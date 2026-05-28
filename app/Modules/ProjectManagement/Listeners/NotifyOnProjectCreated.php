<?php

namespace App\Modules\ProjectManagement\Listeners;

use App\Modules\ProjectManagement\Events\ProjectCreated;
use App\Modules\MCP\Adapters\NotionAdapter;
use App\Modules\MCP\Adapters\GoogleCalendarAdapter;
use App\Modules\MCP\Adapters\ZohoCliqAdapter;
use App\Modules\MCP\Models\McpConnection;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class NotifyOnProjectCreated implements ShouldQueue
{
    public function handle(ProjectCreated $event): void
    {
        $project = $event->project;
        $orgId   = $project->organization_id;

        // Push project page to Notion if connected
        $this->tryNotion($project, $orgId);

        // Create Google Calendar event for project deadline if connected
        $this->tryCalendar($project, $orgId);

        // Notify Zoho Cliq channel
        $this->tryZohoCliq($project, $orgId);
    }

    private function tryNotion($project, string $orgId): void
    {
        try {
            $conn = McpConnection::where('organization_id', $orgId)
                ->where('provider', 'notion')
                ->where('status', 'active')
                ->first();

            if (!$conn) return;

            app(NotionAdapter::class)->push($conn->id, [
                'entity_type' => 'project',
                'entity_id'   => $project->id,
                'title'       => $project->name,
                'status'      => $project->status,
                'description' => $project->description ?? '',
                'due_date'    => optional($project->end_date)->toDateString(),
            ]);
        } catch (\Exception $e) {
            Log::warning("Notion push failed for project {$project->id}: " . $e->getMessage());
        }
    }

    private function tryCalendar($project, string $orgId): void
    {
        if (!$project->end_date) return;

        try {
            $conn = McpConnection::where('organization_id', $orgId)
                ->where('provider', 'google_calendar')
                ->where('status', 'active')
                ->first();

            if (!$conn) return;

            app(GoogleCalendarAdapter::class)->push($conn->id, [
                'entity_type' => 'deadline',
                'title'       => 'Project Deadline: ' . $project->name,
                'date'        => $project->end_date->toDateString(),
                'description' => "Project {$project->name} deadline.",
            ]);
        } catch (\Exception $e) {
            Log::warning("Calendar push failed for project {$project->id}: " . $e->getMessage());
        }
    }

    private function tryZohoCliq($project, string $orgId): void
    {
        try {
            $conn = McpConnection::where('organization_id', $orgId)
                ->where('provider', 'zoho_cliq')
                ->where('status', 'active')
                ->first();

            if (!$conn) return;

            app(ZohoCliqAdapter::class)->push($conn->id, [
                'entity_type' => 'channel_message',
                'channel'     => 'projects',
                'message'     => "New project created: *{$project->name}*\nStatus: {$project->status}",
            ]);
        } catch (\Exception $e) {
            Log::warning("Zoho Cliq push failed for project {$project->id}: " . $e->getMessage());
        }
    }
}

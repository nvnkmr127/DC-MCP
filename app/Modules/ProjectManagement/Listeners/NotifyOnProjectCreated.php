<?php

namespace App\Modules\ProjectManagement\Listeners;

use App\Modules\ProjectManagement\Events\ProjectCreated;
use App\Modules\MCP\Models\McpConnection;
use App\Jobs\PushMcpOutboundActionJob;
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

            PushMcpOutboundActionJob::dispatch($conn->id, [
                'entity_type' => 'project',
                'entity_id'   => $project->id,
                'title'       => $project->name,
                'status'      => $project->status,
                'description' => $project->description ?? '',
                'due_date'    => optional($project->end_date)->toDateString(),
            ], [
                'idempotency_key' => 'project_created_notion_' . $project->id
            ])->onQueue('high');
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

            PushMcpOutboundActionJob::dispatch($conn->id, [
                'entity_type' => 'deadline',
                'title'       => 'Project Deadline: ' . $project->name,
                'date'        => $project->end_date->toDateString(),
                'description' => "Project {$project->name} deadline.",
            ], [
                'idempotency_key' => 'project_created_gcal_' . $project->id
            ])->onQueue('high');
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

            PushMcpOutboundActionJob::dispatch($conn->id, [
                'entity_type' => 'channel_message',
                'channel'     => 'projects',
                'message'     => "New project created: *{$project->name}*\nStatus: {$project->status}",
            ], [
                'idempotency_key' => 'project_created_zoho_' . $project->id
            ])->onQueue('high');
        } catch (\Exception $e) {
            Log::warning("Zoho Cliq push failed for project {$project->id}: " . $e->getMessage());
        }
    }
}

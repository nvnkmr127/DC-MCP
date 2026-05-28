<?php

namespace App\Modules\ProjectManagement\Listeners;

use App\Modules\ProjectManagement\Events\TaskStatusChanged;
use App\Modules\MCP\Adapters\NotionAdapter;
use App\Modules\MCP\Adapters\ZohoCliqAdapter;
use App\Modules\MCP\Models\McpConnection;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class NotifyOnTaskStatusChanged implements ShouldQueue
{
    public function __construct(private NotificationService $notificationService) {}

    public function handle(TaskStatusChanged $event): void
    {
        $task   = $event->task;
        $orgId  = $task->organization_id;

        // Push updated status to Notion
        $this->tryNotion($task, $orgId);

        // Alert Zoho Cliq only for significant transitions
        $significant = ['done', 'blocked', 'cancelled'];
        if (in_array($event->newStatus, $significant)) {
            $this->tryZohoCliq($task, $event->oldStatus, $event->newStatus, $orgId);
        }

        // In-app notification to the task's assignee (if not the actor)
        if ($task->assigned_to && $task->assigned_to !== $event->actor->id) {
            try {
                $assignee = \App\Modules\Auth\Models\User::find($task->assigned_to);
                if ($assignee) {
                    $this->notificationService->sendNotification(
                        $assignee,
                        'task_status_changed',
                        'in_app',
                        'Task updated: ' . $task->title,
                        "{$event->actor->name} changed status from {$event->oldStatus} to {$event->newStatus}.",
                        ['task_id' => $task->id]
                    );
                }
            } catch (\Exception $e) {
                Log::warning("In-app notification failed for task {$task->id}: " . $e->getMessage());
            }
        }
    }

    private function tryNotion($task, string $orgId): void
    {
        try {
            $conn = McpConnection::where('organization_id', $orgId)
                ->where('provider', 'notion')
                ->where('status', 'active')
                ->first();

            if (!$conn) return;

            app(NotionAdapter::class)->push($conn->id, [
                'entity_type' => 'task',
                'entity_id'   => $task->id,
                'title'       => $task->title,
                'status'      => $task->status,
            ]);
        } catch (\Exception $e) {
            Log::warning("Notion task update failed for task {$task->id}: " . $e->getMessage());
        }
    }

    private function tryZohoCliq($task, string $oldStatus, string $newStatus, string $orgId): void
    {
        try {
            $conn = McpConnection::where('organization_id', $orgId)
                ->where('provider', 'zoho_cliq')
                ->where('status', 'active')
                ->first();

            if (!$conn) return;

            $emoji = match ($newStatus) {
                'done'      => '✅',
                'blocked'   => '🚫',
                'cancelled' => '❌',
                default     => '🔄',
            };

            app(ZohoCliqAdapter::class)->push($conn->id, [
                'entity_type' => 'channel_message',
                'channel'     => 'tasks',
                'message'     => "{$emoji} Task *{$task->title}* moved from _{$oldStatus}_ to _{$newStatus}_.",
            ]);
        } catch (\Exception $e) {
            Log::warning("Zoho Cliq task alert failed for task {$task->id}: " . $e->getMessage());
        }
    }
}

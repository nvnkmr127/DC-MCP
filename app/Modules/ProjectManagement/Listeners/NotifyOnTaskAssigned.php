<?php

namespace App\Modules\ProjectManagement\Listeners;

use App\Modules\ProjectManagement\Events\TaskAssigned;
use App\Modules\MCP\Adapters\GoogleCalendarAdapter;
use App\Modules\MCP\Models\McpConnection;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class NotifyOnTaskAssigned implements ShouldQueue
{
    public function __construct(private NotificationService $notificationService) {}

    public function handle(TaskAssigned $event): void
    {
        $task    = $event->task;
        $user    = $event->assignee;
        $orgId   = $task->organization_id;

        // Email notification to the assignee
        try {
            $this->notificationService->sendNotification(
                $user,
                'task_assigned',
                'email',
                'Task Assigned: ' . $task->title,
                "You have been assigned to task: {$task->title}" .
                    ($task->due_date ? '. Due: ' . $task->due_date->toDateString() : '.'),
                ['task_id' => $task->id]
            );
        } catch (\Exception $e) {
            Log::warning("Email notification failed for task assignment {$task->id}: " . $e->getMessage());
        }

        // Add to Google Calendar if task has a due date
        if ($task->due_date) {
            $this->tryCalendar($task, $user, $orgId);
        }
    }

    private function tryCalendar($task, $user, string $orgId): void
    {
        try {
            $conn = McpConnection::where('organization_id', $orgId)
                ->where('provider', 'google_calendar')
                ->where('status', 'active')
                ->first();

            if (!$conn) return;

            app(GoogleCalendarAdapter::class)->push($conn->id, [
                'entity_type' => 'task_due',
                'title'       => 'Due: ' . $task->title,
                'date'        => $task->due_date->toDateString(),
                'description' => "Task due: {$task->title}",
                'attendee'    => $user->email,
            ]);
        } catch (\Exception $e) {
            Log::warning("Calendar task event failed for task {$task->id}: " . $e->getMessage());
        }
    }
}

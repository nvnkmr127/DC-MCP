<?php

namespace App\Modules\TaskEngine\Services;

use App\Modules\ProjectManagement\Models\Task;
use App\Modules\Auth\Models\User;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SlaEngine
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Check all active tasks for SLA warnings and breaches.
     */
    public function checkSlas(): void
    {
        $tasks = Task::whereNotIn('status', ['done', 'cancelled'])
            ->whereNotNull('sla_hours')
            ->whereNull('sla_breached_at')
            ->get();

        foreach ($tasks as $task) {
            $created = $task->created_at;
            $deadline = $created->copy()->addHours($task->sla_hours);
            $now = now();

            if ($now->greaterThanOrEqualTo($deadline)) {
                // SLA breached!
                $task->update([
                    'sla_breached_at' => $deadline,
                ]);

                // Log breach
                DB::table('task_logs')->insert([
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'task_id' => $task->id,
                    'user_id' => null,
                    'action' => 'sla_breached',
                    'old_value' => null,
                    'new_value' => json_encode(['sla_breached_at' => $deadline]),
                    'comment' => "SLA breached. Deadline was {$deadline}.",
                    'logged_at' => now(),
                ]);

                // Send notifications
                $this->notifyUsers($task, 'sla_breached', "SLA Breached: {$task->title}", "Task {$task->title} has breached its SLA of {$task->sla_hours} hours. Deadline was {$deadline}.");
            } else {
                // Check for SLA warning (within 4 hours of deadline)
                $warningTime = $deadline->copy()->subHours(4);
                $meta = $task->meta ?? [];
                $alreadyWarned = $meta['sla_warned'] ?? false;

                if ($now->greaterThanOrEqualTo($warningTime) && !$alreadyWarned) {
                    $meta['sla_warned'] = true;
                    $task->update(['meta' => $meta]);

                    // Log warning
                    DB::table('task_logs')->insert([
                        'id' => (string) \Illuminate\Support\Str::uuid(),
                        'task_id' => $task->id,
                        'user_id' => null,
                        'action' => 'sla_warning',
                        'old_value' => null,
                        'new_value' => json_encode(['sla_warned' => true]),
                        'comment' => "SLA warning. Less than 4 hours remaining until {$deadline}.",
                        'logged_at' => now(),
                    ]);

                    // Send notifications
                    $this->notifyUsers($task, 'sla_warning', "SLA Warning: {$task->title}", "Task {$task->title} is approaching its SLA deadline {$deadline} (less than 4 hours remaining).");
                }
            }
        }
    }

    /**
     * Notify relevant users (assignee, project manager, or organization CEO).
     */
    protected function notifyUsers(Task $task, string $type, string $title, string $body): void
    {
        $recipients = [];

        // 1. Assignee
        if ($task->assigned_to) {
            $assignee = User::find($task->assigned_to);
            if ($assignee) {
                $recipients[] = $assignee;
            }
        }

        // 2. Project Manager
        if ($task->project && $task->project->project_manager_id) {
            $pm = User::find($task->project->project_manager_id);
            if ($pm && !in_array($pm->id, array_map(fn($r) => $r->id, $recipients))) {
                $recipients[] = $pm;
            }
        }

        // 3. Always include CEO on SLA breaches (not warnings)
        if ($type === 'sla_breached') {
            $ceo = User::where('organization_id', $task->organization_id)
                ->whereHas('roles', fn($q) => $q->where('slug', 'ceo'))
                ->first();
            if ($ceo && !in_array($ceo->id, array_map(fn($r) => $r->id, $recipients))) {
                $recipients[] = $ceo;
            }
        } elseif (empty($recipients)) {
            // Fallback to CEO for warnings when no one else is assigned
            $ceo = User::where('organization_id', $task->organization_id)
                ->whereHas('roles', fn($q) => $q->where('slug', 'ceo'))
                ->first();
            if ($ceo) {
                $recipients[] = $ceo;
            }
        }

        foreach ($recipients as $recipient) {
            try {
                $this->notificationService->sendNotification(
                    $recipient,
                    $type,
                    'in_app', // Default channels
                    $title,
                    $body,
                    ['task_id' => $task->id]
                );
            } catch (\Exception $e) {
                // Suppress in pipeline
            }
        }
    }
}

<?php

namespace App\Modules\ProjectManagement\Services;

use App\Modules\ProjectManagement\Models\Task;
use App\Modules\ProjectManagement\Models\TimeEntry;
use App\Modules\Auth\Models\User;
use App\Modules\TaskEngine\Services\TaskDependencyService;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\ProjectManagement\Events\TaskStatusChanged;
use App\Modules\ProjectManagement\Events\TaskAssigned;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use App\Models\Activity;
use Carbon\Carbon;

class TaskService
{
    protected TaskDependencyService $dependencyService;
    protected NotificationService $notificationService;

    public function __construct(
        TaskDependencyService $dependencyService,
        NotificationService $notificationService
    ) {
        $this->dependencyService = $dependencyService;
        $this->notificationService = $notificationService;
    }

    /**
     * Create a task, log activity, and notify user.
     */
    public function createTask(array $data): Task
    {
        return DB::transaction(function () use ($data) {
            $task = Task::create($data);

            // Log creation
            DB::table('task_logs')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'task_id' => $task->id,
                'user_id' => auth()->id() ?: $task->created_by,
                'action' => 'created',
                'old_value' => null,
                'new_value' => json_encode($task->toArray()),
                'comment' => 'Task created.',
                'logged_at' => now(),
            ]);

            Activity::create([
                'organization_id' => $task->organization_id,
                'subject_type' => 'task',
                'subject_id' => $task->id,
                'event' => 'created',
                'user_id' => auth()->id() ?: $task->created_by,
                'changes' => ['new' => $task->toArray()],
                'description' => 'Task created',
            ]);

            // Notify assignee if set
            if ($task->assigned_to) {
                $assignee = User::find($task->assigned_to);
                if ($assignee) {
                    $project = \App\Modules\ProjectManagement\Models\Project::find($task->project_id);
                    $projectName = $project ? $project->name : 'Unknown Project';

                    $this->notificationService->sendNotification(
                        $assignee,
                        'task_assigned',
                        'in_app',
                        'Task Assigned: ' . $task->title,
                        'You have been assigned to: ' . $task->title,
                        [
                            'task_id' => $task->id,
                            'project_id' => $task->project_id,
                            'group_key' => 'project_assigned_' . $task->project_id,
                            'group_title' => '{count} new tasks assigned in ' . $projectName,
                            'group_body' => 'You have {count} new tasks assigned to you in ' . $projectName,
                        ]
                    );
                }
            }

            return $task;
        });
    }

    /**
     * Update task status, log history, calculate SLAs, trigger unlocking, etc.
     */
    public function updateTaskStatus(Task $task, string $newStatus, User $actor): Task
    {
        return DB::transaction(function () use ($task, $newStatus, $actor) {
            $oldStatus = $task->status;
            if ($oldStatus === $newStatus) {
                return $task;
            }

            $updateData = ['status' => $newStatus];

            if ($newStatus === 'done') {
                $updateData['completed_at'] = now();
            } else {
                $updateData['completed_at'] = null;
            }

            $task->update($updateData);

            // Dispatch event for downstream listeners (Notion push, Zoho Cliq alert, etc.)
            Event::dispatch(new TaskStatusChanged($task, $oldStatus, $newStatus, $actor));

            // Log status change
            DB::table('task_logs')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'task_id' => $task->id,
                'user_id' => $actor->id,
                'action' => 'status_changed',
                'old_value' => json_encode(['status' => $oldStatus]),
                'new_value' => json_encode(['status' => $newStatus]),
                'comment' => "Status changed from {$oldStatus} to {$newStatus}.",
                'logged_at' => now(),
            ]);

            Activity::create([
                'organization_id' => $task->organization_id,
                'subject_type' => 'task',
                'subject_id' => $task->id,
                'event' => 'status_changed',
                'user_id' => $actor->id,
                'changes' => ['old' => ['status' => $oldStatus], 'new' => ['status' => $newStatus]],
                'description' => "Status changed from {$oldStatus} to {$newStatus}",
            ]);

            // Unlock downstream dependent tasks if this task is completed
            if ($newStatus === 'done') {
                $this->dependencyService->unlockDependencies($task);

                // Log SLA breach if task was completed after its SLA deadline
                if ($task->sla_hours && $task->created_at) {
                    $slaDeadline = $task->created_at->addHours($task->sla_hours);
                    if (now()->isAfter($slaDeadline)) {
                        $breachedByMinutes = (int) $slaDeadline->diffInMinutes(now());
                        Log::warning('Task completed after SLA deadline', [
                            'task_id'             => $task->id,
                            'organization_id'     => $task->organization_id,
                            'sla_hours'           => $task->sla_hours,
                            'breached_by_minutes' => $breachedByMinutes,
                            'completed_by'        => $actor->id,
                        ]);
                        DB::table('task_logs')->insert([
                            'id'        => (string) \Illuminate\Support\Str::uuid(),
                            'task_id'   => $task->id,
                            'user_id'   => $actor->id,
                            'action'    => 'sla_breached',
                            'old_value' => null,
                            'new_value' => json_encode(['breached_by_minutes' => $breachedByMinutes]),
                            'comment'   => "Completed {$breachedByMinutes} minutes after SLA deadline.",
                            'logged_at' => now(),
                        ]);
                    }
                }
            }

            return $task;
        });
    }

    /**
     * Assign task to a user, log action, and notify user.
     */
    public function assignTask(Task $task, User $user, User $actor): Task
    {
        return DB::transaction(function () use ($task, $user, $actor) {
            $oldAssignedTo = $task->assigned_to;
            if ($oldAssignedTo === $user->id) {
                return $task;
            }

            $task->update(['assigned_to' => $user->id]);

            // Dispatch event for downstream listeners
            Event::dispatch(new TaskAssigned($task, $user, $actor));

            // Create assignment record in task_assignments
            DB::table('task_assignments')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'task_id' => $task->id,
                'user_id' => $user->id,
                'assigned_by' => $actor->id,
                'assigned_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Mark previous assignment as unassigned (if applicable)
            if ($oldAssignedTo) {
                DB::table('task_assignments')
                    ->where('task_id', $task->id)
                    ->where('user_id', $oldAssignedTo)
                    ->whereNull('unassigned_at')
                    ->update(['unassigned_at' => now()]);
            }

            // Log assignment in activity logs
            DB::table('task_logs')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'task_id' => $task->id,
                'user_id' => $actor->id,
                'action' => 'assigned',
                'old_value' => json_encode(['assigned_to' => $oldAssignedTo]),
                'new_value' => json_encode(['assigned_to' => $user->id]),
                'comment' => "Assigned to {$user->name}.",
                'logged_at' => now(),
            ]);

            Activity::create([
                'organization_id' => $task->organization_id,
                'subject_type' => 'task',
                'subject_id' => $task->id,
                'event' => 'assigned',
                'user_id' => $actor->id,
                'changes' => ['old' => ['assigned_to' => $oldAssignedTo], 'new' => ['assigned_to' => $user->id]],
                'description' => "Assigned to {$user->name}",
            ]);

            // Dispatch notification to user
            $project = \App\Modules\ProjectManagement\Models\Project::find($task->project_id);
            $projectName = $project ? $project->name : 'Unknown Project';

            $this->notificationService->sendNotification(
                $user,
                'task_assigned',
                'in_app',
                'Task Assigned: ' . $task->title,
                'You have been assigned to: ' . $task->title,
                [
                    'task_id' => $task->id,
                    'project_id' => $task->project_id,
                    'group_key' => 'project_assigned_' . $task->project_id,
                    'group_title' => '{count} new tasks assigned in ' . $projectName,
                    'group_body' => 'You have {count} new tasks assigned to you in ' . $projectName,
                ]
            );

            return $task;
        });
    }

    /**
     * Log time entry and update task actual hours.
     */
    public function logTime(Task $task, User $user, float $hours, string $description, Carbon $date): TimeEntry
    {
        return DB::transaction(function () use ($task, $user, $hours, $description, $date) {
            $timeEntry = TimeEntry::create([
                'organization_id' => $task->organization_id,
                'task_id' => $task->id,
                'user_id' => $user->id,
                'project_id' => $task->project_id,
                'description' => $description,
                'hours' => $hours,
                'logged_date' => $date,
                'is_billable' => true,
            ]);

            // Update actual hours on task
            $task->increment('actual_hours', $hours);

            // Log time log action
            DB::table('task_logs')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'task_id' => $task->id,
                'user_id' => $user->id,
                'action' => 'time_logged',
                'old_value' => null,
                'new_value' => json_encode(['hours' => $hours]),
                'comment' => "Logged {$hours} hours: {$description}",
                'logged_at' => now(),
            ]);

            return $timeEntry;
        });
    }
}

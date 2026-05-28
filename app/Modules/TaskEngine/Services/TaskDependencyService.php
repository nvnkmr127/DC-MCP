<?php

namespace App\Modules\TaskEngine\Services;

use App\Modules\ProjectManagement\Models\Task;
use Illuminate\Support\Facades\DB;

class TaskDependencyService
{
    /**
     * Unlock downstream tasks that depend on this completed task.
     */
    public function unlockDependencies(Task $completedTask): void
    {
        // Find tasks in the same project that might depend on this task
        // We look for tasks where meta->depends_on contains the completed task's ID
        $dependentTasks = Task::where('project_id', $completedTask->project_id)
            ->where('status', 'backlog')
            ->get();

        foreach ($dependentTasks as $task) {
            $meta = $task->meta;
            if (!$meta || !isset($meta['depends_on'])) {
                continue;
            }

            $dependencies = $meta['depends_on']; // Array of task IDs
            if (!in_array($completedTask->id, $dependencies)) {
                continue;
            }

            // Check if all dependencies for this task are completed (done or cancelled)
            $allCompleted = true;
            $dependencyTasks = Task::whereIn('id', $dependencies)->get();
            
            foreach ($dependencyTasks as $depTask) {
                if (!in_array($depTask->status, ['done', 'cancelled'])) {
                    $allCompleted = false;
                    break;
                }
            }

            if ($allCompleted) {
                // Unlock task: move from backlog to todo
                $task->update(['status' => 'todo']);

                // Log status change
                DB::table('task_logs')->insert([
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'task_id' => $task->id,
                    'user_id' => null, // system action
                    'action' => 'status_changed',
                    'old_value' => json_encode(['status' => 'backlog']),
                    'new_value' => json_encode(['status' => 'todo']),
                    'comment' => "Unlocked dependency: all predecessor tasks are completed.",
                    'logged_at' => now(),
                ]);

                // Trigger auto-assignment if exactly one team member has the required role
                // Let's resolve the spawner service to perform role assignment if needed, or handle it here
                $this->autoAssignIfPossible($task);
            }
        }
    }

    /**
     * Auto assign task if exactly one team member matches the required role.
     */
    protected function autoAssignIfPossible(Task $task): void
    {
        if (!$task->role_required || $task->assigned_to) {
            return;
        }

        // Get project team members
        // Project team is defined by the users in the organization who have the required role
        $users = \App\Modules\Auth\Models\User::where('organization_id', $task->organization_id)
            ->where('is_active', true)
            ->whereHas('roles', function ($query) use ($task) {
                $query->where('slug', $task->role_required);
            })
            ->get();

        if ($users->count() === 1) {
            $user = $users->first();
            $task->update(['assigned_to' => $user->id]);

            // Log assignment
            DB::table('task_assignments')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'task_id' => $task->id,
                'user_id' => $user->id,
                'assigned_by' => null, // system auto-assign
                'assigned_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('task_logs')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'task_id' => $task->id,
                'user_id' => null,
                'action' => 'assigned',
                'old_value' => null,
                'new_value' => json_encode(['assigned_to' => $user->id]),
                'comment' => "Auto-assigned to {$user->name} based on required role: {$task->role_required}.",
                'logged_at' => now(),
            ]);

            // Send notification
            try {
                $notificationService = app(\App\Modules\Notifications\Services\NotificationService::class);
                $notificationService->sendNotification(
                    $user,
                    'task_assigned',
                    'in_app',
                    'Task Auto-Assigned: ' . $task->title,
                    'You have been auto-assigned to: ' . $task->title,
                    ['task_id' => $task->id]
                );
            } catch (\Exception $e) {
                // Ignore notification failures during automated pipeline
            }
        }
    }
}

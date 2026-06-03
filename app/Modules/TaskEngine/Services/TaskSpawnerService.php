<?php

namespace App\Modules\TaskEngine\Services;

use App\Modules\ProjectManagement\Models\Project;
use App\Modules\ProjectManagement\Models\Task;
use App\Modules\TaskEngine\Models\TaskTemplate;
use App\Modules\Auth\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TaskSpawnerService
{
    /**
     * Spawn tasks for a project based on its type templates.
     */
    public function spawnFromTemplate(Project $project): array
    {
        $templates = TaskTemplate::where('project_type', $project->type)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        if ($templates->isEmpty()) {
            return [];
        }

        return DB::transaction(function () use ($project, $templates) {
            $spawnedTasks = [];
            $templateMap = []; // sort_order -> Task

            // 1. First pass: Create all tasks
            foreach ($templates as $template) {
                $task = Task::create([
                    'organization_id' => $project->organization_id,
                    'project_id' => $project->id,
                    'title' => $template->title,
                    'description' => $template->description,
                    'type' => $template->type,
                    'status' => 'todo', // default, will adjust based on dependencies
                    'priority' => 'medium',
                    'role_required' => $template->role_required,
                    'estimated_hours' => $template->estimated_hours,
                    'sla_hours' => $template->sla_hours,
                    'sort_order' => $template->sort_order,
                    'created_by' => auth()->id() ?: $project->project_manager_id,
                ]);

                $spawnedTasks[] = $task;
                $templateMap[$template->sort_order] = $task;

                // Log task creation
                DB::table('task_logs')->insert([
                    'id' => (string) Str::uuid(),
                    'task_id' => $task->id,
                    'user_id' => auth()->id(),
                    'action' => 'created',
                    'old_value' => null,
                    'new_value' => json_encode($task->toArray()),
                    'comment' => "Task spawned from template: {$template->title}.",
                    'logged_at' => now(),
                ]);
            }

            // 2. Second pass: Map dependencies and resolve statuses
            foreach ($templates as $template) {
                $task = $templateMap[$template->sort_order];
                $dependsOnSortOrders = $template->depends_on;

                if (!empty($dependsOnSortOrders)) {
                    $dependsOnIds = [];
                    foreach ($dependsOnSortOrders as $depSortOrder) {
                        if (isset($templateMap[$depSortOrder])) {
                            $dependsOnIds[] = $templateMap[$depSortOrder]->id;
                        }
                    }

                    if (!empty($dependsOnIds)) {
                        $task->update([
                            'status' => 'backlog', // Start blocked tasks in backlog
                            'meta' => array_merge($task->meta ?? [], ['depends_on' => $dependsOnIds]),
                        ]);
                    }
                }
            }

            // 3. Third pass: Auto-assign tasks that are ready (status is todo)
            foreach ($spawnedTasks as $task) {
                // Refresh task status and meta
                $task->refresh();
                if ($task->status === 'todo') {
                    $this->autoAssignIfPossible($task);
                }
            }

            return $spawnedTasks;
        });
    }

    /**
     * Auto assign task if exactly one team member matches the required role.
     */
    protected function autoAssignIfPossible(Task $task): void
    {
        if (!$task->role_required || $task->assigned_to) {
            return;
        }

        // Find active organization users with the required role
        $users = User::where('organization_id', $task->organization_id)
            ->where('is_active', true)
            ->whereHas('roles', function ($query) use ($task) {
                $query->where('slug', $task->role_required);
            })
            ->get();

        if ($users->count() === 1) {
            $user = $users->first();
            $task->update(['assigned_to' => $user->id]);

            // Create assignment record in task_assignments
            DB::table('task_assignments')->insert([
                'id' => (string) Str::uuid(),
                'task_id' => $task->id,
                'user_id' => $user->id,
                'assigned_by' => null, // system auto-assign
                'assigned_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Log assignment
            DB::table('task_logs')->insert([
                'id' => (string) Str::uuid(),
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
                Log::error('Auto-assign notification failed during task spawn', [
                    'task_id'   => $task->id ?? null,
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }
}

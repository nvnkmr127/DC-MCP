<?php

namespace App\Modules\ProjectManagement\Services;

use App\Modules\ProjectManagement\Models\Project;
use App\Modules\ProjectManagement\Models\Task;
use App\Modules\Auth\Models\Organization;
use App\Modules\Auth\Models\User;
use App\Modules\TaskEngine\Services\TaskSpawnerService;
use App\Modules\ProjectManagement\Events\ProjectCreated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ProjectService
{
    protected TaskSpawnerService $taskSpawner;

    public function __construct(TaskSpawnerService $taskSpawner)
    {
        $this->taskSpawner = $taskSpawner;
    }

    /**
     * Create a new project and auto-spawn tasks from the template.
     */
    public function createProject(array $data): Project
    {
        return DB::transaction(function () use ($data) {
            if (empty($data['slug'])) {
                $data['slug'] = Str::slug($data['name']);
            }

            // If a project with this slug already exists in this organization, append a random suffix
            $orgId = $data['organization_id'] ?? (auth()->check() ? auth()->user()->organization_id : null);
            if ($orgId) {
                $exists = Project::where('organization_id', $orgId)->where('slug', $data['slug'])->exists();
                if ($exists) {
                    $data['slug'] .= '-' . Str::lower(Str::random(4));
                }
            }

            $project = Project::create(collect($data)->except('project_template_id')->toArray());

            if (!empty($data['project_template_id'])) {
                $template = \App\Modules\ProjectManagement\Models\ProjectTemplate::find($data['project_template_id']);
                if ($template && $template->organization_id === $orgId) {
                    $startDate = \Carbon\Carbon::parse($project->start_date ?? now());
                    foreach ($template->tasks ?? [] as $taskDef) {
                        $dueDate = $startDate->copy()->addDays($taskDef['offset_days'] ?? 0);
                        $priority = ($taskDef['priority'] ?? 'medium') === 'urgent' ? 'critical' : ($taskDef['priority'] ?? 'medium');
                        \App\Modules\ProjectManagement\Models\Task::create([
                            'organization_id' => $orgId,
                            'project_id'      => $project->id,
                            'created_by'      => auth()->id(),
                            'title'           => $taskDef['title'],
                            'priority'        => $priority,
                            'due_date'        => $dueDate,
                            'estimated_hours' => $taskDef['estimated_hours'] ?? null,
                            'status'          => 'todo',
                        ]);
                    }
                }
            } else {
                // Spawn tasks from template for the project type
                $this->taskSpawner->spawnFromTemplate($project);
            }

            // Dispatch event so listeners can handle Notion, Calendar, Cliq notifications
            Event::dispatch(new ProjectCreated($project));

            return $project;
        });
    }

    /**
     * Update an existing project.
     */
    public function updateProject(Project $project, array $data): Project
    {
        if (isset($data['name']) && !isset($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }
        $project->update($data);
        return $project;
    }

    /**
     * Archive/Delete a project.
     */
    public function archiveProject(Project $project): bool
    {
        return $project->delete();
    }

    /**
     * Get statistics for a project.
     */
    public function getProjectStats(Project $project): array
    {
        $totalTasks = $project->tasks()->count();
        $completedTasks = $project->tasks()->where('status', 'done')->count();

        $progressPercentage = $project->completionPct($totalTasks, $completedTasks);

        $tasksByStatus = $project->tasks()
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Ensure all possible statuses are represented
        $statuses = ['backlog', 'todo', 'in_progress', 'in_review', 'blocked', 'done', 'cancelled'];
        foreach ($statuses as $status) {
            if (!isset($tasksByStatus[$status])) {
                $tasksByStatus[$status] = 0;
            }
        }

        $timeLogged = (float) DB::table('time_entries')
            ->where('project_id', $project->id)
            ->sum('hours');

        $daysRemaining = 0;
        if ($project->end_date) {
            $daysRemaining = Carbon::now()->diffInDays($project->end_date, false);
        }

        return [
            'progress_percentage' => $progressPercentage,
            'tasks_by_status' => $tasksByStatus,
            'budget' => (float) $project->budget,
            'budget_used' => (float) $project->budget_used,
            'time_logged' => $timeLogged,
            'days_remaining' => $daysRemaining,
            'total_tasks' => $totalTasks,
            'completed_tasks' => $completedTasks,
        ];
    }

    /**
     * Get team workload stats for an organization.
     */
    public function getTeamWorkload(Organization $org): array
    {
        // Get all active members of the organization
        $users = User::where('organization_id', $org->id)
            ->where('is_active', true)
            ->get();

        $workload = [];

        foreach ($users as $user) {
            // Unfinished tasks assigned to this user
            $activeTasksQuery = Task::where('assigned_to', $user->id)
                ->whereIn('status', ['todo', 'in_progress', 'in_review', 'blocked']);

            $taskCount = $activeTasksQuery->count();
            $estimatedHours = (float) $activeTasksQuery->sum('estimated_hours');
            $overdueTasksCount = $activeTasksQuery->whereNotNull('due_date')
                ->where('due_date', '<', Carbon::today())
                ->count();

            $workload[] = [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'task_count' => $taskCount,
                'estimated_hours' => $estimatedHours,
                'overdue_tasks_count' => $overdueTasksCount,
            ];
        }

        return $workload;
    }
}

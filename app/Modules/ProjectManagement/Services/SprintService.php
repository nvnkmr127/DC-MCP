<?php

namespace App\Modules\ProjectManagement\Services;

use App\Modules\ProjectManagement\Models\Project;
use App\Modules\ProjectManagement\Models\Sprint;
use App\Modules\ProjectManagement\Models\Task;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SprintService
{
    /**
     * Create a new sprint for a project.
     */
    public function createSprint(Project $project, array $data): Sprint
    {
        $data['project_id'] = $project->id;
        $data['status'] = $data['status'] ?? 'planning';
        return Sprint::create($data);
    }

    /**
     * Start a sprint (transition status to active).
     */
    public function startSprint(Sprint $sprint): Sprint
    {
        return DB::transaction(function () use ($sprint) {
            // Optionally, pause other active sprints for the same project
            Sprint::where('project_id', $sprint->project_id)
                ->where('status', 'active')
                ->update(['status' => 'planning']);

            $sprint->update([
                'status' => 'active',
                'start_date' => $sprint->start_date ?? Carbon::today(),
            ]);

            return $sprint;
        });
    }

    /**
     * Complete a sprint.
     * Roll over unfinished tasks to backlog or a next sprint.
     */
    public function completeSprint(Sprint $sprint, string $unfinishedTaskAction = 'backlog', ?Sprint $nextSprint = null): Sprint
    {
        return DB::transaction(function () use ($sprint, $unfinishedTaskAction, $nextSprint) {
            // Calculate actual velocity (sum of estimated_hours of completed tasks in this sprint)
            $completedTasksEstimateSum = Task::where('sprint_id', $sprint->id)
                ->where('status', 'done')
                ->sum('estimated_hours');

            $sprint->update([
                'status' => 'completed',
                'end_date' => $sprint->end_date ?? Carbon::today(),
                'velocity_actual' => (int) $completedTasksEstimateSum,
            ]);

            // Unfinished tasks are tasks not in 'done' or 'cancelled' status
            $unfinishedTasks = Task::where('sprint_id', $sprint->id)
                ->whereNotIn('status', ['done', 'cancelled'])
                ->get();

            foreach ($unfinishedTasks as $task) {
                if ($unfinishedTaskAction === 'next_sprint' && $nextSprint) {
                    $task->update([
                        'sprint_id' => $nextSprint->id,
                        // Keep its status as is (todo/in_progress etc)
                    ]);
                } else {
                    // Move to backlog
                    $task->update([
                        'sprint_id' => null,
                        'status' => 'backlog',
                    ]);
                }
            }

            return $sprint;
        });
    }
}

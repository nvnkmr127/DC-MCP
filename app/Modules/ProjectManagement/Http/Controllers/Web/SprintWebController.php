<?php

namespace App\Modules\ProjectManagement\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\ProjectManagement\Models\Sprint;
use App\Modules\ProjectManagement\Models\SprintTask;
use App\Modules\ProjectManagement\Models\Task;
use App\Modules\ProjectManagement\Services\SprintService;
use App\Modules\ProjectManagement\Http\Requests\StoreSprintRequest;
use App\Modules\ProjectManagement\Http\Requests\UpdateSprintRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SprintWebController extends Controller
{
    public function __construct(
        protected SprintService $sprintService
    ) {}

    public function index(Request $request): Response
    {
        if (!$request->user()->hasPermission('view', 'sprint')) {
            abort(403);
        }

        $sprints = Sprint::whereHas('project')
            ->with(['project:id,name', 'sprintTasks.task:id,title,status'])
            ->orderByDesc('start_date')
            ->get()
            ->map(fn($s) => [
                'id'           => $s->id,
                'name'         => $s->name,
                'goal'         => $s->goal,
                'status'              => is_object($s->status) ? $s->status->value : $s->status,
                'start_date'          => $s->start_date?->toDateString(),
                'end_date'            => $s->end_date?->toDateString(),
                'retrospective_notes' => $s->retrospective_notes,
                'project'             => $s->project ? ['id' => $s->project->id, 'name' => $s->project->name] : null,
                'sprint_tasks'        => $s->sprintTasks->map(fn($st) => [
                    'id'           => $st->id,
                    'story_points' => $st->story_points,
                    'task'         => $st->task ? ['id' => $st->task->id, 'title' => $st->task->title, 'status' => is_object($st->task->status) ? $st->task->status->value : $st->task->status] : null,
                ]),
            ]);

        $projects = Project::select('id', 'name')->orderBy('name')->get();

        return Inertia::render('Sprints/Index', [
            'sprints'  => $sprints,
            'projects' => $projects,
        ]);
    }

    public function store(StoreSprintRequest $request): RedirectResponse
    {
        $project = Project::findOrFail($request->project_id);
        $this->sprintService->createSprint($project, $request->validated());

        return back()->with('success', 'Sprint created.');
    }

    public function update(UpdateSprintRequest $request, Sprint $sprint): RedirectResponse
    {
        $sprint->update($request->validated());
        return back()->with('success', 'Sprint updated.');
    }

    public function destroy(Request $request, Sprint $sprint): RedirectResponse
    {
        if (!$request->user()->hasPermission('delete', 'sprint')) {
            abort(403);
        }

        SprintTask::where('sprint_id', $sprint->id)->delete();
        $sprint->delete();
        return back()->with('success', 'Sprint deleted.');
    }

    public function addTask(Request $request, Sprint $sprint): RedirectResponse
    {
        if (!$request->user()->hasPermission('update', 'sprint')) {
            abort(403);
        }

        $validated = $request->validate([
            'task_id'      => [
                'required',
                'uuid',
                Rule::exists(Task::class, 'id')
                    ->where('project_id', $sprint->project_id)
                    ->whereNull('deleted_at'),
            ],
            'story_points' => 'nullable|integer|min:0',
        ]);

        SprintTask::firstOrCreate(
            ['sprint_id' => $sprint->id, 'task_id' => $validated['task_id']],
            ['story_points' => $validated['story_points'] ?? 0]
        );

        return back()->with('success', 'Task added to sprint.');
    }

    public function removeTask(Request $request, Sprint $sprint, Task $task): RedirectResponse
    {
        if (!$request->user()->hasPermission('update', 'sprint')) {
            abort(403);
        }

        abort_if($task->project_id !== $sprint->project_id, 422, 'Task does not belong to this sprint project.');

        SprintTask::where('sprint_id', $sprint->id)->where('task_id', $task->id)->delete();
        return back()->with('success', 'Task removed from sprint.');
    }

    public function complete(Request $request, Sprint $sprint): RedirectResponse
    {
        if (!$request->user()->hasPermission('update', 'sprint')) {
            abort(403);
        }

        $validated = $request->validate([
            'unfinished_action'   => 'required|in:backlog,new_sprint',
            'new_sprint_name'     => 'nullable|string|max:255',
            'retrospective_notes' => 'nullable|string',
        ]);

        $openSprintTasks = SprintTask::where('sprint_id', $sprint->id)
            ->whereHas('task', function($q) {
                $q->whereNotIn('status', ['done', 'cancelled']);
            })->get();

        if ($validated['unfinished_action'] === 'new_sprint' && !empty($validated['new_sprint_name'])) {
            $newSprint = Sprint::create([
                'project_id' => $sprint->project_id,
                'name'       => $validated['new_sprint_name'],
                'status'     => 'planning',
                'start_date' => now()->toDateString(),
                'end_date'   => now()->addWeeks(2)->toDateString(),
            ]);

            foreach ($openSprintTasks as $st) {
                SprintTask::create([
                    'sprint_id'    => $newSprint->id,
                    'task_id'      => $st->task_id,
                    'story_points' => $st->story_points,
                ]);
            }
        }

        // Remove open tasks from the current sprint to clean it up
        foreach ($openSprintTasks as $st) {
            $st->delete();
        }

        $sprint->update([
            'status' => 'completed',
            'retrospective_notes' => $validated['retrospective_notes'] ?? null,
        ]);

        return back()->with('success', 'Sprint completed.');
    }
}

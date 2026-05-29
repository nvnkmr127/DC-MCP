<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\ProjectManagement\Models\Sprint;
use App\Modules\ProjectManagement\Models\SprintTask;
use App\Modules\ProjectManagement\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SprintController extends Controller
{
    public function index(Request $request): Response
    {
        $orgId = $request->user()->organization_id;

        $sprints = Sprint::whereHas('project', fn($q) => $q->where('organization_id', $orgId))
            ->with(['project:id,name'])
            ->orderByDesc('start_date')
            ->get()
            ->map(fn($s) => [
                'id'           => $s->id,
                'name'         => $s->name,
                'goal'         => $s->goal,
                'status'       => $s->status,
                'start_date'   => $s->start_date?->toDateString(),
                'end_date'     => $s->end_date?->toDateString(),
                'project'      => $s->project ? ['id' => $s->project->id, 'name' => $s->project->name] : null,
                'sprint_tasks' => SprintTask::where('sprint_id', $s->id)->with('task:id,title,status')->get()->map(fn($st) => [
                    'id'           => $st->id,
                    'story_points' => $st->story_points,
                    'task'         => $st->task ? ['id' => $st->task->id, 'title' => $st->task->title, 'status' => $st->task->status] : null,
                ]),
            ]);

        $projects = Project::where('organization_id', $orgId)->select('id', 'name')->orderBy('name')->get();

        return Inertia::render('Sprints/Index', [
            'sprints'  => $sprints,
            'projects' => $projects,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'project_id' => 'required|uuid|exists:projects,id',
            'name'       => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after:start_date',
            'goal'       => 'nullable|string',
            'status'     => 'sometimes|in:planning,active,completed',
        ]);

        Sprint::create([
            ...$validated,
            'organization_id' => $request->user()->organization_id,
        ]);

        return back()->with('success', 'Sprint created.');
    }

    public function update(Request $request, Sprint $sprint): RedirectResponse
    {
        $validated = $request->validate([
            'name'       => 'sometimes|string|max:255',
            'start_date' => 'sometimes|date',
            'end_date'   => 'sometimes|date',
            'goal'       => 'nullable|string',
            'status'     => 'sometimes|in:planning,active,completed',
        ]);

        $sprint->update($validated);
        return back()->with('success', 'Sprint updated.');
    }

    public function destroy(Request $request, Sprint $sprint): RedirectResponse
    {
        SprintTask::where('sprint_id', $sprint->id)->delete();
        $sprint->delete();
        return back()->with('success', 'Sprint deleted.');
    }

    public function addTask(Request $request, Sprint $sprint): RedirectResponse
    {
        $validated = $request->validate([
            'task_id'      => 'required|uuid|exists:tasks,id',
            'story_points' => 'nullable|integer|min:0',
        ]);

        SprintTask::firstOrCreate(
            ['sprint_id' => $sprint->id, 'task_id' => $validated['task_id']],
            ['organization_id' => $request->user()->organization_id, 'story_points' => $validated['story_points'] ?? 0]
        );

        return back()->with('success', 'Task added to sprint.');
    }

    public function removeTask(Request $request, Sprint $sprint, Task $task): RedirectResponse
    {
        SprintTask::where('sprint_id', $sprint->id)->where('task_id', $task->id)->delete();
        return back()->with('success', 'Task removed from sprint.');
    }
}

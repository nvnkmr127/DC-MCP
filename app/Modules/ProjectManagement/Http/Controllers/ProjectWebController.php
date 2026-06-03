<?php

namespace App\Modules\ProjectManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\ProjectManagement\Models\Task;
use App\Modules\Auth\Models\User;
use App\Modules\ProjectManagement\Models\Client;

class ProjectWebController extends Controller
{
    public function index(Request $request)
    {
        $query = Project::with('client')
            ->withCount(['tasks', 'tasks as completed_tasks_count' => fn($q) => $q->where('status', 'done')])
            ->orderByDesc('updated_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $projects = $query->paginate(18)->through(fn($p) => [
            'id'               => $p->id,
            'name'             => $p->name,
            'status'           => $p->status,
            'priority'         => $p->priority,
            'start_date'       => $p->start_date?->toDateString(),
            'end_date'         => $p->end_date?->toDateString(),
            'budget'           => (float) $p->budget,
            'budget_used'      => (float) $p->budget_used,
            'total_tasks'      => $p->tasks_count,
            'completed_tasks'  => $p->completed_tasks_count,
            'completion_pct'   => $p->completionPct($p->tasks_count, $p->completed_tasks_count),
            'client'           => $p->client ? ['id' => $p->client->id, 'name' => $p->client->name] : null,
            'tags'             => $p->tags ?? [],
        ]);

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
            'filters'  => $request->only(['status', 'search']),
        ]);
    }

    public function create(Request $request)
    {
        $clients = Client::select('id', 'name')->orderBy('name')->get();
        $members = User::select('id', 'name')->orderBy('name')->get();

        return Inertia::render('Projects/Create', [
            'clients' => $clients,
            'members' => $members,
            'defaults' => [
                'status'     => 'planning',
                'project_id' => $request->query('project_id'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'               => 'required|string|max:200',
            'description'        => 'nullable|string',
            'client_id'          => 'nullable|uuid|exists:clients,id',
            'status'             => 'required|in:planning,active,on_hold,completed,cancelled',
            'priority'           => 'required|in:urgent,high,medium,low',
            'start_date'         => 'nullable|date',
            'end_date'           => 'nullable|date',
            'budget'             => 'nullable|numeric|min:0',
            'project_manager_id' => 'nullable|uuid|exists:users,id',
            'tags'               => 'nullable|array',
            'type'               => 'nullable|string',
        ]);

        $project = Project::create([
            ...$data,
            'organization_id' => $request->user()->organization_id,
            'slug'            => Str::slug($data['name']) . '-' . substr(uniqid(), -5),
        ]);

        return redirect()->route('web.projects.show', $project)->with('success', 'Project created.');
    }

    public function show(Project $project)
    {
        $project->load(['client', 'manager', 'milestones', 'sprints']);
        $project->loadCount(['tasks', 'tasks as completed_tasks_count' => fn($q) => $q->where('status', 'done')]);

        return Inertia::render('Projects/Show', [
            'project' => array_merge($project->toArray(), [
                'completion_pct' => $project->completionPct($project->tasks_count, $project->completed_tasks_count),
            ]),
        ]);
    }

    public function edit(Project $project)
    {
        $clients = Client::select('id', 'name')->orderBy('name')->get();
        $members = User::select('id', 'name')->orderBy('name')->get();

        return Inertia::render('Projects/Edit', [
            'project' => $project->load('client'),
            'clients' => $clients,
            'members' => $members,
        ]);
    }

    public function update(Request $request, Project $project)
    {
        $data = $request->validate([
            'name'               => 'sometimes|string|max:200',
            'description'        => 'nullable|string',
            'client_id'          => 'nullable|uuid|exists:clients,id',
            'status'             => 'sometimes|in:planning,active,on_hold,completed,cancelled',
            'priority'           => 'sometimes|in:urgent,high,medium,low',
            'start_date'         => 'nullable|date',
            'end_date'           => 'nullable|date',
            'budget'             => 'nullable|numeric|min:0',
            'project_manager_id' => 'nullable|uuid|exists:users,id',
            'tags'               => 'nullable|array',
        ]);

        $project->update($data);

        return back()->with('success', 'Project updated.');
    }

    public function destroy(Project $project)
    {
        $project->delete();
        return redirect()->route('web.projects.index')->with('success', 'Project deleted.');
    }

    public function kanban(Project $project)
    {
        $tasks = $project->tasks()
            ->with('assignee:id,name')
            ->whereNull('parent_task_id')
            ->orderBy('sort_order')
            ->get()
            ->map(fn($t) => [
                'id'              => $t->id,
                'title'           => $t->title,
                'status'          => $t->status,
                'priority'        => $t->priority,
                'due_date'        => $t->due_date?->toDateString(),
                'estimated_hours' => (float) $t->estimated_hours,
                'assignee'        => $t->assignee ? ['id' => $t->assignee->id, 'name' => $t->assignee->name] : null,
                'tags'            => $t->tags ?? [],
            ]);

        return Inertia::render('Projects/Kanban', [
            'project' => $project->only('id', 'name', 'status'),
            'tasks'   => $tasks,
        ]);
    }

    public function stats(Project $project)
    {
        $project->load(['client']);
        $tasksByStatus = $project->tasks()->selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status');

        return Inertia::render('Projects/Stats', [
            'project'       => $project->only('id', 'name', 'status', 'budget', 'budget_used', 'start_date', 'end_date'),
            'tasks_by_status' => $tasksByStatus,
        ]);
    }
}

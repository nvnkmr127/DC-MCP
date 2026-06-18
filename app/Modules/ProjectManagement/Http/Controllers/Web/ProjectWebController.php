<?php

namespace App\Modules\ProjectManagement\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\Auth\Models\User;
use App\Modules\ProjectManagement\Models\Client;
use App\Modules\ProjectManagement\Services\ProjectService;
use App\Modules\ProjectManagement\Http\Requests\StoreProjectRequest;
use App\Modules\ProjectManagement\Http\Requests\UpdateProjectRequest;

class ProjectWebController extends Controller
{
    public function __construct(
        protected ProjectService $projectService
    ) {}

    public function index(Request $request)
    {
        if (!$request->user()->hasPermission('view', 'project')) {
            abort(403);
        }

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
        if (!$request->user()->hasPermission('create', 'project')) {
            abort(403);
        }

        $clients = Client::select('id', 'name')->orderBy('name')->get();
        $members = User::where('organization_id', $request->user()->organization_id)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Projects/Create', [
            'clients' => $clients,
            'members' => $members,
            'defaults' => [
                'status'     => 'planning',
                'project_id' => $request->query('project_id'),
            ],
        ]);
    }

    public function store(StoreProjectRequest $request)
    {
        $project = $this->projectService->createProject($request->validated());
        return redirect()->route('web.projects.show', $project)->with('success', 'Project created.');
    }

    public function show(Request $request, Project $project)
    {
        if (!$request->user()->hasPermission('view', 'project')) {
            abort(403);
        }

        $project->load(['client', 'manager', 'milestones', 'sprints']);
        $project->loadCount(['tasks', 'tasks as completed_tasks_count' => fn($q) => $q->where('status', 'done')]);

        $activities = \App\Models\Activity::where('subject_type', 'project')
            ->where('subject_id', $project->id)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($a) => [
                'id'          => $a->id,
                'event'       => $a->event,
                'description' => $a->description,
                'changes'     => $a->changes,
                'created_at'  => $a->created_at->toISOString(),
                'user'        => $a->user ? ['id' => $a->user->id, 'name' => $a->user->name] : null,
            ]);

        return Inertia::render('Projects/Show', [
            'project' => array_merge($project->toArray(), [
                'completion_pct' => $project->completionPct($project->tasks_count, $project->completed_tasks_count),
                'activities' => $activities,
            ]),
        ]);
    }

    public function edit(Request $request, Project $project)
    {
        if (!$request->user()->hasPermission('update', 'project')) {
            abort(403);
        }

        $clients = Client::select('id', 'name')->orderBy('name')->get();
        $members = User::where('organization_id', $request->user()->organization_id)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Projects/Edit', [
            'project' => $project->load('client'),
            'clients' => $clients,
            'members' => $members,
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project)
    {
        $project = $this->projectService->updateProject($project, $request->validated());
        return back()->with('success', 'Project updated.');
    }

    public function destroy(Request $request, Project $project)
    {
        if (!$request->user()->hasPermission('delete', 'project')) {
            abort(403);
        }

        $this->projectService->deleteProject($project);
        return redirect()->route('web.projects.index')->with('success', 'Project deleted.');
    }

    public function kanban(Request $request, Project $project)
    {
        if (!$request->user()->hasPermission('view', 'project')) {
            abort(403);
        }

        $tasks = $project->tasks()
            ->with('assignee:id,name')
            ->whereNull('parent_task_id')
            ->orderBy('sort_order')
            ->get()
            ->map(fn($t) => [
                'id'              => $t->id,
                'title'           => $t->title,
                'status'          => is_object($t->status) ? $t->status->value : $t->status,
                'priority'        => is_object($t->priority) ? $t->priority->value : $t->priority,
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

    public function stats(Request $request, Project $project)
    {
        if (!$request->user()->hasPermission('view', 'project')) {
            abort(403);
        }

        $project->load(['client']);
        $tasksByStatus = $project->tasks()->selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status');

        return Inertia::render('Projects/Stats', [
            'project'       => $project->only('id', 'name', 'status', 'budget', 'budget_used', 'start_date', 'end_date'),
            'tasks_by_status' => $tasksByStatus,
        ]);
    }

    public function milestones(Request $request, Project $project)
    {
        if (!$request->user()->hasPermission('view', 'project')) {
            abort(403);
        }

        $project->load('milestones');

        return Inertia::render('Projects/Milestones', [
            'project' => $project->only('id', 'name', 'status'),
            'milestones' => $project->milestones,
        ]);
    }
}

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
            
        $templates = \App\Modules\ProjectManagement\Models\ProjectTemplate::where('organization_id', $request->user()->organization_id)
            ->select('id', 'name', 'service_type', 'description')
            ->orderBy('name')
            ->get();

        return Inertia::render('Projects/Create', [
            'clients' => $clients,
            'members' => $members,
            'templates' => $templates,
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

        $project->load([
            'client.retainers', 
            'manager', 
            'milestones.goal', 
            'sprints',
            'expenses',
            'invoices' => fn($q) => $q->where('status', '!=', 'cancelled'),
            'campaignBudgets',
            'tasks.timeEntries',
            'issues' => fn($q) => $q->with('assignee:id,name')->orderByDesc('created_at'),
            'assets' => fn($q) => $q->with('submitter:id,name')->orderByDesc('created_at')
        ]);
        
        $project->loadCount(['tasks', 'tasks as completed_tasks_count' => fn($q) => $q->where('status', 'done')]);

        // Activities
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

        // Tasks (Kanban)
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

        // Financials calculation
        $invoices = $project->invoices;
        $expenses = $project->expenses;
        $campaignBudgets = $project->campaignBudgets;

        $invoicedRevenue = $invoices->sum('amount');
        $baseBudget = (float) $project->budget;
        $revenue = $invoicedRevenue > 0 ? $invoicedRevenue : $baseBudget; 
        
        $totalExpenses = $expenses->sum('amount');
        $totalAdSpend = $campaignBudgets->sum('spent_amount');

        $blendedRate = 500;
        $totalLoggedHours = $project->tasks->flatMap->timeEntries->sum('hours_logged');
        $laborCost = $totalLoggedHours * $blendedRate;

        $totalCosts = $totalExpenses + $totalAdSpend + $laborCost;
        $profitMargin = $revenue - $totalCosts;
        $profitMarginPercent = $revenue > 0 ? round(($profitMargin / $revenue) * 100, 1) : 0;

        $goals = \App\Modules\Revenue\Models\Goal::where('organization_id', $request->user()->organization_id)->get(['id', 'title']);

        // Team unique users extraction
        $teamMap = collect();
        if ($project->manager) {
            $teamMap->put($project->manager->id, $project->manager);
        }
        foreach ($project->tasks as $task) {
            if ($task->assignee) {
                $teamMap->put($task->assignee->id, $task->assignee);
            }
        }

        return Inertia::render('Projects/Show', [
            'project' => array_merge($project->toArray(), [
                'completion_pct' => $project->completionPct($project->tasks_count, $project->completed_tasks_count),
                'total_logged_hours' => $totalLoggedHours,
                'activities' => $activities,
            ]),
            'tasks' => $tasks,
            'goals' => $goals,
            'team' => $teamMap->values()->map(fn($u) => ['id' => $u->id, 'name' => $u->name]),
            'financials' => [
                'revenue' => $revenue,
                'invoiced_revenue' => $invoicedRevenue,
                'base_budget' => $baseBudget,
                'total_expenses' => $totalExpenses,
                'total_ad_spend' => $totalAdSpend,
                'labor_cost' => $laborCost,
                'total_costs' => $totalCosts,
                'profit_margin' => $profitMargin,
                'profit_margin_percent' => $profitMarginPercent,
            ],
            'invoices' => $invoices->map(fn($i) => [
                'id' => $i->id, 'invoice_number' => $i->invoice_number, 'amount' => $i->amount, 'status' => $i->status, 'issue_date' => $i->issue_date?->toDateString()
            ]),
            'expenses' => $expenses->map(fn($e) => [
                'id' => $e->id, 'title' => $e->title, 'amount' => $e->amount, 'category' => $e->category, 'expense_date' => $e->expense_date?->toDateString()
            ]),
            'campaignBudgets' => $campaignBudgets->map(fn($c) => [
                'id' => $c->id, 'channel' => $c->channel, 'allocated_budget' => $c->allocated_budget, 'spent_amount' => $c->spent_amount, 'month_year' => $c->month_year
            ]),
            'retainers' => $project->client && $project->client->retainers ? $project->client->retainers->map(fn($r) => [
                'id' => $r->id, 'name' => $r->name, 'monthly_value' => $r->monthly_value, 'status' => $r->status, 'billing_cycle' => $r->billing_cycle, 'currency' => $r->currency
            ]) : [],
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


    public function storeMilestone(Request $request, Project $project)
    {
        if (!$request->user()->hasPermission('edit', 'project')) {
            abort(403);
        }
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'status' => 'required|in:pending,in_progress,completed',
            'goal_id' => 'nullable|uuid|exists:goals,id',
        ]);

        $project->milestones()->create($validated);

        return back()->with('success', 'Milestone created successfully.');
    }

    public function updateMilestone(Request $request, Project $project, \App\Modules\ProjectManagement\Models\Milestone $milestone)
    {
        if (!$request->user()->hasPermission('edit', 'project')) {
            abort(403);
        }

        if ($milestone->project_id !== $project->id) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'due_date' => 'sometimes|nullable|date',
            'status' => 'sometimes|in:pending,in_progress,completed',
            'goal_id' => 'sometimes|nullable|uuid|exists:goals,id',
        ]);

        if (isset($validated['status']) && $validated['status'] === 'completed' && $milestone->status !== 'completed') {
            $validated['completed_at'] = now();
        } elseif (isset($validated['status']) && $validated['status'] !== 'completed') {
            $validated['completed_at'] = null;
        }

        $milestone->update($validated);

        return back()->with('success', 'Milestone updated successfully.');
    }


}

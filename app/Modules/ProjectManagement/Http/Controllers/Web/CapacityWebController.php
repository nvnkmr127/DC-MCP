<?php

namespace App\Modules\ProjectManagement\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\ProjectManagement\Models\Task;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CapacityWebController extends Controller
{
    public function index(Request $request): Response
    {
        $orgId = $request->user()->organization_id;

        $team = User::where('organization_id', $orgId)
            ->where('is_active', true)
            ->withCount([
                'assignedTasks as total_tasks' => fn($q) =>
                    $q->whereIn('status', ['todo', 'in_progress', 'in_review']),
                'assignedTasks as urgent_tasks' => fn($q) =>
                    $q->whereIn('status', ['todo', 'in_progress', 'in_review'])
                      ->where('priority', 'critical'),
                'assignedTasks as overdue_tasks' => fn($q) =>
                    $q->whereIn('status', ['todo', 'in_progress', 'in_review'])
                      ->whereDate('due_date', '<', now()),
                'assignedTasks as due_today' => fn($q) =>
                    $q->whereIn('status', ['todo', 'in_progress', 'in_review'])
                      ->whereDate('due_date', today()),
            ])
            ->with('roles')
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get()
            ->map(fn($u) => [
                'id'           => $u->id,
                'name'         => $u->name,
                'email'        => $u->email,
                'role'         => $u->role,
                'total_tasks'  => $u->total_tasks,
                'urgent_tasks' => $u->urgent_tasks,
                'overdue_tasks'=> $u->overdue_tasks,
                'due_today'    => $u->due_today,
                'load_percent' => min(100, (int) round(($u->total_tasks / max(1, 10)) * 100)),
            ]);

        $activeTasks = Task::where('organization_id', $orgId)
            ->whereIn('status', ['todo', 'in_progress', 'in_review'])
            ->with('assignee:id,name')
            ->select('id', 'title', 'status', 'priority', 'due_date', 'assigned_to', 'project_id')
            ->orderBy('due_date')
            ->limit(100)
            ->get()
            ->map(fn($t) => [
                'id'       => $t->id,
                'title'    => $t->title,
                'status'   => $t->status,
                'priority' => $t->priority,
                'due_date' => $t->due_date?->toDateString(),
                'assignee' => $t->assignee ? ['id' => $t->assignee->id, 'name' => $t->assignee->name] : null,
            ]);

        return Inertia::render('Capacity/Index', [
            'team'        => $team,
            'activeTasks' => $activeTasks,
            'stats'       => [
                'total_active'     => $activeTasks->count(),
                'unassigned'       => $activeTasks->whereNull('assignee')->count(),
                'overloaded_count' => $team->where('total_tasks', '>', 10)->count(),
            ],
        ]);
    }
}

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
        $weekStart = now()->startOfWeek()->toDateString();
        $weekEnd = now()->endOfWeek()->toDateString();

        $todayStandups = \App\Modules\Standup\Models\EodStandup::where('organization_id', $orgId)
            ->whereDate('date', today())
            ->get()
            ->keyBy('user_id');

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
            ->withSum(['assignedTasks as total_estimated_hours' => fn($q) => 
                $q->whereIn('status', ['todo', 'in_progress', 'in_review'])
            ], 'estimated_hours')
            ->withSum(['timeEntries as logged_hours_this_week' => fn($q) => 
                $q->whereBetween('logged_date', [$weekStart, $weekEnd])
            ], 'hours')
            ->with('roles')
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get()
            ->map(function($u) use ($todayStandups) {
                $estimated = (float) $u->total_estimated_hours;
                $logged = (float) $u->logged_hours_this_week;
                // Standard 40h workweek for capacity
                $capacity = 40;
                $loadPercent = min(100, (int) round(($logged / $capacity) * 100));
                
                $standup = $todayStandups->get($u->id);

                return [
                    'id'                     => $u->id,
                    'name'                   => $u->name,
                    'email'                  => $u->email,
                    'role'                   => $u->role,
                    'total_tasks'            => $u->total_tasks,
                    'urgent_tasks'           => $u->urgent_tasks,
                    'overdue_tasks'          => $u->overdue_tasks,
                    'due_today'              => $u->due_today,
                    'total_estimated_hours'  => $estimated,
                    'logged_hours_this_week' => $logged,
                    'load_percent'           => $loadPercent,
                    'today_standup'          => $standup ? [
                        'status' => $standup->status,
                        'has_blockers' => !empty(trim((string)$standup->blockers)),
                        'submitted_at' => $standup->submitted_at?->toTimeString(),
                    ] : null,
                ];
            });

        $activeTasks = Task::where('organization_id', $orgId)
            ->whereIn('status', ['todo', 'in_progress', 'in_review'])
            ->with('assignee:id,name')
            ->select('id', 'title', 'status', 'priority', 'due_date', 'assigned_to', 'project_id', 'estimated_hours', 'actual_hours')
            ->orderBy('due_date')
            ->limit(100)
            ->get()
            ->map(fn($t) => [
                'id'              => $t->id,
                'title'           => $t->title,
                'status'          => $t->status,
                'priority'        => $t->priority,
                'due_date'        => $t->due_date?->toDateString(),
                'estimated_hours' => (float) $t->estimated_hours,
                'actual_hours'    => (float) $t->actual_hours,
                'assignee'        => $t->assignee ? ['id' => $t->assignee->id, 'name' => $t->assignee->name] : null,
            ]);

        $recentTimesheets = \App\Modules\ProjectManagement\Models\TimeEntry::where('organization_id', $orgId)
            ->whereBetween('logged_date', [$weekStart, $weekEnd])
            ->with('task:id,title,status,priority')
            ->orderByDesc('logged_date')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($e) => [
                'id'               => $e->id,
                'user_id'          => $e->user_id,
                'task_title'       => $e->task?->title,
                'hours'            => (float) $e->hours,
                'logged_date'      => $e->logged_date?->toDateString(),
                'description'      => $e->description,
                'timer_started_at' => $e->timer_started_at?->toISOString(),
            ]);

        return Inertia::render('Capacity/Index', [
            'team'             => $team,
            'activeTasks'      => $activeTasks,
            'recentTimesheets' => $recentTimesheets,
            'stats'            => [
                'total_active'     => $activeTasks->count(),
                'unassigned'       => $activeTasks->whereNull('assignee')->count(),
                'overloaded_count' => $team->where('load_percent', '>=', 90)->count(),
            ],
        ]);
    }
}

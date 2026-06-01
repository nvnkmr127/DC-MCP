<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;
use App\Modules\ProjectManagement\Models\Task;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\ProjectManagement\Models\Client;
use App\Modules\ProjectManagement\Models\TimeEntry;
use App\Modules\Auth\Models\User;
use App\Modules\Reporting\Models\Report;
use App\Modules\Reporting\Models\ReportSchedule;

class ReportWebController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->filled('from') ? Carbon::parse($request->from) : now()->subDays(30);
        $to   = $request->filled('to')   ? Carbon::parse($request->to)   : now();

        // Task completion over time (daily for 30 days)
        $taskCompletion = DB::table('tasks')
            ->selectRaw('DATE(completed_at) as date, count(*) as count')
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$from, $to])
            ->groupByRaw('DATE(completed_at)')
            ->orderBy('date')
            ->get()
            ->map(fn($r) => ['date' => $r->date, 'completed' => (int) $r->count]);

        // Tasks by status
        $tasksByStatus = Task::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        // Tasks by priority
        $tasksByPriority = Task::selectRaw('priority, count(*) as count')
            ->groupBy('priority')
            ->pluck('count', 'priority');

        // Time logged per user (this period)
        $timeByUser = TimeEntry::selectRaw('user_id, sum(hours) as total_hours')
            ->whereBetween('logged_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('user_id')
            ->with('user:id,name')
            ->get()
            ->map(fn($e) => [
                'user'  => $e->user?->name ?? 'Unknown',
                'hours' => (float) $e->total_hours,
            ]);

        // Time logged per project
        $timeByProject = TimeEntry::selectRaw('project_id, sum(hours) as total_hours')
            ->whereBetween('logged_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('project_id')
            ->with('project:id,name')
            ->get()
            ->map(fn($e) => [
                'project' => $e->project?->name ?? 'Unknown',
                'hours'   => (float) $e->total_hours,
            ]);

        // Project health
        $projects = Project::withCount([
            'tasks',
            'tasks as completed_tasks' => fn($q) => $q->where('status', 'done'),
            'tasks as overdue_tasks'   => fn($q) => $q->whereDate('due_date', '<', now())->whereNotIn('status', ['done', 'cancelled']),
        ])->where('status', 'active')->get()->map(fn($p) => [
            'name'            => $p->name,
            'total'           => $p->tasks_count,
            'completed'       => $p->completed_tasks,
            'overdue'         => $p->overdue_tasks,
            'completion_pct'  => $p->tasks_count > 0 ? round(($p->completed_tasks / $p->tasks_count) * 100) : 0,
        ]);

        // Load generated reports
        $generatedReports = Report::with(['project', 'client', 'generatedBy'])
            ->orderByDesc('created_at')
            ->get();

        // Load schedules
        $reportSchedules = ReportSchedule::with(['project', 'client', 'creator'])
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Reports/Index', [
            'data' => [
                'task_completion'   => $taskCompletion,
                'tasks_by_status'   => $tasksByStatus,
                'tasks_by_priority' => $tasksByPriority,
                'time_by_user'      => $timeByUser,
                'time_by_project'   => $timeByProject,
                'projects'          => $projects,
            ],
            'filters' => [
                'from' => $from->toDateString(),
                'to'   => $to->toDateString(),
            ],
            'reports' => $generatedReports,
            'schedules' => $reportSchedules,
        ]);
    }

    public function create(Request $request)
    {
        $projects = Project::where('status', 'active')->get(['id', 'name', 'client_id']);
        $clients = Client::where('status', 'active')->get(['id', 'name']);

        return Inertia::render('Reports/Create', [
            'projects' => $projects,
            'clients'  => $clients,
        ]);
    }

    public function show(Report $report)
    {
        $report->load(['project', 'client', 'generatedBy']);

        return Inertia::render('Reports/Show', [
            'report' => $report,
        ]);
    }
}

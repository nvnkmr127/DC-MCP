<?php

namespace App\Modules\Reporting\Http\Controllers\Web;

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
        $orgId = $request->user()->organization_id;
        $from  = $request->filled('from') ? Carbon::parse($request->from) : now()->subDays(30);
        $to    = $request->filled('to')   ? Carbon::parse($request->to)   : now();

        // Task completion over time (scoped to org)
        $taskCompletion = DB::table('tasks')
            ->selectRaw('DATE(completed_at) as date, count(*) as count')
            ->where('organization_id', $orgId)
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$from, $to])
            ->groupByRaw('DATE(completed_at)')
            ->orderBy('date')
            ->get()
            ->map(fn($r) => ['date' => $r->date, 'completed' => (int) $r->count]);

        // Tasks by status (scoped to org)
        $tasksByStatus = Task::where('organization_id', $orgId)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        // Tasks by priority (scoped to org)
        $tasksByPriority = Task::where('organization_id', $orgId)
            ->selectRaw('priority, count(*) as count')
            ->groupBy('priority')
            ->pluck('count', 'priority');

        // Time logged per user (scoped to org)
        $timeByUser = TimeEntry::where('organization_id', $orgId)
            ->selectRaw('user_id, sum(hours) as total_hours')
            ->whereBetween('logged_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('user_id')
            ->with('user:id,name')
            ->get()
            ->map(fn($e) => [
                'user'  => $e->user?->name ?? 'Unknown',
                'hours' => (float) $e->total_hours,
            ]);

        // Time logged per project (scoped to org)
        $timeByProject = TimeEntry::where('organization_id', $orgId)
            ->selectRaw('project_id, sum(hours) as total_hours')
            ->whereBetween('logged_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('project_id')
            ->with('project:id,name')
            ->get()
            ->map(fn($e) => [
                'project' => $e->project?->name ?? 'Unknown',
                'hours'   => (float) $e->total_hours,
            ]);

        // Project health — paginated to prevent memory exhaustion on large datasets
        $projects = Project::where('organization_id', $orgId)
            ->where('status', 'active')
            ->withCount([
                'tasks',
                'tasks as completed_tasks' => fn($q) => $q->where('status', 'done'),
                'tasks as overdue_tasks'   => fn($q) => $q->whereDate('due_date', '<', now())->whereNotIn('status', ['done', 'cancelled']),
            ])
            ->paginate(50)
            ->through(fn($p) => [
                'name'           => $p->name,
                'total'          => $p->tasks_count,
                'completed'      => $p->completed_tasks,
                'overdue'        => $p->overdue_tasks,
                'completion_pct' => $p->completionPct($p->tasks_count, $p->completed_tasks),
            ]);

        // Generated reports scoped to org, paginated
        $generatedReports = Report::where('organization_id', $orgId)
            ->with(['project', 'client', 'generatedBy'])
            ->orderByDesc('created_at')
            ->paginate(20);

        // Schedules scoped to org
        $reportSchedules = ReportSchedule::where('organization_id', $orgId)
            ->with(['project', 'client', 'creator'])
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
            'reports'   => $generatedReports,
            'schedules' => $reportSchedules,
        ]);
    }

    public function create(Request $request)
    {
        $orgId    = $request->user()->organization_id;
        $projects = Project::where('organization_id', $orgId)->where('status', 'active')->get(['id', 'name', 'client_id']);
        $clients  = Client::where('organization_id', $orgId)->where('status', 'active')->get(['id', 'name']);

        return Inertia::render('Reports/Create', [
            'projects' => $projects,
            'clients'  => $clients,
        ]);
    }

    public function show(Report $report)
    {
        abort_if($report->organization_id !== request()->user()->organization_id, 403);
        $report->load([
            'project', 
            'client', 
            'generatedBy',
            'comments' => fn($q) => $q->with('user:id,name,email')->latest()
        ]);
        
        return Inertia::render('Reports/Show', [
            'report' => $report,
        ]);
    }

    public function compare(Request $request)
    {
        $id1 = $request->query('id1');
        $id2 = $request->query('id2');

        abort_if(!$id1 || !$id2, 400, 'Two report IDs are required for comparison.');

        $orgId = $request->user()->organization_id;

        $report1 = Report::where('organization_id', $orgId)
            ->with(['project', 'client', 'generatedBy'])
            ->findOrFail($id1);

        $report2 = Report::where('organization_id', $orgId)
            ->with(['project', 'client', 'generatedBy'])
            ->findOrFail($id2);

        return Inertia::render('Reports/Compare', [
            'report1' => $report1,
            'report2' => $report2,
        ]);
    }

    public function storeComment(Request $request, Report $report)
    {
        abort_if($report->organization_id !== $request->user()->organization_id, 403);

        $validated = $request->validate([
            'body' => 'required|string',
        ]);

        $report->comments()->create([
            'user_id' => $request->user()->id,
            'body'    => $validated['body'],
        ]);

        return back()->with('success', 'Comment added.');
    }

    public function destroyComment(Request $request, Report $report, \App\Modules\ProjectManagement\Models\Comment $comment)
    {
        abort_if($report->organization_id !== $request->user()->organization_id, 403);
        abort_if($comment->commentable_id !== $report->id, 403);
        
        // Only author or admin can delete
        if ($comment->user_id !== $request->user()->id && !in_array($request->user()->role, ['owner', 'admin'])) {
            abort(403);
        }

        $comment->delete();

        return back()->with('success', 'Comment deleted.');
    }
}

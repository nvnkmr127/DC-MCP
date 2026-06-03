<?php

namespace App\Modules\DataViz\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;
use App\Modules\ProjectManagement\Models\Task;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\DailyBriefing\Models\DailyBriefing;
use App\Modules\ProjectManagement\Models\TaskLog;

class DashboardWebController extends Controller
{
    public function index(Request $request)
    {
        $user      = $request->user();
        $orgId     = $user->organization_id;
        $weekStart = Carbon::now()->startOfWeek();
        $weekEnd   = Carbon::now()->endOfWeek();
        $today     = Carbon::today();

        // My active tasks (org-scoped via HasOrganization global scope + user filter)
        $myActiveTasks = Task::where('assigned_to', $user->id)
            ->whereNotIn('status', ['done', 'cancelled'])
            ->count();

        // My overdue tasks
        $myOverdueTasks = Task::where('assigned_to', $user->id)
            ->whereNotIn('status', ['done', 'cancelled'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->count();

        // My hours this week
        $myHoursThisWeek = DB::table('time_entries')
            ->where('organization_id', $orgId)
            ->where('user_id', $user->id)
            ->whereBetween('logged_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->sum('hours') ?? 0;

        // Active projects — scoped to org
        $activeProjects = Project::where('organization_id', $orgId)
            ->where('status', 'active')
            ->count();

        // Team overdue — scoped to org
        $teamOverdue = Task::where('organization_id', $orgId)
            ->whereNotIn('status', ['done', 'cancelled'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->count();

        // Tasks by status — scoped to org
        $tasksByStatus = Task::where('organization_id', $orgId)
            ->selectRaw('status, count(*) as count')
            ->whereNotIn('status', ['cancelled'])
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Recent activity — scoped to org via task relationship
        $recentActivity = TaskLog::whereHas('task', fn ($q) => $q->where('organization_id', $orgId))
            ->with(['actor', 'task'])
            ->orderByDesc('logged_at')
            ->limit(20)
            ->get()
            ->map(fn ($log) => [
                'id'         => $log->id,
                'action'     => $log->action,
                'comment'    => $log->comment ?? '',
                'logged_at'  => $log->logged_at,
                'actor_name' => $log->actor?->name ?? 'System',
                'task_title' => $log->task?->title ?? '',
            ]);

        // Today's briefing for this user
        $briefing = DailyBriefing::where('user_id', $user->id)
            ->whereDate('date', $today)
            ->first();

        return Inertia::render('Dashboard/Index', [
            'stats' => [
                'my_active_tasks'    => $myActiveTasks,
                'my_overdue_tasks'   => $myOverdueTasks,
                'my_hours_this_week' => (float) $myHoursThisWeek,
                'active_projects'    => $activeProjects,
                'team_overdue_tasks' => $teamOverdue,
                'tasks_by_status'    => $tasksByStatus,
                'recent_activity'    => $recentActivity,
            ],
            'briefing' => $briefing ? [
                'id'          => $briefing->id,
                'date'        => $briefing->date,
                'digest_text' => $briefing->digest_text,
                'status'      => $briefing->status,
            ] : null,
        ]);
    }
}

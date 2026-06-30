<?php

namespace App\Modules\DataViz\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;
use App\Modules\ProjectManagement\Models\Task;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\DailyBriefing\Models\DailyBriefing;
use App\Modules\ProjectManagement\Models\TaskLog;
use App\Modules\ProjectManagement\Models\Milestone;
use App\Modules\ProjectManagement\Models\AssetApproval;

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

        // Overdue Tasks List
        $overdueTasksList = Task::with(['project:id,name', 'assignee:id,name'])
            ->where('assigned_to', $user->id)
            ->whereNotIn('status', ['done', 'cancelled'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->orderBy('due_date', 'asc')
            ->limit(10)
            ->get();

        // Today's Calendar
        $todayTasks = Task::with(['project:id,name', 'assignee:id,name'])
            ->where('assigned_to', $user->id)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '=', $today)
            ->whereNotIn('status', ['cancelled'])
            ->orderBy('due_date')
            ->get()
            ->map(fn($t) => [
                'id'       => $t->id,
                'title'    => $t->title,
                'date'     => $t->due_date->toDateString(),
                'status'   => $t->status,
                'priority' => $t->priority,
                'type'     => 'task',
                'project'  => $t->project ? ['name' => $t->project->name] : null,
                'url'      => '/tasks/' . $t->id,
            ]);

        $todayMilestones = Milestone::with('project:id,name')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '=', $today)
            ->orderBy('due_date')
            ->get()
            ->map(fn($m) => [
                'id'       => $m->id,
                'title'    => $m->name,
                'date'     => $m->due_date->toDateString(),
                'status'   => $m->status ?? 'pending',
                'priority' => 'high',
                'type'     => 'milestone',
                'project'  => $m->project ? ['name' => $m->project->name] : null,
                'url'      => '/projects/' . $m->project_id,
            ]);

        $todayCalendar = $todayTasks->concat($todayMilestones)->sortBy('date')->values();

        // Pending Approvals (limit 10 for the org/user)
        $pendingApprovals = AssetApproval::with(['project:id,name', 'client:id,name', 'submitter:id,name'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Pending Leave Requests
        $pendingLeaves = \App\Modules\HR\Models\LeaveRequest::with('user:id,name')
            ->where('organization_id', $orgId)
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(fn($l) => [
                'id' => $l->id,
                'user_name' => $l->user?->name,
                'type' => $l->type,
                'from_date' => $l->from_date->toDateString(),
                'to_date' => $l->to_date->toDateString(),
                'days' => (float) $l->days,
                'reason' => $l->reason,
            ]);

        // Setup Checklist
        $teamInvited = \App\Modules\Auth\Models\User::where('organization_id', $orgId)->count() > 1;
        $projectCreated = Project::where('organization_id', $orgId)->count() > 0;
        $mcpConnected = DB::table('mcp_connections')->where('organization_id', $orgId)->exists();
        $taskCreated = Task::where('organization_id', $orgId)->exists();
        
        $setupChecklist = [
            ['id' => 'org', 'title' => 'Register Organization', 'done' => true, 'href' => null],
            ['id' => 'team', 'title' => 'Invite Team Members', 'done' => $teamInvited, 'href' => '/settings/team'],
            ['id' => 'project', 'title' => 'Create First Project', 'done' => $projectCreated, 'href' => '/projects/create'],
            ['id' => 'mcp', 'title' => 'Connect MCP Provider', 'done' => $mcpConnected, 'href' => '/settings/mcp'],
            ['id' => 'task', 'title' => 'Create First Task', 'done' => $taskCreated, 'href' => '/projects'],
        ];

        $myActiveTasksList = Task::with('project:id,name')
            ->where('organization_id', $orgId)
            ->where('assigned_to', $user->id)
            ->whereNotIn('status', ['done', 'cancelled'])
            ->orderByRaw('due_date IS NULL, due_date ASC')
            ->limit(10)
            ->get()
            ->map(fn($t) => [
                'id'       => $t->id,
                'title'    => $t->title,
                'due_date' => $t->due_date?->toDateString(),
                'status'   => is_object($t->status) ? $t->status->value : $t->status,
                'project'  => $t->project ? ['name' => $t->project->name] : null,
            ]);

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
            'my_active_tasks_list' => $myActiveTasksList,
            'briefing' => $briefing ? [
                'id'          => $briefing->id,
                'date'        => $briefing->date,
                'digest_text' => $briefing->digest_text,
                'status'      => $briefing->status,
            ] : null,
            'setup_checklist' => $setupChecklist,
            'overdue_tasks_list' => $overdueTasksList,
            'today_calendar'     => $todayCalendar,
            'pending_approvals'  => $pendingApprovals,
            'pending_leaves'     => $pendingLeaves,
        ]);
    }
}

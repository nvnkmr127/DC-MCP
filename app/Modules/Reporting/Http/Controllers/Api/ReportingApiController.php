<?php

namespace App\Modules\Reporting\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Shared\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportingApiController extends Controller
{
    public function taskSummary(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to'   => ['nullable', 'date'],
        ]);

        $orgId = $request->user()->organization_id;
        $from  = Carbon::parse($request->input('from', now()->startOfMonth()));
        $to    = Carbon::parse($request->input('to', now()->endOfMonth()));

        $byStatus = DB::table('tasks')
            ->where('organization_id', $orgId)
            ->whereBetween('created_at', [$from, $to])
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $completedCount = DB::table('tasks')
            ->where('organization_id', $orgId)
            ->where('status', 'done')
            ->whereBetween('completed_at', [$from, $to])
            ->count();

        $overdueCount = DB::table('tasks')
            ->where('organization_id', $orgId)
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->whereNotIn('status', ['done', 'cancelled'])
            ->count();

        $avgCompletionHours = DB::table('tasks')
            ->where('organization_id', $orgId)
            ->where('status', 'done')
            ->whereBetween('completed_at', [$from, $to])
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->select(DB::raw('avg(extract(epoch from (completed_at - started_at)) / 3600) as avg_hours'))
            ->value('avg_hours');

        return ApiResponse::success([
            'period'            => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'by_status'         => $byStatus,
            'completed'         => $completedCount,
            'overdue'           => $overdueCount,
            'avg_completion_hrs' => round((float) $avgCompletionHours, 2),
        ]);
    }

    public function projectSummary(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to'   => ['nullable', 'date'],
        ]);

        $orgId = $request->user()->organization_id;
        $from  = Carbon::parse($request->input('from', now()->startOfMonth()));
        $to    = Carbon::parse($request->input('to', now()->endOfMonth()));

        $projects = DB::table('projects')
            ->where('organization_id', $orgId)
            ->select('id', 'name', 'status', 'start_date', 'end_date', 'budget', 'budget_used')
            ->get()
            ->map(function ($p) {
                $totalTasks     = DB::table('tasks')->where('project_id', $p->id)->count();
                $completedTasks = DB::table('tasks')->where('project_id', $p->id)->where('status', 'done')->count();
                $timeLogged     = (float) DB::table('time_entries')->where('project_id', $p->id)->sum('hours');

                return [
                    'id'               => $p->id,
                    'name'             => $p->name,
                    'status'           => $p->status,
                    'start_date'       => $p->start_date,
                    'end_date'         => $p->end_date,
                    'budget'           => (float) $p->budget,
                    'budget_used'      => (float) $p->budget_used,
                    'total_tasks'      => $totalTasks,
                    'completed_tasks'  => $completedTasks,
                    'completion_pct'   => $p->completionPct($totalTasks, $completedTasks),
                    'time_logged_hrs'  => $timeLogged,
                ];
            });

        return ApiResponse::success($projects);
    }

    public function teamProductivity(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to'   => ['nullable', 'date'],
        ]);

        $orgId = $request->user()->organization_id;
        $from  = Carbon::parse($request->input('from', now()->startOfMonth()));
        $to    = Carbon::parse($request->input('to', now()->endOfMonth()));

        $members = DB::table('users')
            ->where('organization_id', $orgId)
            ->where('is_active', true)
            ->select('id', 'name', 'email')
            ->get()
            ->map(function ($u) use ($from, $to) {
                $tasksCompleted = DB::table('tasks')
                    ->where('assigned_to', $u->id)
                    ->where('status', 'done')
                    ->whereBetween('completed_at', [$from, $to])
                    ->count();

                $hoursLogged = (float) DB::table('time_entries')
                    ->where('user_id', $u->id)
                    ->whereBetween('logged_date', [$from, $to])
                    ->sum('hours');

                $tasksOverdue = DB::table('tasks')
                    ->where('assigned_to', $u->id)
                    ->whereNotNull('due_date')
                    ->where('due_date', '<', now())
                    ->whereNotIn('status', ['done', 'cancelled'])
                    ->count();

                return [
                    'user_id'         => $u->id,
                    'name'            => $u->name,
                    'email'           => $u->email,
                    'tasks_completed' => $tasksCompleted,
                    'hours_logged'    => $hoursLogged,
                    'tasks_overdue'   => $tasksOverdue,
                ];
            });

        return ApiResponse::success($members);
    }

    public function timeReport(Request $request): JsonResponse
    {
        $request->validate([
            'from'       => ['nullable', 'date'],
            'to'         => ['nullable', 'date'],
            'project_id' => ['nullable', 'uuid'],
            'user_id'    => ['nullable', 'uuid'],
        ]);

        $orgId = $request->user()->organization_id;
        $from  = Carbon::parse($request->input('from', now()->startOfMonth()));
        $to    = Carbon::parse($request->input('to', now()->endOfMonth()));

        $query = DB::table('time_entries')
            ->join('users', 'time_entries.user_id', '=', 'users.id')
            ->join('projects', 'time_entries.project_id', '=', 'projects.id')
            ->where('time_entries.organization_id', $orgId)
            ->whereBetween('time_entries.logged_date', [$from, $to])
            ->select(
                'time_entries.id',
                'time_entries.hours',
                'time_entries.description',
                'time_entries.logged_date',
                'time_entries.is_billable',
                'users.name as user_name',
                'projects.name as project_name'
            );

        if ($request->filled('project_id')) {
            $query->where('time_entries.project_id', $request->project_id);
        }

        if ($request->filled('user_id')) {
            $query->where('time_entries.user_id', $request->user_id);
        }

        $entries = $query->orderByDesc('time_entries.logged_date')->paginate(50);

        return ApiResponse::paginated($entries);
    }
}

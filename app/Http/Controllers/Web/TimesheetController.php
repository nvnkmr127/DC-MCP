<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\ProjectManagement\Models\Task;
use App\Modules\ProjectManagement\Models\TimeEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TimesheetController extends Controller
{
    public function index(Request $request): Response
    {
        $user  = $request->user();
        $orgId = $user->organization_id;
        $isCeo = $user->hasRoles(['ceo', 'project_manager']);

        $weekStart = $request->filled('week')
            ? \Carbon\Carbon::parse($request->week)->startOfWeek()
            : now()->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        $viewUser = $request->filled('user_id') && $isCeo
            ? $request->user_id
            : $user->id;

        $entries = TimeEntry::where('organization_id', $orgId)
            ->when(!$isCeo || !$request->filled('user_id'), fn($q) => $q->where('user_id', $viewUser))
            ->when($isCeo && $request->filled('user_id'), fn($q) => $q->where('user_id', $viewUser))
            ->whereBetween('logged_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->with(['task:id,title,project_id', 'task.project:id,name'])
            ->orderBy('logged_date')
            ->get()
            ->map(fn($e) => [
                'id'              => $e->id,
                'task_id'         => $e->task_id,
                'task_title'      => $e->task?->title,
                'project_name'    => $e->task?->project?->name,
                'hours'           => (float) $e->hours,
                'description'     => $e->description,
                'logged_date'     => $e->logged_date,
                'billable'        => $e->billable ?? true,
                'billing_status'  => $e->billing_status ?? 'unbilled',
                'timer_started_at'=> $e->timer_started_at?->toISOString(),
            ]);

        $totalHours    = $entries->sum('hours');
        $billableHours = $entries->where('billable', true)->sum('hours');
        $utilization   = $totalHours > 0 ? round(($billableHours / $totalHours) * 100) : 0;

        $teamMembers = [];
        if ($isCeo) {
            $teamMembers = User::where('organization_id', $orgId)
                ->where('is_active', true)
                ->select('id', 'name')
                ->orderBy('name')
                ->get();
        }

        $tasks = Task::where('organization_id', $orgId)
            ->whereIn('status', ['todo', 'in_progress', 'in_review'])
            ->select('id', 'title')
            ->orderBy('title')
            ->limit(200)
            ->get();

        return Inertia::render('Timesheets/Index', [
            'entries'       => $entries->values(),
            'weekStart'     => $weekStart->toDateString(),
            'weekEnd'       => $weekEnd->toDateString(),
            'totalHours'    => $totalHours,
            'billableHours' => $billableHours,
            'utilization'   => $utilization,
            'teamMembers'   => $teamMembers,
            'tasks'         => $tasks,
            'viewUserId'    => $viewUser,
            'isCeo'         => $isCeo,
        ]);
    }

    public function startTimer(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'task_id'     => 'required|uuid',
            'description' => 'nullable|string|max:500',
            'billable'    => 'boolean',
        ]);

        TimeEntry::create([
            'organization_id'  => $request->user()->organization_id,
            'user_id'          => $request->user()->id,
            'task_id'          => $validated['task_id'],
            'project_id'       => Task::find($validated['task_id'])?->project_id,
            'hours'            => 0,
            'description'      => $validated['description'] ?? null,
            'logged_date'      => now()->toDateString(),
            'billable'         => $validated['billable'] ?? true,
            'billing_status'   => 'unbilled',
            'timer_started_at' => now(),
        ]);

        return back()->with('success', 'Timer started.');
    }

    public function stopTimer(Request $request, TimeEntry $timeEntry): RedirectResponse
    {
        abort_if($timeEntry->user_id !== $request->user()->id, 403);
        abort_if(!$timeEntry->timer_started_at, 422);

        $minutes = now()->diffInMinutes($timeEntry->timer_started_at);
        $hours   = round($minutes / 60, 2);

        $timeEntry->update([
            'hours'            => max(0.25, $hours),
            'timer_started_at' => null,
        ]);

        $timeEntry->task?->increment('actual_hours', $timeEntry->hours);

        return back()->with('success', 'Timer stopped. ' . number_format($hours, 2) . 'h logged.');
    }
}

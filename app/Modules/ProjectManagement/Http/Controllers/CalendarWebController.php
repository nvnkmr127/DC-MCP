<?php

namespace App\Modules\ProjectManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use App\Modules\ProjectManagement\Models\Task;
use App\Modules\ProjectManagement\Models\Milestone;

class CalendarWebController extends Controller
{
    public function index(Request $request)
    {
        $year  = (int) $request->get('year',  now()->year);
        $month = (int) $request->get('month', now()->month);

        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        // Tasks due in this month
        $tasks = Task::with(['project:id,name', 'assignee:id,name'])
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])
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
                'assignee' => $t->assignee ? ['name' => $t->assignee->name] : null,
                'url'      => '/tasks/' . $t->id,
            ]);

        // Milestones in this month
        $milestones = Milestone::with('project:id,name')
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])
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

        $events = $tasks->concat($milestones)->sortBy('date')->values();

        return Inertia::render('Calendar/Index', [
            'events'    => $events,
            'year'      => $year,
            'month'     => $month,
        ]);
    }
}

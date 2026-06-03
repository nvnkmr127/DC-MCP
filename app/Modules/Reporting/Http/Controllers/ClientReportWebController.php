<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ProjectManagement\Models\Client;
use App\Modules\ProjectManagement\Models\Task;
use App\Modules\ProjectManagement\Models\TimeEntry;
use App\Modules\Revenue\Models\ClientReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClientReportWebController extends Controller
{
    public function index(Request $request): Response
    {
        $orgId = $request->user()->organization_id;

        $reports = ClientReport::where('organization_id', $orgId)
            ->with('client:id,name,company')
            ->orderByDesc('month_year')
            ->get()
            ->map(fn($r) => [
                'id'         => $r->id,
                'month_year' => $r->month_year,
                'status'     => $r->status,
                'highlights' => $r->highlights,
                'challenges' => $r->challenges,
                'metrics'    => $r->metrics ?? [],
                'client'     => $r->client ? ['id' => $r->client->id, 'name' => $r->client->company ?? $r->client->name] : null,
            ]);

        $clients = Client::where('organization_id', $orgId)->select('id', 'name', 'company')->get();

        return Inertia::render('ClientReports/Index', [
            'reports' => $reports,
            'clients' => $clients,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'client_id'  => 'required|uuid|exists:clients,id',
            'month_year' => 'required|string|regex:/^\d{4}-\d{2}$/',
            'highlights' => 'nullable|string',
            'challenges' => 'nullable|string',
            'metrics'    => 'nullable|array',
        ]);

        ClientReport::create([
            'organization_id' => $request->user()->organization_id,
            'author_id'       => $request->user()->id,
            ...$validated,
        ]);

        return back()->with('success', 'Report created.');
    }

    public function update(Request $request, ClientReport $report): RedirectResponse
    {
        $this->authorizeOrg($report);

        $validated = $request->validate([
            'highlights' => 'nullable|string',
            'challenges' => 'nullable|string',
            'metrics'    => 'nullable|array',
        ]);

        $report->update($validated);
        return back()->with('success', 'Report updated.');
    }

    public function destroy(Request $request, ClientReport $report): RedirectResponse
    {
        $this->authorizeOrg($report);
        $report->delete();
        return back()->with('success', 'Report deleted.');
    }

    public function markSent(Request $request, ClientReport $report): RedirectResponse
    {
        $this->authorizeOrg($report);
        $report->update(['status' => 'sent']);
        return back()->with('success', 'Report marked as sent.');
    }

    public function generateDraft(Request $request, ClientReport $report): RedirectResponse
    {
        $this->authorizeOrg($report);

        [$year, $month] = explode('-', $report->month_year);

        $projects = \App\Modules\ProjectManagement\Models\Project::where('client_id', $report->client_id)
            ->pluck('id');

        $tasksCompleted = Task::whereIn('project_id', $projects)
            ->where('status', 'done')
            ->whereYear('updated_at', $year)
            ->whereMonth('updated_at', $month)
            ->count();

        $hoursLogged = TimeEntry::whereIn('project_id', $projects)
            ->whereYear('logged_date', $year)
            ->whereMonth('logged_date', $month)
            ->sum('hours');

        $issuesResolved = \App\Modules\ProjectManagement\Models\Issue::where('client_id', $report->client_id)
            ->where('status', 'resolved')
            ->whereYear('resolved_at', $year)
            ->whereMonth('resolved_at', $month)
            ->pluck('title')
            ->take(5)
            ->toArray();

        $highlights = "Completed {$tasksCompleted} tasks, logged " . round($hoursLogged, 1) . " hours.";
        if (!empty($issuesResolved)) {
            $highlights .= " Key issues resolved: " . implode(', ', $issuesResolved) . ".";
        }

        $report->update(['highlights' => $highlights]);

        return back()->with('success', 'Draft generated.');
    }
}

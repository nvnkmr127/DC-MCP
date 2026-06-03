<?php

namespace App\Modules\ProjectManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\ProjectManagement\Models\Client;
use App\Modules\ProjectManagement\Models\Issue;
use App\Modules\ProjectManagement\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IssueWebController extends Controller
{
    public function index(Request $request): Response
    {
        $orgId = $request->user()->organization_id;

        $query = Issue::where('organization_id', $orgId)
            ->with(['client:id,name,company', 'assignee:id,name']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $issues = $query->orderByDesc('created_at')->get()->map(fn($i) => [
            'id'          => $i->id,
            'title'       => $i->title,
            'description' => $i->description,
            'type'        => $i->type,
            'priority'    => $i->priority,
            'status'      => $i->status,
            'source'      => $i->source,
            'resolution'  => $i->resolution,
            'resolved_at' => $i->resolved_at?->toDateString(),
            'created_at'  => $i->created_at->toISOString(),
            'task_id'     => $i->task_id,
            'client'      => $i->client ? ['id' => $i->client->id, 'name' => $i->client->company ?? $i->client->name] : null,
            'assignee'    => $i->assignee ? ['id' => $i->assignee->id, 'name' => $i->assignee->name] : null,
        ]);

        $clients = Client::where('organization_id', $orgId)->select('id', 'name', 'company')->get();
        $users   = User::where('organization_id', $orgId)->select('id', 'name')->get();

        return Inertia::render('Issues/Index', [
            'issues'  => $issues,
            'clients' => $clients,
            'users'   => $users,
            'filters' => $request->only(['status', 'priority', 'type']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'type'        => 'required|in:bug,enhancement,question,feedback',
            'priority'    => 'required|in:low,medium,high,critical',
            'client_id'   => 'nullable|uuid|exists:clients,id',
            'project_id'  => 'nullable|uuid|exists:projects,id',
            'assigned_to' => 'nullable|uuid|exists:users,id',
            'source'      => 'nullable|in:internal,client_portal,email,call',
        ]);

        Issue::create([
            'organization_id' => $request->user()->organization_id,
            'reported_by'     => $request->user()->id,
            ...$validated,
        ]);

        return back()->with('success', 'Issue reported.');
    }

    public function update(Request $request, Issue $issue): RedirectResponse
    {
        $this->authorizeOrg($issue);

        $validated = $request->validate([
            'title'       => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'status'      => 'sometimes|in:open,in_progress,resolved,closed',
            'priority'    => 'sometimes|in:low,medium,high,critical',
            'assigned_to' => 'nullable|uuid|exists:users,id',
            'resolution'  => 'nullable|string',
        ]);

        if (isset($validated['status']) && $validated['status'] === 'resolved' && !$issue->resolved_at) {
            $validated['resolved_at'] = now();
        }

        $issue->update($validated);
        return back()->with('success', 'Issue updated.');
    }

    public function destroy(Request $request, Issue $issue): RedirectResponse
    {
        $this->authorizeOrg($issue);
        $issue->delete();
        return back()->with('success', 'Issue deleted.');
    }

    public function convertToTask(Request $request, Issue $issue): RedirectResponse
    {
        $this->authorizeOrg($issue);

        $validated = $request->validate([
            'project_id' => 'required|uuid|exists:projects,id',
        ]);

        $task = Task::create([
            'organization_id' => $request->user()->organization_id,
            'created_by'      => $request->user()->id,
            'project_id'      => $validated['project_id'],
            'title'           => $issue->title,
            'description'     => $issue->description,
            'priority'        => $issue->priority === 'critical' ? 'urgent' : $issue->priority,
            'status'          => 'todo',
        ]);

        $issue->update(['task_id' => $task->id]);

        return back()->with('success', 'Issue converted to task.');
    }
}

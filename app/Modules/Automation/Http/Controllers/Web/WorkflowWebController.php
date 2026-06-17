<?php

namespace App\Modules\Automation\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Automation\Models\WorkflowTrigger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkflowWebController extends Controller
{
    public function index(Request $request): Response
    {
        $orgId = $request->user()->organization_id;

        $workflows = WorkflowTrigger::where('organization_id', $orgId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($w) => [
                'id'            => $w->id,
                'name'          => $w->name,
                'description'   => $w->description,
                'trigger_event' => $w->trigger_event,
                'conditions'    => $w->conditions ?? [],
                'action_type'   => $w->action_type,
                'action_config' => $w->action_config ?? [],
                'is_active'     => $w->is_active,
                'run_count'     => $w->run_count,
                'last_run_at'   => $w->last_run_at?->toDateString(),
            ]);

        return Inertia::render('Workflows/Index', [
            'workflows' => $workflows,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'description'   => 'nullable|string',
            'trigger_event' => 'required|in:task_completed,invoice_sent,project_created,client_added,retainer_renewed,proposal_accepted',
            'action_type'   => 'required|in:send_notification,create_task,send_email,update_status',
            'conditions'    => 'nullable|array',
            'action_config' => 'nullable|array',
            'is_active'     => 'boolean',
        ]);

        WorkflowTrigger::create([
            'organization_id' => $request->user()->organization_id,
            ...$validated,
        ]);

        return back()->with('success', 'Workflow created.');
    }

    public function update(Request $request, WorkflowTrigger $workflow): RedirectResponse
    {
        $this->authorizeOrg($workflow);

        $validated = $request->validate([
            'name'          => 'sometimes|string|max:255',
            'description'   => 'nullable|string',
            'trigger_event' => 'sometimes|in:task_completed,invoice_sent,project_created,client_added,retainer_renewed,proposal_accepted',
            'action_type'   => 'sometimes|in:send_notification,create_task,send_email,update_status',
            'conditions'    => 'nullable|array',
            'action_config' => 'nullable|array',
            'is_active'     => 'sometimes|boolean',
        ]);

        $workflow->update($validated);
        return back()->with('success', 'Workflow updated.');
    }

    public function destroy(Request $request, WorkflowTrigger $workflow): RedirectResponse
    {
        $this->authorizeOrg($workflow);
        $workflow->delete();
        return back()->with('success', 'Workflow deleted.');
    }

    public function toggleActive(Request $request, WorkflowTrigger $workflow): RedirectResponse
    {
        $this->authorizeOrg($workflow);
        $workflow->update(['is_active' => !$workflow->is_active]);
        return back()->with('success', $workflow->is_active ? 'Workflow activated.' : 'Workflow deactivated.');
    }
}

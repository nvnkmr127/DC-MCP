<?php

namespace App\Modules\ProjectManagement\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\ProjectManagement\Models\AuditChecklist;
use App\Modules\ProjectManagement\Models\Client;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\ProjectManagement\Models\AssetApproval;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AuditChecklistWebController extends Controller
{
    public function index(Request $request): Response
    {
        $orgId = $request->user()->organization_id;

        $query = AuditChecklist::where('organization_id', $orgId)->with(['client:id,name,company', 'project:id,name', 'assetApproval:id,title']);

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $checklists = $query->orderByDesc('created_at')->get()->map(fn($c) => [
            'id'          => $c->id,
            'title'       => $c->title,
            'type'        => $c->type,
            'status'      => $c->status,
            'due_date'    => $c->due_date?->toDateString(),
            'items'       => $c->items ?? [],
            'client'      => $c->client ? ['id' => $c->client->id, 'name' => $c->client->company ?? $c->client->name] : null,
            'project'     => $c->project ? ['id' => $c->project->id, 'name' => $c->project->name] : null,
            'asset_approval' => $c->assetApproval ? ['id' => $c->assetApproval->id, 'title' => $c->assetApproval->title] : null,
        ]);

        $clients = Client::where('organization_id', $orgId)->select('id', 'name', 'company')->get();
        $projects = Project::where('organization_id', $orgId)->select('id', 'name')->get();
        $assetApprovals = AssetApproval::where('organization_id', $orgId)->select('id', 'title')->get();
        $users   = User::where('organization_id', $orgId)->select('id', 'name')->get();

        return Inertia::render('AuditChecklists/Index', [
            'checklists' => $checklists,
            'clients'    => $clients,
            'projects'   => $projects,
            'assetApprovals' => $assetApprovals,
            'users'      => $users,
            'filters'    => $request->only(['type', 'status']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $orgId = $request->user()->organization_id;
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'type'        => 'required|in:seo,social,ads,content,website,general',
            'client_id'   => [
                'nullable',
                'uuid',
                Rule::exists('clients', 'id')
                    ->where('organization_id', $orgId)
                    ->whereNull('deleted_at'),
            ],
            'project_id'   => [
                'nullable',
                'uuid',
                Rule::exists('projects', 'id')->where('organization_id', $orgId)->whereNull('deleted_at'),
            ],
            'asset_approval_id' => [
                'nullable',
                'uuid',
                Rule::exists('asset_approvals', 'id')->where('organization_id', $orgId)->whereNull('deleted_at'),
            ],
            'assigned_to' => [
                'nullable',
                'uuid',
                Rule::exists('users', 'id')->where('organization_id', $orgId)->whereNull('deleted_at'),
            ],
            'due_date'    => 'nullable|date',
            'items'       => 'nullable|array',
            'items.*.label' => 'required|string|max:255',
        ]);

        $items = collect($validated['items'] ?? [])->map(fn($item, $i) => [
            'id'      => (string) ($i + 1),
            'label'   => $item['label'],
            'checked' => false,
            'notes'   => '',
        ])->values()->all();

        AuditChecklist::create([
            'organization_id' => $request->user()->organization_id,
            'items'           => $items,
            'title'           => $validated['title'],
            'type'            => $validated['type'],
            'client_id'       => $validated['client_id'] ?? null,
            'project_id'      => $validated['project_id'] ?? null,
            'asset_approval_id'=> $validated['asset_approval_id'] ?? null,
            'assigned_to'     => $validated['assigned_to'] ?? null,
            'due_date'        => $validated['due_date'] ?? null,
        ]);

        return back()->with('success', 'Checklist created.');
    }

    public function update(Request $request, AuditChecklist $checklist): RedirectResponse
    {
        $this->authorizeOrg($checklist);

        $validated = $request->validate([
            'title'          => 'sometimes|string|max:255',
            'status'         => 'sometimes|in:in_progress,completed',
            'due_date'       => 'nullable|date',
            'items'          => 'nullable|array',
            'items.*.label'  => 'sometimes|string|max:255',
            'items.*.checked'=> 'sometimes|boolean',
            'items.*.notes'  => 'nullable|string|max:500',
        ]);

        $checklist->update($validated);
        return back()->with('success', 'Checklist updated.');
    }

    public function destroy(Request $request, AuditChecklist $checklist): RedirectResponse
    {
        $this->authorizeOrg($checklist);
        $checklist->delete();
        return back()->with('success', 'Checklist deleted.');
    }
}

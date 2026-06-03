<?php

namespace App\Modules\ProjectManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\ProjectManagement\Models\AuditChecklist;
use App\Modules\ProjectManagement\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditChecklistWebController extends Controller
{
    public function index(Request $request): Response
    {
        $orgId = $request->user()->organization_id;

        $query = AuditChecklist::where('organization_id', $orgId)->with('client:id,name,company');

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
        ]);

        $clients = Client::where('organization_id', $orgId)->select('id', 'name', 'company')->get();
        $users   = User::where('organization_id', $orgId)->select('id', 'name')->get();

        return Inertia::render('AuditChecklists/Index', [
            'checklists' => $checklists,
            'clients'    => $clients,
            'users'      => $users,
            'filters'    => $request->only(['type', 'status']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'type'        => 'required|in:seo,social,ads,content,website,general',
            'client_id'   => 'nullable|uuid|exists:clients,id',
            'assigned_to' => 'nullable|uuid|exists:users,id',
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

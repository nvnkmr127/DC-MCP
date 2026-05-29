<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\ProjectManagement\Models\AssetApproval;
use App\Modules\ProjectManagement\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssetApprovalController extends Controller
{
    public function index(Request $request): Response
    {
        $orgId = $request->user()->organization_id;

        $query = AssetApproval::where('organization_id', $orgId)
            ->with(['client:id,name,company', 'submitter:id,name']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        $approvals = $query->orderByDesc('created_at')->get()->map(fn($a) => [
            'id'          => $a->id,
            'title'       => $a->title,
            'description' => $a->description,
            'type'        => $a->type,
            'asset_url'   => $a->asset_url,
            'feedback'    => $a->feedback,
            'version'     => $a->version,
            'status'      => $a->status,
            'reviewed_at' => $a->reviewed_at?->toDateString(),
            'created_at'  => $a->created_at->toISOString(),
            'client'      => $a->client ? ['id' => $a->client->id, 'name' => $a->client->company ?? $a->client->name] : null,
            'submitter'   => $a->submitter ? ['id' => $a->submitter->id, 'name' => $a->submitter->name] : null,
        ]);

        $clients = Client::where('organization_id', $orgId)->select('id', 'name', 'company')->get();

        return Inertia::render('AssetApprovals/Index', [
            'approvals' => $approvals,
            'clients'   => $clients,
            'filters'   => $request->only(['status', 'client_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'type'        => 'required|in:social_post,ad_creative,blog,video,email,other',
            'client_id'   => 'required|uuid|exists:clients,id',
            'asset_url'   => 'nullable|string',
        ]);

        AssetApproval::create([
            'organization_id' => $request->user()->organization_id,
            'submitted_by'    => $request->user()->id,
            ...$validated,
        ]);

        return back()->with('success', 'Asset submitted for approval.');
    }

    public function update(Request $request, AssetApproval $approval): RedirectResponse
    {
        abort_if($approval->organization_id !== $request->user()->organization_id, 403);

        $validated = $request->validate([
            'status'   => 'sometimes|in:pending,approved,revision_requested,rejected',
            'feedback' => 'nullable|string',
        ]);

        $updates = $validated;

        if (isset($validated['status']) && $validated['status'] !== $approval->status) {
            $updates['reviewed_by']  = $request->user()->id;
            $updates['reviewed_at']  = now();
            if ($validated['status'] === 'revision_requested') {
                $updates['version'] = $approval->version + 1;
            }
        }

        $approval->update($updates);
        return back()->with('success', 'Approval updated.');
    }

    public function destroy(Request $request, AssetApproval $approval): RedirectResponse
    {
        abort_if($approval->organization_id !== $request->user()->organization_id, 403);
        $approval->delete();
        return back()->with('success', 'Asset deleted.');
    }
}

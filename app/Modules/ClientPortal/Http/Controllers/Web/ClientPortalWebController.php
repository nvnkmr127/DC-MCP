<?php

namespace App\Modules\ClientPortal\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\ClientPortal\Models\PortalRequest;
use App\Modules\ClientPortal\Models\PortalShare;
use App\Modules\ClientPortal\Models\PortalUser;
use App\Modules\ClientPortal\Services\PortalService;
use App\Modules\ProjectManagement\Models\Client;
use App\Modules\ProjectManagement\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ClientPortalWebController extends Controller
{
    public function __construct(private PortalService $portalService) {}

    // ─── CEO Management Views ────────────────────────────────────────────────

    /**
     * CEO's portal management dashboard — list all clients with portal access.
     */
    public function manage(Request $request): Response
    {
        $orgId = $request->user()->organization_id;

        $clients = Client::where('organization_id', $orgId)
            ->where('status', 'active')
            ->with([
                'portalUsers' => fn($q) => $q->where('is_active', true),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn($c) => [
                'id'           => $c->id,
                'name'         => $c->name,
                'company'      => $c->company,
                'portal_users' => $c->portalUsers->map(fn($u) => [
                    'id'            => $u->id,
                    'name'          => $u->name,
                    'email'         => $u->email,
                    'is_active'     => $u->is_active,
                    'last_login_at' => $u->last_login_at?->toISOString(),
                ]),
                'pending_requests' => PortalRequest::where('organization_id', $orgId)
                    ->where('client_id', $c->id)
                    ->where('status', 'open')
                    ->count(),
            ]);

        $pendingRequests = PortalRequest::where('organization_id', $orgId)
            ->where('status', 'open')
            ->with(['client:id,name,company', 'portalUser:id,name,email'])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn($r) => $this->formatRequest($r));

        return Inertia::render('Settings/ClientPortal', [
            'clients'         => $clients,
            'pendingRequests' => $pendingRequests,
        ]);
    }

    /**
     * Show a client's portal configuration (shares, users, requests).
     */
    public function showClient(Request $request, Client $client): Response
    {
        $this->authorizeClient($client, $request);
        $orgId = $request->user()->organization_id;

        $portalUsers = PortalUser::where('organization_id', $orgId)
            ->where('client_id', $client->id)
            ->get()
            ->map(fn($u) => [
                'id'            => $u->id,
                'name'          => $u->name,
                'email'         => $u->email,
                'is_active'     => $u->is_active,
                'last_login_at' => $u->last_login_at?->toISOString(),
                'permissions'   => $u->permissions,
            ]);

        $shares = $this->portalService->getClientShares($orgId, $client->id)
            ->map(fn($s) => [
                'id'             => $s->id,
                'shareable_type' => $s->shareable_type,
                'shareable_id'   => $s->shareable_id,
                'note'           => $s->note,
                'shared_at'      => $s->shared_at->toISOString(),
                'expires_at'     => $s->expires_at?->toISOString(),
                'shared_by'      => $s->sharedBy ? ['name' => $s->sharedBy->name] : null,
            ]);

        $requests = PortalRequest::where('organization_id', $orgId)
            ->where('client_id', $client->id)
            ->with(['portalUser:id,name,email'])
            ->orderByDesc('created_at')
            ->paginate(20)
            ->through(fn($r) => $this->formatRequest($r));

        return Inertia::render('Settings/ClientPortalDetail', [
            'client'      => ['id' => $client->id, 'name' => $client->name, 'company' => $client->company],
            'portalUsers' => $portalUsers,
            'shares'      => $shares,
            'requests'    => $requests,
        ]);
    }

    /**
     * Invite a client contact to the portal.
     */
    public function inviteUser(Request $request, Client $client): RedirectResponse
    {
        $this->authorizeClient($client, $request);

        $validated = $request->validate([
            'name'        => 'required|string|max:100',
            'email'       => 'required|email|max:150',
            'permissions' => 'nullable|array',
        ]);

        $portalUser = $this->portalService->invitePortalUser($client, $validated, $request->user());

        return back()->with('success', "Portal invite sent to {$portalUser->email}.");
    }

    /**
     * Resend magic link to a portal user.
     */
    public function resendInvite(Request $request, PortalUser $portalUser): RedirectResponse
    {
        if ($portalUser->organization_id !== $request->user()->organization_id) {
            abort(403);
        }

        $this->portalService->sendMagicLink($portalUser);
        return back()->with('success', "Magic link resent to {$portalUser->email}.");
    }

    /**
     * Toggle portal user active status.
     */
    public function toggleUser(Request $request, PortalUser $portalUser): RedirectResponse
    {
        if ($portalUser->organization_id !== $request->user()->organization_id) {
            abort(403);
        }

        $portalUser->update(['is_active' => !$portalUser->is_active]);
        $status = $portalUser->is_active ? 'enabled' : 'disabled';
        return back()->with('success', "Portal access {$status} for {$portalUser->name}.");
    }

    /**
     * Share a report / project / other item with a client (CEO-gated).
     */
    public function share(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'client_id'      => 'required|uuid',
            'shareable_type' => 'required|string|in:report,project,content_item',
            'shareable_id'   => 'required|uuid',
            'note'           => 'nullable|string|max:500',
            'expires_at'     => 'nullable|date',
        ]);

        $orgId = $request->user()->organization_id;
        $client = Client::where('organization_id', $orgId)->findOrFail($validated['client_id']);

        $this->portalService->shareWithClient(
            $orgId,
            $client->id,
            $validated['shareable_type'],
            $validated['shareable_id'],
            $request->user(),
            $validated['note'] ?? null,
            isset($validated['expires_at']) ? new \DateTime($validated['expires_at']) : null,
        );

        return back()->with('success', 'Item shared with client portal.');
    }

    /**
     * Revoke a portal share.
     */
    public function revokeShare(Request $request, PortalShare $portalShare): RedirectResponse
    {
        if ($portalShare->organization_id !== $request->user()->organization_id) {
            abort(403);
        }

        $this->portalService->revokeShare($portalShare);
        return back()->with('success', 'Share revoked.');
    }

    /**
     * Convert a client portal request into a task.
     */
    public function convertRequestToTask(Request $request, PortalRequest $portalRequest): RedirectResponse
    {
        if ($portalRequest->organization_id !== $request->user()->organization_id) {
            abort(403);
        }

        if ($portalRequest->task_id) {
            return back()->with('error', 'Request already linked to a task.');
        }

        $task = Task::create([
            'organization_id' => $portalRequest->organization_id,
            'title'           => $portalRequest->title,
            'description'     => $portalRequest->description,
            'type'            => 'task',
            'status'          => 'todo',
            'priority'        => $portalRequest->priority,
            'created_by'      => $request->user()->id,
            'tags'            => ['client-request'],
            'meta'            => ['portal_request_id' => $portalRequest->id, 'client_id' => $portalRequest->client_id],
        ]);

        $portalRequest->update([
            'task_id'     => $task->id,
            'status'      => 'actioned',
            'actioned_by' => $request->user()->id,
            'actioned_at' => now(),
        ]);

        return back()->with('success', "Task created from client request: \"{$task->title}\".");
    }

    /**
     * Close a portal request without creating a task.
     */
    public function closeRequest(Request $request, PortalRequest $portalRequest): RedirectResponse
    {
        if ($portalRequest->organization_id !== $request->user()->organization_id) {
            abort(403);
        }

        $portalRequest->update([
            'status'      => 'closed',
            'actioned_by' => $request->user()->id,
            'actioned_at' => now(),
        ]);

        return back()->with('success', 'Request closed.');
    }

    private function formatRequest(PortalRequest $r): array
    {
        return [
            'id'          => $r->id,
            'title'       => $r->title,
            'description' => $r->description,
            'type'        => $r->type,
            'status'      => $r->status,
            'priority'    => $r->priority,
            'created_at'  => $r->created_at->toISOString(),
            'task_id'     => $r->task_id,
            'client'      => $r->client ? ['id' => $r->client->id, 'name' => $r->client->company ?? $r->client->name] : null,
            'portal_user' => $r->portalUser ? ['name' => $r->portalUser->name, 'email' => $r->portalUser->email] : null,
        ];
    }

    private function authorizeClient(Client $client, Request $request): void
    {
        if ($client->organization_id !== $request->user()->organization_id) {
            abort(403);
        }
    }
}

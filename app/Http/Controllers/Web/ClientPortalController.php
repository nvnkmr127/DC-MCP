<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\ClientPortal\Models\ClientPortalUser;
use App\Modules\ClientPortal\Models\ClientPortalRequest;
use App\Modules\ClientPortal\Models\ClientPortalShare;
use App\Modules\ProjectManagement\Models\Client;
use App\Modules\ProjectManagement\Models\Task;
use App\Modules\ProjectManagement\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ClientPortalController extends Controller
{
    public function manage(Request $request): Response
    {
        $orgId = $request->user()->organization_id;

        $portalUsers = ClientPortalUser::where('organization_id', $orgId)
            ->with('client:id,name,company')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($u) => [
                'id'             => $u->id,
                'name'           => $u->name,
                'email'          => $u->email,
                'is_active'      => $u->is_active,
                'last_login_at'  => $u->last_login_at?->toISOString(),
                'invite_sent_at' => $u->invite_sent_at?->toISOString(),
                'client'         => $u->client ? ['id' => $u->client->id, 'name' => $u->client->company ?? $u->client->name] : null,
            ]);

        $requests = ClientPortalRequest::where('organization_id', $orgId)
            ->with(['client:id,name,company', 'portalUser:id,name,email'])
            ->whereIn('status', ['open', 'in_progress'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($r) => [
                'id'          => $r->id,
                'title'       => $r->title,
                'description' => $r->description,
                'status'      => $r->status,
                'created_at'  => $r->created_at->toISOString(),
                'client'      => $r->client ? ['id' => $r->client->id, 'name' => $r->client->company ?? $r->client->name] : null,
                'portal_user' => $r->portalUser ? ['name' => $r->portalUser->name, 'email' => $r->portalUser->email] : null,
            ]);

        $clients = Client::where('organization_id', $orgId)
            ->where('status', 'active')
            ->select('id', 'name', 'company')
            ->orderBy('name')
            ->get();

        return Inertia::render('Settings/ClientPortal', [
            'portalUsers' => $portalUsers,
            'requests'    => $requests,
            'clients'     => $clients,
        ]);
    }

    public function showClient(Request $request, Client $client): Response
    {
        abort_if($client->organization_id !== $request->user()->organization_id, 403);

        $users = ClientPortalUser::where('client_id', $client->id)
            ->orderByDesc('created_at')->get()
            ->map(fn($u) => [
                'id'             => $u->id,
                'name'           => $u->name,
                'email'          => $u->email,
                'is_active'      => $u->is_active,
                'last_login_at'  => $u->last_login_at?->toISOString(),
                'invite_sent_at' => $u->invite_sent_at?->toISOString(),
            ]);

        $shares = ClientPortalShare::where('client_id', $client->id)
            ->orderByDesc('created_at')->get()
            ->map(fn($s) => [
                'id'             => $s->id,
                'shareable_type' => $s->shareable_type,
                'shareable_id'   => $s->shareable_id,
                'permissions'    => $s->permissions,
                'expires_at'     => $s->expires_at?->toISOString(),
            ]);

        $projects = Project::where('client_id', $client->id)->select('id', 'name')->get();

        return Inertia::render('Settings/ClientPortalClient', [
            'client'   => ['id' => $client->id, 'name' => $client->company ?? $client->name],
            'users'    => $users,
            'shares'   => $shares,
            'projects' => $projects,
        ]);
    }

    public function inviteUser(Request $request, Client $client): RedirectResponse
    {
        abort_if($client->organization_id !== $request->user()->organization_id, 403);

        $data = $request->validate([
            'name'  => 'required|string|max:120',
            'email' => 'required|email|max:255',
        ]);

        $token = Str::random(64);

        ClientPortalUser::create([
            'organization_id'  => $request->user()->organization_id,
            'client_id'        => $client->id,
            'name'             => $data['name'],
            'email'            => $data['email'],
            'invite_token'     => $token,
            'invite_expires_at'=> now()->addDays(7),
            'invite_sent_at'   => now(),
        ]);

        return back()->with('success', "Invite sent to {$data['email']}.");
    }

    public function resendInvite(Request $request, ClientPortalUser $portalUser): RedirectResponse
    {
        abort_if($portalUser->organization_id !== $request->user()->organization_id, 403);

        $portalUser->update([
            'invite_token'     => Str::random(64),
            'invite_expires_at'=> now()->addDays(7),
            'invite_sent_at'   => now(),
        ]);

        return back()->with('success', 'Invite resent.');
    }

    public function toggleUser(Request $request, ClientPortalUser $portalUser): RedirectResponse
    {
        abort_if($portalUser->organization_id !== $request->user()->organization_id, 403);
        $portalUser->update(['is_active' => !$portalUser->is_active]);
        return back()->with('success', 'Access updated.');
    }

    public function share(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'client_id'      => 'required|uuid',
            'shareable_type' => 'required|in:project,task,invoice',
            'shareable_id'   => 'required|uuid',
            'permissions'    => 'nullable|array',
            'expires_at'     => 'nullable|date',
        ]);

        ClientPortalShare::create([
            'organization_id' => $request->user()->organization_id,
            'shared_by'       => $request->user()->id,
            'client_id'       => $data['client_id'],
            'shareable_type'  => $data['shareable_type'],
            'shareable_id'    => $data['shareable_id'],
            'permissions'     => $data['permissions'] ?? ['view'],
            'expires_at'      => $data['expires_at'] ?? null,
        ]);

        return back()->with('success', 'Item shared with client portal.');
    }

    public function revokeShare(Request $request, ClientPortalShare $portalShare): RedirectResponse
    {
        abort_if($portalShare->organization_id !== $request->user()->organization_id, 403);
        $portalShare->delete();
        return back()->with('success', 'Share revoked.');
    }

    public function convertRequestToTask(Request $request, ClientPortalRequest $portalRequest): RedirectResponse
    {
        abort_if($portalRequest->organization_id !== $request->user()->organization_id, 403);

        $data = $request->validate([
            'project_id'  => 'nullable|uuid',
            'assigned_to' => 'nullable|uuid',
        ]);

        $task = Task::create([
            'organization_id' => $request->user()->organization_id,
            'title'           => $portalRequest->title,
            'description'     => $portalRequest->description,
            'status'          => 'todo',
            'priority'        => 'medium',
            'project_id'      => $data['project_id'] ?? null,
            'assigned_to'     => $data['assigned_to'] ?? null,
        ]);

        $portalRequest->update([
            'task_id' => $task->id,
            'status'  => 'converted',
        ]);

        return back()->with('success', 'Request converted to task.');
    }

    public function closeRequest(Request $request, ClientPortalRequest $portalRequest): RedirectResponse
    {
        abort_if($portalRequest->organization_id !== $request->user()->organization_id, 403);

        $portalRequest->update([
            'status'    => 'closed',
            'closed_at' => now(),
        ]);

        return back()->with('success', 'Request closed.');
    }
}

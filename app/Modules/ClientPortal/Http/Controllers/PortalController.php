<?php

namespace App\Modules\ClientPortal\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ClientPortal\Models\PortalRequest;
use App\Modules\ClientPortal\Models\PortalShare;
use App\Modules\ClientPortal\Models\PortalUser;
use App\Modules\ClientPortal\Services\PortalService;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\ProjectManagement\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortalController extends Controller
{
    public function __construct(private PortalService $portalService) {}

    // ─── Auth ────────────────────────────────────────────────────────────────

    public function showLogin(): Response
    {
        return Inertia::render('Portal/Login');
    }

    public function handleMagicLink(string $token): \Illuminate\Http\Response|RedirectResponse
    {
        $portalUser = $this->portalService->authenticateByToken($token);

        if (!$portalUser) {
            return redirect('/portal/login')->withErrors(['token' => 'This link has expired or is invalid. Please request a new one.']);
        }

        session(['portal_user_id' => $portalUser->id, 'portal_org_id' => $portalUser->organization_id]);
        return redirect('/portal/dashboard');
    }

    public function logout(): RedirectResponse
    {
        session()->forget(['portal_user_id', 'portal_org_id']);
        return redirect('/portal/login');
    }

    // ─── Portal Dashboard ────────────────────────────────────────────────────

    public function dashboard(Request $request): Response|RedirectResponse
    {
        $portalUser = $this->getPortalUser();
        if (!$portalUser) {
            return redirect('/portal/login');
        }

        $orgId    = $portalUser->organization_id;
        $clientId = $portalUser->client_id;

        // Active projects shared with this client
        $projects = Project::where('organization_id', $orgId)
            ->where('client_id', $clientId)
            ->whereNotIn('status', ['cancelled', 'archived'])
            ->with(['tasks' => fn($q) => $q->whereNotIn('status', ['cancelled', 'done'])])
            ->get()
            ->map(fn($p) => [
                'id'          => $p->id,
                'name'        => $p->name,
                'status'      => $p->status,
                'end_date'    => $p->end_date?->toDateString(),
                'task_counts' => [
                    'total'       => $p->tasks->count(),
                    'done'        => Task::where('project_id', $p->id)->where('status', 'done')->count(),
                    'in_progress' => $p->tasks->where('status', 'in_progress')->count(),
                    'overdue'     => $p->tasks->filter(fn($t) => $t->due_date && $t->due_date->isPast())->count(),
                ],
            ]);

        // Shared items (reports, content) the CEO has shared
        $shares = $this->portalService->getClientShares($orgId, $clientId);

        $sharedReports = $shares->where('shareable_type', 'report')
            ->map(fn($s) => [
                'share_id'   => $s->id,
                'note'       => $s->note,
                'shared_at'  => $s->shared_at->toISOString(),
            ]);

        // Client's own requests
        $myRequests = PortalRequest::where('organization_id', $orgId)
            ->where('client_id', $clientId)
            ->where('portal_user_id', $portalUser->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn($r) => [
                'id'         => $r->id,
                'title'      => $r->title,
                'type'       => $r->type,
                'status'     => $r->status,
                'priority'   => $r->priority,
                'created_at' => $r->created_at->toISOString(),
            ]);

        return Inertia::render('Portal/Dashboard', [
            'portalUser'     => ['id' => $portalUser->id, 'name' => $portalUser->name, 'email' => $portalUser->email],
            'clientName'     => $portalUser->client->company ?? $portalUser->client->name ?? 'Client',
            'projects'       => $projects,
            'sharedReports'  => $sharedReports,
            'myRequests'     => $myRequests,
        ]);
    }

    // ─── Submit Request ──────────────────────────────────────────────────────

    public function submitRequest(Request $request): RedirectResponse
    {
        $portalUser = $this->getPortalUser();
        if (!$portalUser) {
            return redirect('/portal/login');
        }

        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'type'        => 'required|in:new_request,feedback,bug,question',
            'priority'    => 'nullable|in:low,medium,high',
        ]);

        PortalRequest::create([
            'organization_id' => $portalUser->organization_id,
            'client_id'       => $portalUser->client_id,
            'portal_user_id'  => $portalUser->id,
            'title'           => $validated['title'],
            'description'     => $validated['description'] ?? null,
            'type'            => $validated['type'],
            'status'          => 'open',
            'priority'        => $validated['priority'] ?? 'medium',
        ]);

        return back()->with('success', 'Your request has been submitted. We\'ll get back to you soon.');
    }

    private function getPortalUser(): ?PortalUser
    {
        $id = session('portal_user_id');
        if (!$id) {
            return null;
        }

        return PortalUser::where('id', $id)->where('is_active', true)->with('client')->first();
    }
}

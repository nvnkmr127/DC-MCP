<?php

namespace App\Modules\ClientPortal\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ClientPortal\Models\ClientPortalRequest;
use App\Modules\ClientPortal\Models\ClientPortalShare;
use App\Modules\ClientPortal\Models\ClientPortalUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortalController extends Controller
{
    public function showLogin(): Response
    {
        return Inertia::render('Portal/Login');
    }

    public function handleMagicLink(Request $request, string $token): RedirectResponse
    {
        $user = ClientPortalUser::where('invite_token', $token)
            ->where('invite_expires_at', '>', now())
            ->where('is_active', true)
            ->first();

        if (!$user) {
            return redirect('/portal/login')->withErrors(['token' => 'This link is invalid or has expired.']);
        }

        $user->update([
            'last_login_at' => now(),
            'invite_token'  => null,
        ]);

        session(['portal_user_id' => $user->id, 'portal_org_id' => $user->organization_id]);

        return redirect('/portal/dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget(['portal_user_id', 'portal_org_id']);
        return redirect('/portal/login');
    }

    public function dashboard(Request $request): Response|RedirectResponse
    {
        $portalUserId = session('portal_user_id');
        if (!$portalUserId) {
            return redirect('/portal/login');
        }

        $portalUser = ClientPortalUser::find($portalUserId);
        if (!$portalUser || !$portalUser->is_active) {
            $request->session()->forget(['portal_user_id', 'portal_org_id']);
            return redirect('/portal/login');
        }

        $shares = ClientPortalShare::where('client_id', $portalUser->client_id)
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($s) => [
                'id'             => $s->id,
                'shareable_type' => $s->shareable_type,
                'shareable_id'   => $s->shareable_id,
                'permissions'    => $s->permissions,
                'expires_at'     => $s->expires_at?->toISOString(),
            ]);

        $requests = ClientPortalRequest::where('portal_user_id', $portalUser->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($r) => [
                'id'          => $r->id,
                'title'       => $r->title,
                'description' => $r->description,
                'status'      => $r->status,
                'created_at'  => $r->created_at->toISOString(),
            ]);

        return Inertia::render('Portal/Dashboard', [
            'portalUser' => ['id' => $portalUser->id, 'name' => $portalUser->name, 'email' => $portalUser->email],
            'client'     => ['id' => $portalUser->client_id],
            'shares'     => $shares,
            'requests'   => $requests,
        ]);
    }

    public function submitRequest(Request $request): RedirectResponse
    {
        $portalUserId = session('portal_user_id');
        if (!$portalUserId) {
            return redirect('/portal/login');
        }

        $portalUser = ClientPortalUser::find($portalUserId);
        if (!$portalUser) {
            return redirect('/portal/login');
        }

        $data = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
        ]);

        ClientPortalRequest::create([
            'organization_id' => $portalUser->organization_id,
            'client_id'       => $portalUser->client_id,
            'portal_user_id'  => $portalUser->id,
            'title'           => $data['title'],
            'description'     => $data['description'] ?? null,
            'status'          => 'open',
        ]);

        return back()->with('success', 'Request submitted successfully.');
    }
}

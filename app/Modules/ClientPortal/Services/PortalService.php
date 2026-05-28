<?php

namespace App\Modules\ClientPortal\Services;

use App\Modules\ClientPortal\Models\PortalShare;
use App\Modules\ClientPortal\Models\PortalUser;
use App\Modules\ProjectManagement\Models\Client;
use App\Modules\Auth\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class PortalService
{
    /**
     * Create a portal user for a client contact and send magic link.
     */
    public function invitePortalUser(Client $client, array $data, User $invitedBy): PortalUser
    {
        $portalUser = PortalUser::updateOrCreate(
            [
                'organization_id' => $client->organization_id,
                'client_id'       => $client->id,
                'email'           => strtolower(trim($data['email'])),
            ],
            [
                'name'       => $data['name'],
                'is_active'  => true,
                'created_by' => $invitedBy->id,
                'permissions' => $data['permissions'] ?? [],
            ]
        );

        return $this->sendMagicLink($portalUser);
    }

    /**
     * Generate a magic login link for a portal user.
     */
    public function sendMagicLink(PortalUser $portalUser): PortalUser
    {
        $token = Str::random(48);

        $portalUser->update([
            'magic_token'            => $token,
            'magic_token_expires_at' => now()->addHours(24),
        ]);

        // Send email (fails silently if mail not configured)
        try {
            Mail::send([], [], function ($m) use ($portalUser, $token) {
                $url = url("/portal/auth/{$token}");
                $m->to($portalUser->email, $portalUser->name)
                  ->subject('Your Client Portal Access — Digicloudify')
                  ->html(
                      "<p>Hi {$portalUser->name},</p>" .
                      "<p>Click the link below to access your client portal (valid 24 hours):</p>" .
                      "<p><a href=\"{$url}\" style=\"background:#4f46e5;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none\">Access Portal</a></p>" .
                      "<p style=\"font-size:12px;color:#888\">{$url}</p>"
                  );
            });
        } catch (\Exception $e) {
            // Non-critical
        }

        return $portalUser;
    }

    /**
     * Authenticate a portal user via magic token.
     * Returns the portal user or null if token invalid/expired.
     */
    public function authenticateByToken(string $token): ?PortalUser
    {
        $portalUser = PortalUser::where('magic_token', $token)
            ->where('is_active', true)
            ->where('magic_token_expires_at', '>', now())
            ->first();

        if (!$portalUser) {
            return null;
        }

        $portalUser->update([
            'magic_token'            => null,
            'magic_token_expires_at' => null,
            'last_login_at'          => now(),
        ]);

        return $portalUser;
    }

    /**
     * Share an item with a client (CEO-gated — only team members call this).
     */
    public function shareWithClient(
        string $orgId,
        string $clientId,
        string $shareableType,
        string $shareableId,
        User $sharedBy,
        ?string $note = null,
        ?\DateTime $expiresAt = null
    ): PortalShare {
        return PortalShare::updateOrCreate(
            [
                'organization_id' => $orgId,
                'client_id'       => $clientId,
                'shareable_type'  => $shareableType,
                'shareable_id'    => $shareableId,
            ],
            [
                'shared_by'  => $sharedBy->id,
                'shared_at'  => now(),
                'expires_at' => $expiresAt,
                'is_active'  => true,
                'note'       => $note,
            ]
        );
    }

    /**
     * Revoke a portal share.
     */
    public function revokeShare(PortalShare $share): void
    {
        $share->update(['is_active' => false]);
    }

    /**
     * Get all active shares for a client (what they can see in portal).
     */
    public function getClientShares(string $orgId, string $clientId): \Illuminate\Database\Eloquent\Collection
    {
        return PortalShare::where('organization_id', $orgId)
            ->where('client_id', $clientId)
            ->where('is_active', true)
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->with('sharedBy:id,name')
            ->orderByDesc('shared_at')
            ->get();
    }
}

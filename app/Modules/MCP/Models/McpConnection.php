<?php

namespace App\Modules\MCP\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class McpConnection extends BaseModel
{
    use HasOrganization, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'mcp_connections';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'organization_id',
        'user_id',
        'provider',
        'name',
        'label',
        'status',
        'is_active',
        'credentials',
        'scopes',
        'last_synced_at',
        'sync_error',
        'settings',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'credentials' => 'array',
        'scopes' => 'array',
        'settings' => 'array',
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the user that set up this connection.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Auth\Models\User::class);
    }

    /**
     * Mark the connection as successfully synced.
     *
     * @return void
     */
    public function markSynced(): void
    {
        $this->update([
            'status' => 'active',
            'last_synced_at' => now(),
            'sync_error' => null,
        ]);
    }

    /**
     * Mark the connection with an error status and log the error message.
     *
     * @param string $message
     * @return void
     */
    public function markError(string $message): void
    {
        $this->update([
            'status' => 'error',
            'sync_error' => $message,
        ]);
    }

    /**
     * Get the decrypted access token. The only safe way to access tokens.
     *
     * @return string|null
     */
    public function getDecryptedAccessToken(): ?string
    {
        $credentials = $this->credentials ?? [];
        $token = $credentials['access_token'] ?? null;
        if (!$token) {
            return null;
        }
        try {
            return \Illuminate\Support\Facades\Crypt::decryptString($token);
        } catch (\Exception $e) {
            return $token; // already plain (legacy or test)
        }
    }

    /**
     * Get the decrypted refresh token.
     *
     * @return string|null
     */
    public function getDecryptedRefreshToken(): ?string
    {
        $credentials = $this->credentials ?? [];
        $token = $credentials['refresh_token'] ?? null;
        if (!$token) {
            return null;
        }
        try {
            return \Illuminate\Support\Facades\Crypt::decryptString($token);
        } catch (\Exception $e) {
            return $token;
        }
    }

    /**
     * Check if the OAuth token is expired.
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        $credentials = $this->credentials ?? [];
        if (!isset($credentials['expires_in']) || !isset($credentials['created'])) {
            return false;
        }

        $expiryTime = $credentials['created'] + $credentials['expires_in'];
        return time() >= $expiryTime;
    }
}

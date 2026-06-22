<?php

namespace App\Modules\MCP\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Modules\MCP\Enums\ConnectionStatus;

class McpConnection extends BaseModel
{
    use HasOrganization, SoftDeletes;

    protected static function booted()
    {
        parent::booted();
        static::saving(function ($connection) {
            if ($connection->isDirty('settings')) {
                $connection->settings = \App\Shared\Helpers\HtmlSanitizer::sanitize($connection->settings);
            }
        });
    }

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
        'status' => ConnectionStatus::class,
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'troubleshooting_guide',
        'last_error_message',
        'consecutive_failure_count',
        'uptime_percentage',
        'health_score',
        'sync_progress',
    ];

    public function getSyncProgressAttribute(): ?int
    {
        return \Illuminate\Support\Facades\Cache::get("mcp_sync_progress_{$this->id}");
    }

    public function updateSyncProgress(int $percentage): void
    {
        \Illuminate\Support\Facades\Cache::put("mcp_sync_progress_{$this->id}", min(100, max(0, $percentage)), 3600);
    }

    public function clearSyncProgress(): void
    {
        \Illuminate\Support\Facades\Cache::forget("mcp_sync_progress_{$this->id}");
    }

    public function pauseSync(): void
    {
        $settings = $this->settings ?? [];
        $settings['sync_paused'] = true;
        $this->update(['settings' => $settings]);
    }

    public function resumeSync(): void
    {
        $settings = $this->settings ?? [];
        $settings['sync_paused'] = false;
        $this->update(['settings' => $settings]);
    }

    public function isSyncPaused(): bool
    {
        // Read fresh from DB if inside a long-running job loop
        $fresh = static::where('id', $this->id)->value('settings');
        $settings = is_string($fresh) ? json_decode($fresh, true) : $fresh;
        return !empty($settings['sync_paused']);
    }

    public function cancelSync(): void
    {
        $settings = $this->settings ?? [];
        $settings['sync_cancelled'] = true;
        $this->update(['settings' => $settings]);
    }

    public function isSyncCancelled(): bool
    {
        $fresh = static::where('id', $this->id)->value('settings');
        $settings = is_string($fresh) ? json_decode($fresh, true) : $fresh;
        return !empty($settings['sync_cancelled']);
    }

    public function resetSyncCancellation(): void
    {
        $settings = $this->settings ?? [];
        unset($settings['sync_cancelled']);
        $this->update(['settings' => $settings]);
    }

    public function setLastSyncedRecordReference(string $recordId): void
    {
        $settings = $this->settings ?? [];
        $settings['last_synced_record_id'] = $recordId;
        $this->update(['settings' => $settings]);
    }

    public function getLastSyncedRecordReference(): ?string
    {
        return $this->settings['last_synced_record_id'] ?? null;
    }

    public function getLastErrorMessageAttribute(): ?string
    {
        return $this->sync_error;
    }

    public function getConsecutiveFailureCountAttribute(): int
    {
        return $this->settings['consecutive_failures'] ?? 0;
    }

    public function getUptimePercentageAttribute(): float
    {
        $total = $this->settings['total_syncs'] ?? 0;
        $success = $this->settings['successful_syncs'] ?? 0;
        if ($total === 0) return 100.0;
        return round(($success / $total) * 100, 2);
    }

    public function getHealthScoreAttribute(): int
    {
        if ($this->status === ConnectionStatus::ACTIVE) {
            return 100;
        }
        if ($this->status === ConnectionStatus::DEGRADED || $this->status === ConnectionStatus::RATE_LIMITED) {
            return max(0, 70 - ($this->getConsecutiveFailureCountAttribute() * 10));
        }
        return 0;
    }

    /**
     * Get troubleshooting guide based on current status.
     *
     * @return array
     */
    public function getTroubleshootingGuideAttribute(): array
    {
        return match ($this->status) {
            ConnectionStatus::TOKEN_EXPIRED => [
                'Your authentication token has expired.',
                'Click the "Reconnect" button to log in with the provider again.',
                'Ensure you grant all requested permissions during the OAuth flow.'
            ],
            ConnectionStatus::RATE_LIMITED => [
                'We are hitting the provider\'s rate limit.',
                'Syncing has been automatically slowed down.',
                'If this persists, check your provider tier or contact their support.'
            ],
            ConnectionStatus::QUOTA_EXCEEDED => [
                'Your account has exceeded its API quota.',
                'Please upgrade your plan with the provider or wait until your quota resets.'
            ],
            ConnectionStatus::SUSPENDED => [
                'Your access has been suspended by the provider.',
                'Log into your provider dashboard to resolve any account warnings.',
            ],
            ConnectionStatus::DEGRADED => [
                'The provider\'s API is currently experiencing downtime or timeouts.',
                'No action is required. We will automatically recover when the service returns.'
            ],
            ConnectionStatus::ERROR => [
                'An error occurred during sync: ' . ($this->sync_error ?? 'Unknown'),
                'Verify your credentials and network settings.',
                'Try deleting and recreating the connection if the issue persists.'
            ],
            default => [],
        };
    }

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
     * Get a synchronization cursor for an entity.
     *
     * @param string $entity
     * @return string|null
     */
    public function getCursor(string $entity): ?string
    {
        return $this->settings['cursors'][$entity] ?? null;
    }

    /**
     * Set a synchronization cursor for an entity.
     *
     * @param string $entity
     * @param string|null $cursor
     * @return void
     */
    public function setCursor(string $entity, ?string $cursor): void
    {
        $settings = $this->settings ?? [];
        if ($cursor === null) {
            unset($settings['cursors'][$entity]);
        } else {
            $settings['cursors'][$entity] = $cursor;
        }
        $this->update(['settings' => $settings]);
    }

    /**
     * Get the configured sync interval in minutes. Default is 60.
     *
     * @return int
     */
    public function getSyncIntervalMinutes(): int
    {
        return $this->settings['sync_interval_minutes'] ?? 60;
    }

    /**
     * Mark the connection as successfully synced.
     *
     * @return void
     */
    public function markSynced(): void
    {
        $this->update([
            'status' => ConnectionStatus::ACTIVE,
            'last_synced_at' => now(),
            'sync_error' => null,
        ]);
    }

    /**
     * Mark the connection with an error status based on a raw message.
     *
     * @param string $message
     * @return void
     */
    public function markError(string $message): void
    {
        $specificErrorStatuses = [
            ConnectionStatus::TOKEN_EXPIRED,
            ConnectionStatus::RATE_LIMITED,
            ConnectionStatus::SUSPENDED,
            ConnectionStatus::QUOTA_EXCEEDED,
            ConnectionStatus::DEGRADED,
            ConnectionStatus::PENDING_REAUTH,
        ];

        $statusToSet = in_array($this->status, $specificErrorStatuses) 
            ? $this->status 
            : ConnectionStatus::ERROR;

        // Handle transient grace period
        $settings = $this->settings ?? [];
        $settings['total_syncs'] = ($settings['total_syncs'] ?? 0) + 1;

        if (in_array($statusToSet, [ConnectionStatus::ERROR, ConnectionStatus::DEGRADED, ConnectionStatus::RATE_LIMITED])) {
            $failures = ($settings['consecutive_failures'] ?? 0) + 1;
            $settings['consecutive_failures'] = $failures;
            
            if ($failures < 3) {
                $statusToSet = ConnectionStatus::DEGRADED;
            }
        }

        $this->update([
            'status' => $statusToSet,
            'sync_error' => substr($message, 0, 500),
            'settings' => $settings,
        ]);
    }

    /**
     * Parse an exception and set the granular connection status.
     *
     * @param \Throwable $e
     * @return void
     */
    public function handleException(\Throwable $e): void
    {
        $status = ConnectionStatus::ERROR;
        $message = $e->getMessage();

        if ($e instanceof \GuzzleHttp\Exception\RequestException && $e->hasResponse()) {
            $statusCode = $e->getResponse()->getStatusCode();

            $status = match ($statusCode) {
                401 => ConnectionStatus::TOKEN_EXPIRED,
                403 => ConnectionStatus::SUSPENDED,
                429 => ConnectionStatus::RATE_LIMITED,
                502, 503, 504 => ConnectionStatus::DEGRADED,
                default => ConnectionStatus::ERROR,
            };

            // Heuristic for Quota Exceeded
            if ($statusCode === 403 || $statusCode === 429) {
                $body = (string) $e->getResponse()->getBody();
                if (stripos($body, 'quota') !== false || stripos($body, 'limit') !== false) {
                    $status = ConnectionStatus::QUOTA_EXCEEDED;
                }
            }
        }

        $settings = $this->settings ?? [];
        $settings['total_syncs'] = ($settings['total_syncs'] ?? 0) + 1;

        // Handle transient grace period
        if (in_array($status, [ConnectionStatus::ERROR, ConnectionStatus::DEGRADED, ConnectionStatus::RATE_LIMITED])) {
            $failures = ($settings['consecutive_failures'] ?? 0) + 1;
            $settings['consecutive_failures'] = $failures;
            
            if ($failures < 3) {
                $status = ConnectionStatus::DEGRADED;
            }
        }

        $this->update([
            'status' => $status,
            'sync_error' => substr($message, 0, 500),
            'settings' => $settings,
        ]);
        
        if ($status === ConnectionStatus::TOKEN_EXPIRED) {
            event(new \App\Modules\MCP\Events\ConnectionTokenExpiredEvent($this));
        } elseif (in_array($status, [ConnectionStatus::DEGRADED, ConnectionStatus::RATE_LIMITED, ConnectionStatus::QUOTA_EXCEEDED])) {
            event(new \App\Modules\MCP\Events\ConnectionHealthDegradedEvent($this));
        }
    }

    /**
     * Clear errors and transition back to active status if applicable.
     *
     * @param int $latencyMs Time taken for sync in milliseconds
     * @return void
     */
    public function markSuccess(int $latencyMs = 0): void
    {
        $updateData = ['last_synced_at' => now(), 'sync_error' => null];
        
        $settings = $this->settings ?? [];
        $settings['total_syncs'] = ($settings['total_syncs'] ?? 0) + 1;
        $settings['successful_syncs'] = ($settings['successful_syncs'] ?? 0) + 1;

        if ($latencyMs > 0) {
            $settings['last_latency_ms'] = $latencyMs;
            $avg = $settings['avg_latency_ms'] ?? $latencyMs;
            $settings['avg_latency_ms'] = round(($avg * 0.9) + ($latencyMs * 0.1));
        }

        if (isset($settings['consecutive_failures'])) {
            unset($settings['consecutive_failures']);
        }
        $updateData['settings'] = $settings;

        if (in_array($this->status, [ConnectionStatus::ERROR, ConnectionStatus::DEGRADED, ConnectionStatus::RATE_LIMITED])) {
            $updateData['status'] = ConnectionStatus::ACTIVE;
        }

        $this->update($updateData);
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

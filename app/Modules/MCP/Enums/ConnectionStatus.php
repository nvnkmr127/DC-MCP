<?php

namespace App\Modules\MCP\Enums;

enum ConnectionStatus: string
{
    // Existing core states
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case DISCONNECTED = 'disconnected';
    case ERROR = 'error';

    // Newly added granular states
    case PENDING_VERIFICATION = 'pending_verification';
    case TOKEN_EXPIRED = 'token_expired';
    case RATE_LIMITED = 'rate_limited';
    case PARTIALLY_ACTIVE = 'partially_active';
    case SUSPENDED = 'suspended';
    case QUOTA_EXCEEDED = 'quota_exceeded';
    case PENDING_REAUTH = 'pending_reauth';
    case DEGRADED = 'degraded';
    case PAUSED = 'paused';

    /**
     * Get a human-readable label for the status.
     *
     * @return string
     */
    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }

    /**
     * Check if the connection needs user action based on its status.
     *
     * @return bool
     */
    public function requiresUserAction(): bool
    {
        return in_array($this, [
            self::ERROR,
            self::TOKEN_EXPIRED,
            self::PENDING_REAUTH,
            self::DISCONNECTED,
            self::SUSPENDED,
            self::PAUSED,
        ]);
    }
}

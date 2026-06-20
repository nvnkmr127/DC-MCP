<?php

namespace App\Modules\Notifications\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use App\Modules\Auth\Models\User;

class InAppNotification extends Model
{
    use HasUuids;

    protected $table = 'notifications_log';

    protected $fillable = [
        'organization_id',
        'user_id',
        'type',
        'channel',
        'title',
        'body',
        'data',
        'status',
        'read_at',
        'sent_at',
        'snoozed_until',
    ];

    protected $casts = [
        'data'          => 'array',
        'read_at'       => 'datetime',
        'sent_at'       => 'datetime',
        'snoozed_until' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getIsReadAttribute(): bool
    {
        return $this->status === 'read' || $this->read_at !== null;
    }

    public function markRead(): void
    {
        $this->update(['status' => 'read', 'read_at' => now()]);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeUnread($query)
    {
        return $query->whereNotIn('status', ['read'])
                     ->whereNull('read_at')
                     ->where(function($q) {
                         $q->whereNull('snoozed_until')
                           ->orWhere('snoozed_until', '<=', now());
                     });
    }

    public function scopeInApp($query)
    {
        return $query->where('channel', 'in_app');
    }
}

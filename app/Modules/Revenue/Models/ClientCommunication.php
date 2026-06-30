<?php

namespace App\Modules\Revenue\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientCommunication extends BaseModel
{
    use HasOrganization, SoftDeletes;

    protected $table = 'client_communications';

    protected $fillable = [
        'organization_id', 'client_id', 'user_id', 'type',
        'contact_person', 'subject', 'notes', 'outcome',
        'next_action', 'next_action_date', 'communicated_at', 'status'
    ];

    protected $casts = [
        'communicated_at'  => 'datetime',
        'next_action_date' => 'date',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\ProjectManagement\Models\Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Auth\Models\User::class);
    }

    public function comments()
    {
        return $this->morphMany(\App\Modules\ProjectManagement\Models\Comment::class, 'commentable');
    }

    public function attachments()
    {
        return $this->morphMany(\App\Modules\ProjectManagement\Models\Attachment::class, 'attachable');
    }
}

<?php

namespace App\Modules\ClientPortal\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortalRequest extends BaseModel
{
    use HasOrganization;

    protected $table = 'client_portal_requests';

    protected $fillable = [
        'organization_id',
        'client_id',
        'portal_user_id',
        'title',
        'description',
        'type',
        'status',
        'priority',
        'task_id',
        'actioned_by',
        'actioned_at',
        'meta',
    ];

    protected $casts = [
        'actioned_at' => 'datetime',
        'meta'        => 'array',
    ];

    public function portalUser(): BelongsTo
    {
        return $this->belongsTo(PortalUser::class, 'portal_user_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\ProjectManagement\Models\Client::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\ProjectManagement\Models\Task::class);
    }

    public function actionedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Auth\Models\User::class, 'actioned_by');
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

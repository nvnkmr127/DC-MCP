<?php

namespace App\Modules\ClientPortal\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\ProjectManagement\Models\Client;
use App\Modules\ProjectManagement\Models\Task;

class ClientPortalRequest extends BaseModel
{
    use HasOrganization;

    protected $table = 'client_portal_requests';

    protected $fillable = [
        'organization_id', 'client_id', 'portal_user_id', 'task_id',
        'title', 'description', 'status', 'closed_at',
    ];

    protected $casts = [
        'closed_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function portalUser(): BelongsTo
    {
        return $this->belongsTo(ClientPortalUser::class, 'portal_user_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
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

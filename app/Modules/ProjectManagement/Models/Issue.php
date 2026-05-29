<?php

namespace App\Modules\ProjectManagement\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Issue extends BaseModel
{
    use HasOrganization, SoftDeletes;

    protected $table = 'issues';

    protected $fillable = [
        'organization_id', 'client_id', 'project_id', 'reported_by', 'assigned_to',
        'task_id', 'title', 'description', 'type', 'priority', 'status',
        'source', 'resolution', 'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'deleted_at'  => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Auth\Models\User::class, 'assigned_to');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Auth\Models\User::class, 'reported_by');
    }
}

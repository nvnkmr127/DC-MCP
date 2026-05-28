<?php

namespace App\Modules\ContentCalendar\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContentItem extends BaseModel
{
    use HasOrganization, SoftDeletes;

    protected $table = 'content_items';

    protected $fillable = [
        'organization_id',
        'client_id',
        'project_id',
        'assigned_to',
        'created_by',
        'approved_by',
        'task_id',
        'title',
        'body',
        'type',
        'platform',
        'status',
        'due_date',
        'scheduled_at',
        'published_at',
        'approved_at',
        'meta',
        'tags',
        'sort_order',
    ];

    protected $casts = [
        'due_date'     => 'date',
        'scheduled_at' => 'datetime',
        'published_at' => 'datetime',
        'approved_at'  => 'datetime',
        'meta'         => 'array',
        'tags'         => 'array',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\ProjectManagement\Models\Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\ProjectManagement\Models\Project::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Auth\Models\User::class, 'assigned_to');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Auth\Models\User::class, 'approved_by');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\ProjectManagement\Models\Task::class);
    }

    public function isApproved(): bool
    {
        return in_array($this->status, ['approved', 'scheduled', 'published']);
    }
}

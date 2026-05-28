<?php

namespace App\Modules\TaskEngine\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskSuggestion extends BaseModel
{
    use HasOrganization;

    protected $table = 'task_suggestions';

    protected $fillable = [
        'organization_id',
        'briefing_id',
        'title',
        'description',
        'project_id',
        'client_id',
        'role_required',
        'priority',
        'due_date',
        'estimated_hours',
        'suggested_by',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'task_id',
        'meta',
    ];

    protected $casts = [
        'due_date'    => 'date',
        'approved_at' => 'datetime',
        'meta'        => 'array',
    ];

    public function briefing(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\DailyBriefing\Models\DailyBriefing::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\ProjectManagement\Models\Project::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\ProjectManagement\Models\Client::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Auth\Models\User::class, 'approved_by');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\ProjectManagement\Models\Task::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}

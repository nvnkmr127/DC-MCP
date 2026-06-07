<?php

namespace App\Modules\ProjectManagement\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeEntry extends BaseModel
{
    use HasOrganization;

    protected $table = 'time_entries';

    protected $fillable = [
        'organization_id',
        'task_id',
        'user_id',
        'project_id',
        'description',
        'hours',
        'logged_date',
        'is_billable',
        'timer_started_at',
    ];

    protected $casts = [
        'hours' => 'decimal:2',
        'logged_date' => 'date',
        'is_billable' => 'boolean',
        'timer_started_at' => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Auth\Models\User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}

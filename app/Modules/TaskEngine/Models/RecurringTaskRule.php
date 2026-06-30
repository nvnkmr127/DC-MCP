<?php

namespace App\Modules\TaskEngine\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringTaskRule extends BaseModel
{
    use HasOrganization;

    protected $table = 'recurring_task_rules';

    protected $fillable = [
        'organization_id', 'client_id', 'project_id', 'title', 'description',
        'target_type', 'target_template_id',
        'type', 'role_required', 'priority', 'frequency', 'frequency_day',
        'sla_hours', 'estimated_hours', 'is_active', 'last_spawned_at',
        'next_spawn_at', 'created_by',
    ];

    protected $casts = [
        'is_active'       => 'boolean',
        'estimated_hours' => 'decimal:2',
        'last_spawned_at' => 'datetime',
        'next_spawn_at'   => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\ProjectManagement\Models\Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\ProjectManagement\Models\Project::class);
    }

    public function isDue(): bool
    {
        return $this->is_active && $this->next_spawn_at && $this->next_spawn_at->lte(now());
    }
}

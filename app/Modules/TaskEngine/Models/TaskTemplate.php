<?php

namespace App\Modules\TaskEngine\Models;

use App\Shared\Models\BaseModel;

class TaskTemplate extends BaseModel
{
    protected $table = 'task_templates';

    protected $fillable = [
        'project_type',
        'title',
        'description',
        'type',
        'role_required',
        'estimated_hours',
        'sla_hours',
        'sort_order',
        'depends_on',
        'is_active',
    ];

    protected $casts = [
        'estimated_hours' => 'decimal:2',
        'depends_on' => 'array',
        'is_active' => 'boolean',
        'sla_hours' => 'integer',
        'sort_order' => 'integer',
    ];
}

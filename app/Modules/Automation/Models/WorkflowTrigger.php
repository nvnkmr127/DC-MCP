<?php

namespace App\Modules\Automation\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;

class WorkflowTrigger extends BaseModel
{
    use HasOrganization;

    protected $table = 'workflow_triggers';

    protected $fillable = [
        'organization_id', 'name', 'description', 'trigger_event',
        'conditions', 'action_type', 'action_config', 'is_active',
        'run_count', 'last_run_at',
    ];

    protected $casts = [
        'conditions'    => 'array',
        'action_config' => 'array',
        'is_active'     => 'boolean',
        'last_run_at'   => 'datetime',
    ];
}

<?php

namespace App\Modules\ProjectManagement\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;

class ProjectTemplate extends BaseModel
{
    use HasOrganization;

    protected $table = 'project_templates';

    protected $fillable = [
        'organization_id', 'name', 'description', 'service_type', 'tasks',
    ];

    protected $casts = [
        'tasks' => 'array',
    ];
}

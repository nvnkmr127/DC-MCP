<?php

namespace App\Modules\ProjectManagement\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SprintTask extends BaseModel
{
    use HasOrganization;

    protected $table = 'sprint_tasks';

    protected $fillable = [
        'organization_id', 'sprint_id', 'task_id', 'story_points',
    ];

    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}

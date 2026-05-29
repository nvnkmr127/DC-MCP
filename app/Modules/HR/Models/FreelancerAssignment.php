<?php

namespace App\Modules\HR\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FreelancerAssignment extends BaseModel
{
    use HasOrganization;

    protected $table = 'freelancer_assignments';

    protected $fillable = [
        'organization_id', 'freelancer_id', 'project_id', 'task_id',
        'agreed_rate', 'start_date', 'end_date', 'hours_worked',
        'total_paid', 'status', 'notes',
    ];

    protected $casts = [
        'start_date'  => 'date',
        'end_date'    => 'date',
        'agreed_rate' => 'decimal:2',
        'hours_worked'=> 'decimal:2',
        'total_paid'  => 'decimal:2',
    ];

    public function freelancer(): BelongsTo
    {
        return $this->belongsTo(Freelancer::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\ProjectManagement\Models\Project::class);
    }
}

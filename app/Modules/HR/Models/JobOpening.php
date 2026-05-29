<?php

namespace App\Modules\HR\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobOpening extends BaseModel
{
    use HasOrganization, SoftDeletes;

    protected $table = 'job_openings';

    protected $fillable = [
        'organization_id', 'title', 'department', 'description', 'requirements',
        'salary_min', 'salary_max', 'status', 'target_date',
    ];

    protected $casts = [
        'salary_min'  => 'decimal:2',
        'salary_max'  => 'decimal:2',
        'target_date' => 'date',
        'deleted_at'  => 'datetime',
    ];

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }
}

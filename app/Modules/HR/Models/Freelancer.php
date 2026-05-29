<?php

namespace App\Modules\HR\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Freelancer extends BaseModel
{
    use HasOrganization, SoftDeletes;

    protected $table = 'freelancers';

    protected $fillable = [
        'organization_id', 'name', 'email', 'phone', 'skill_set',
        'status', 'rate_per_hour', 'payment_method', 'notes',
    ];

    protected $casts = [
        'rate_per_hour' => 'decimal:2',
        'deleted_at'    => 'datetime',
    ];

    public function assignments(): HasMany
    {
        return $this->hasMany(FreelancerAssignment::class);
    }
}

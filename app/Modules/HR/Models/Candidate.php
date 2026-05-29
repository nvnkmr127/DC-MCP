<?php

namespace App\Modules\HR\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Candidate extends BaseModel
{
    use HasOrganization, SoftDeletes;

    protected $table = 'candidates';

    protected $fillable = [
        'organization_id', 'job_opening_id', 'name', 'email', 'phone',
        'resume_url', 'source', 'stage', 'rating', 'notes', 'rejected_reason', 'hired_at',
    ];

    protected $casts = [
        'hired_at'   => 'date',
        'deleted_at' => 'datetime',
    ];

    public function jobOpening(): BelongsTo
    {
        return $this->belongsTo(JobOpening::class);
    }
}

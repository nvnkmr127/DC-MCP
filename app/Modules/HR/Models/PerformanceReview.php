<?php

namespace App\Modules\HR\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceReview extends BaseModel
{
    use HasOrganization;

    protected $table = 'performance_reviews';

    protected $fillable = [
        'organization_id', 'reviewer_id', 'reviewee_id', 'period', 'year',
        'overall_rating', 'technical_rating', 'communication_rating', 'teamwork_rating',
        'strengths', 'improvements', 'goals_next', 'status', 'acknowledged_at',
    ];

    protected $casts = [
        'acknowledged_at' => 'datetime',
    ];

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Auth\Models\User::class, 'reviewer_id');
    }

    public function reviewee(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Auth\Models\User::class, 'reviewee_id');
    }
}

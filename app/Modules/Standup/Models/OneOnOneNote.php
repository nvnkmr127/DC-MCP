<?php

namespace App\Modules\Standup\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OneOnOneNote extends BaseModel
{
    use HasOrganization;

    protected $table = 'one_on_one_notes';

    protected $fillable = [
        'organization_id', 'manager_id', 'member_id',
        'meeting_date', 'wins', 'challenges', 'action_items',
        'mood', 'next_meeting_date', 'performance_review_id', 'template_name',
    ];

    protected $casts = [
        'action_items'    => 'array',
        'meeting_date'    => 'date',
        'next_meeting_date' => 'date',
    ];

    public function manager(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Auth\Models\User::class, 'manager_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Auth\Models\User::class, 'member_id');
    }

    public function performanceReview(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\HR\Models\PerformanceReview::class, 'performance_review_id');
    }


}

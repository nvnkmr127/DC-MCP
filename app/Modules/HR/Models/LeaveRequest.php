<?php

namespace App\Modules\HR\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends BaseModel
{
    use HasOrganization;

    protected $table = 'leave_requests';

    protected $fillable = [
        'organization_id', 'user_id', 'reviewed_by', 'type',
        'from_date', 'to_date', 'days', 'reason', 'status',
        'reviewer_notes', 'reviewed_at',
    ];

    protected $casts = [
        'from_date'   => 'date',
        'to_date'     => 'date',
        'reviewed_at' => 'datetime',
        'days'        => 'decimal:1',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Auth\Models\User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Auth\Models\User::class, 'reviewed_by');
    }
}

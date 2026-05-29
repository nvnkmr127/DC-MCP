<?php

namespace App\Modules\HR\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalance extends BaseModel
{
    use HasOrganization;

    protected $table = 'leave_balances';

    protected $fillable = [
        'organization_id', 'user_id', 'year',
        'earned_total', 'earned_used', 'sick_total', 'sick_used',
        'casual_total', 'casual_used',
    ];

    protected $casts = [
        'earned_total'  => 'decimal:1',
        'earned_used'   => 'decimal:1',
        'sick_total'    => 'decimal:1',
        'sick_used'     => 'decimal:1',
        'casual_total'  => 'decimal:1',
        'casual_used'   => 'decimal:1',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Auth\Models\User::class);
    }
}

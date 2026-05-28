<?php
namespace App\Modules\Revenue\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollRecord extends BaseModel
{
    use HasOrganization;

    protected $table = 'payroll_records';

    protected $fillable = [
        'organization_id', 'user_id', 'month_year', 'base_salary',
        'bonuses', 'deductions', 'net_pay', 'currency',
        'status', 'notes', 'paid_at', 'processed_by',
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'bonuses'     => 'decimal:2',
        'deductions'  => 'decimal:2',
        'net_pay'     => 'decimal:2',
        'paid_at'     => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Auth\Models\User::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Auth\Models\User::class, 'processed_by');
    }

    public function isPaid(): bool { return $this->status === 'paid'; }
}

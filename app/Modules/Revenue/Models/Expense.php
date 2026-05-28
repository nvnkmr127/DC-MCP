<?php
namespace App\Modules\Revenue\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends BaseModel
{
    use HasOrganization, SoftDeletes;

    protected $table = 'expenses';

    protected $fillable = [
        'organization_id', 'title', 'category', 'amount', 'currency',
        'expense_date', 'vendor', 'notes', 'receipt_url',
        'is_recurring', 'recurrence', 'created_by',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'expense_date' => 'date',
        'is_recurring' => 'boolean',
        'deleted_at'   => 'datetime',
    ];
}

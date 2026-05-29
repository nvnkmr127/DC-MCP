<?php

namespace App\Modules\Revenue\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditNote extends BaseModel
{
    use HasOrganization;

    protected $table = 'credit_notes';

    protected $fillable = [
        'organization_id', 'invoice_id', 'client_id', 'created_by',
        'credit_note_number', 'issue_date', 'amount', 'reason', 'status', 'applied_at',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'applied_at' => 'datetime',
        'amount'     => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\ProjectManagement\Models\Client::class);
    }
}

<?php

namespace App\Modules\Revenue\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentReceipt extends BaseModel
{
    use HasOrganization;

    protected $table = 'payment_receipts';

    protected $fillable = [
        'organization_id', 'invoice_id', 'client_id', 'amount',
        'payment_date', 'payment_method', 'reference_no', 'notes', 'recorded_by',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'payment_date' => 'date',
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

<?php

namespace App\Modules\Revenue\Models;

use Laravel\Scout\Searchable;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends BaseModel
{
    use HasOrganization, SoftDeletes, Searchable;

    protected $table = 'invoices';

    protected $fillable = [
        'organization_id', 'client_id', 'project_id', 'retainer_id', 'invoice_number',
        'amount', 'currency', 'status', 'issue_date', 'due_date', 'paid_at',
        'payment_method', 'notes', 'meta',
        'client_gstin', 'agency_gstin', 'gst_rate', 'gst_amount', 'hsn_code', 'supply_type',
    ];

    protected $casts = [
        'amount'     => 'decimal:2',
        'issue_date' => 'date',
        'due_date'   => 'date',
        'paid_at'    => 'datetime',
        'meta'       => 'array',
        'deleted_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\ProjectManagement\Models\Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\ProjectManagement\Models\Project::class);
    }

    public function retainer(): BelongsTo
    {
        return $this->belongsTo(ClientRetainer::class, 'retainer_id');
    }

    public function paymentReceipts(): HasMany
    {
        return $this->hasMany(PaymentReceipt::class);
    }

    public function amountPaid(): float
    {
        return (float) $this->paymentReceipts()->sum('amount');
    }

    public function balanceDue(): float
    {
        return (float) $this->amount - $this->amountPaid();
    }

    public function paymentStatus(): string
    {
        $paid = $this->amountPaid();
        if ($paid <= 0) return 'unpaid';
        if ($this->balanceDue() <= 0) return 'paid';
        return 'partial';
    }

    public function isOverdue(): bool
    {
        return $this->status !== 'paid' && $this->due_date && $this->due_date->isPast();
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }
}

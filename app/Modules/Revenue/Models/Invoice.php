<?php

namespace App\Modules\Revenue\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends BaseModel
{
    use HasOrganization, SoftDeletes;

    protected $table = 'invoices';

    protected $fillable = [
        'organization_id', 'client_id', 'retainer_id', 'invoice_number',
        'amount', 'currency', 'status', 'issue_date', 'due_date', 'paid_at',
        'payment_method', 'notes', 'meta',
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

    public function retainer(): BelongsTo
    {
        return $this->belongsTo(ClientRetainer::class, 'retainer_id');
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

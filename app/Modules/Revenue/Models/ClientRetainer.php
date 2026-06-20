<?php

namespace App\Modules\Revenue\Models;

use Laravel\Scout\Searchable;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientRetainer extends BaseModel
{
    use HasOrganization, SoftDeletes, Searchable;

    protected $table = 'client_retainers';

    protected $fillable = [
        'organization_id', 'client_id', 'name', 'monthly_value', 'currency',
        'billing_cycle', 'start_date', 'end_date', 'status',
        'payment_terms_days', 'notes', 'auto_renew', 'next_renewal_date',
    ];

    protected $casts = [
        'monthly_value'     => 'decimal:2',
        'start_date'        => 'date',
        'end_date'          => 'date',
        'next_renewal_date' => 'date',
        'auto_renew'        => 'boolean',
        'deleted_at'        => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\ProjectManagement\Models\Client::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'retainer_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isDueForRenewal(int $daysAhead = 30): bool
    {
        return $this->next_renewal_date && $this->next_renewal_date->lte(now()->addDays($daysAhead));
    }
}

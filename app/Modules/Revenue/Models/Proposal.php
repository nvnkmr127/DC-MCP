<?php

namespace App\Modules\Revenue\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Proposal extends BaseModel
{
    use HasOrganization, SoftDeletes;

    protected $table = 'proposals';

    protected $fillable = [
        'organization_id', 'client_id', 'created_by', 'title', 'status',
        'valid_until', 'subtotal', 'discount', 'tax_amount', 'total_value',
        'notes', 'public_token', 'sent_at', 'accepted_at',
    ];

    protected $casts = [
        'valid_until'  => 'date',
        'sent_at'      => 'datetime',
        'accepted_at'  => 'datetime',
        'subtotal'     => 'decimal:2',
        'discount'     => 'decimal:2',
        'tax_amount'   => 'decimal:2',
        'total_value'  => 'decimal:2',
        'deleted_at'   => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\ProjectManagement\Models\Client::class);
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(ProposalLineItem::class);
    }
}

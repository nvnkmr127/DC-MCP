<?php

namespace App\Modules\Revenue\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Prospect extends BaseModel
{
    use HasOrganization, SoftDeletes;

    protected $table = 'prospects';

    protected $fillable = [
        'organization_id', 'company_name', 'contact_name', 'contact_email',
        'contact_phone', 'source', 'stage', 'estimated_value', 'currency',
        'probability', 'services_interested', 'assigned_to', 'expected_close_date',
        'lost_reason', 'notes', 'converted_client_id', 'created_by',
    ];

    protected $casts = [
        'estimated_value'     => 'decimal:2',
        'probability'         => 'integer',
        'services_interested' => 'array',
        'expected_close_date' => 'date',
        'deleted_at'          => 'datetime',
    ];

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Auth\Models\User::class, 'assigned_to');
    }

    public function convertedClient(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\ProjectManagement\Models\Client::class, 'converted_client_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ProspectActivity::class, 'prospect_id');
    }

    public function weightedValue(): float
    {
        return (float) $this->estimated_value * ($this->probability / 100);
    }

    public function isWon(): bool { return $this->stage === 'won'; }
    public function isLost(): bool { return $this->stage === 'lost'; }
    public function isActive(): bool { return !in_array($this->stage, ['won', 'lost']); }
}

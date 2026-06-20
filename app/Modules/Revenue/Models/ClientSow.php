<?php

namespace App\Modules\Revenue\Models;

use Laravel\Scout\Searchable;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientSow extends BaseModel
{
    use HasOrganization, SoftDeletes, Searchable;

    protected $table = 'client_sows';

    protected $fillable = [
        'organization_id', 'client_id', 'retainer_id', 'title', 'description',
        'start_date', 'end_date', 'status', 'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
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

    public function deliverables(): HasMany
    {
        return $this->hasMany(SowDeliverable::class, 'sow_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}

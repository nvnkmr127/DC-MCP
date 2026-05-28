<?php

namespace App\Modules\Revenue\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SowDeliverable extends BaseModel
{
    use HasOrganization;

    protected $table = 'sow_deliverables';

    protected $fillable = [
        'organization_id', 'sow_id', 'client_id', 'title', 'service_type',
        'frequency', 'quantity_per_period', 'notes',
    ];

    protected $casts = [
        'quantity_per_period' => 'integer',
    ];

    public function sow(): BelongsTo
    {
        return $this->belongsTo(ClientSow::class, 'sow_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\ProjectManagement\Models\Client::class);
    }
}

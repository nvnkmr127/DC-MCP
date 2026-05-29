<?php

namespace App\Modules\Revenue\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;

class RateCard extends BaseModel
{
    use HasOrganization;

    protected $table = 'rate_cards';

    protected $fillable = [
        'organization_id', 'service_name', 'category', 'description',
        'unit', 'rate', 'currency', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'rate'      => 'decimal:2',
        'is_active' => 'boolean',
    ];
}

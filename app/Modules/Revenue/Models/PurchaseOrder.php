<?php

namespace App\Modules\Revenue\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends BaseModel
{
    use HasOrganization, SoftDeletes;

    protected $table = 'purchase_orders';

    protected $fillable = [
        'organization_id', 'vendor_id', 'created_by', 'po_number',
        'issue_date', 'expected_delivery', 'total_amount', 'status',
        'line_items', 'notes',
    ];

    protected $casts = [
        'line_items'        => 'array',
        'issue_date'        => 'date',
        'expected_delivery' => 'date',
        'total_amount'      => 'decimal:2',
        'deleted_at'        => 'datetime',
    ];
}

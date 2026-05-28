<?php
namespace App\Modules\Revenue\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorContract extends BaseModel
{
    use HasOrganization, SoftDeletes;

    protected $table = 'vendor_contracts';

    protected $fillable = [
        'organization_id', 'name', 'type', 'monthly_cost', 'currency',
        'billing_cycle', 'billing_day', 'website', 'contact_email',
        'status', 'contract_start', 'contract_end', 'notes', 'created_by',
    ];

    protected $casts = [
        'monthly_cost'   => 'decimal:2',
        'contract_start' => 'date',
        'contract_end'   => 'date',
        'deleted_at'     => 'datetime',
    ];

    public function isActive(): bool { return $this->status === 'active'; }
}

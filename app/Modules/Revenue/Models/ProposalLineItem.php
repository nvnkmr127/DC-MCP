<?php

namespace App\Modules\Revenue\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProposalLineItem extends BaseModel
{
    use HasOrganization;

    protected $table = 'proposal_line_items';

    protected $fillable = [
        'organization_id', 'proposal_id', 'service', 'description',
        'unit_price', 'quantity', 'frequency', 'sort_order',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'quantity'   => 'decimal:2',
    ];

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }
}

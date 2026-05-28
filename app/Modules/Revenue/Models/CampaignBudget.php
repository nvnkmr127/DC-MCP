<?php
namespace App\Modules\Revenue\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignBudget extends BaseModel
{
    use HasOrganization;

    protected $table = 'campaign_budgets';

    protected $fillable = [
        'organization_id', 'client_id', 'project_id', 'channel', 'month_year',
        'allocated_budget', 'spent_amount', 'currency', 'notes',
    ];

    protected $casts = [
        'allocated_budget' => 'decimal:2',
        'spent_amount'     => 'decimal:2',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\ProjectManagement\Models\Client::class);
    }

    public function utilizationPercent(): float
    {
        if ((float)$this->allocated_budget <= 0) return 0;
        return round(((float)$this->spent_amount / (float)$this->allocated_budget) * 100, 1);
    }

    public function remaining(): float
    {
        return max(0, (float)$this->allocated_budget - (float)$this->spent_amount);
    }
}

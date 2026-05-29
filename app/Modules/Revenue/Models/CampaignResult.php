<?php

namespace App\Modules\Revenue\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignResult extends BaseModel
{
    use HasOrganization;

    protected $table = 'campaign_results';

    protected $fillable = [
        'organization_id', 'campaign_budget_id', 'client_id', 'report_date',
        'impressions', 'clicks', 'conversions', 'spend', 'revenue',
        'ctr', 'cpc', 'roas', 'platform', 'notes',
    ];

    protected $casts = [
        'report_date' => 'date',
    ];

    public function campaignBudget(): BelongsTo
    {
        return $this->belongsTo(CampaignBudget::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\ProjectManagement\Models\Client::class);
    }
}

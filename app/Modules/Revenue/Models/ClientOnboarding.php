<?php
namespace App\Modules\Revenue\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientOnboarding extends BaseModel
{
    use HasOrganization;

    protected $table = 'client_onboardings';

    protected $fillable = [
        'organization_id', 'client_id', 'stage', 'checklist', 'notes',
        'target_go_live', 'actual_go_live', 'assigned_to', 'nps_score', 'nps_comment',
    ];

    protected $casts = [
        'checklist'      => 'array',
        'target_go_live' => 'date',
        'actual_go_live' => 'date',
        'nps_score'      => 'integer',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\ProjectManagement\Models\Client::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Auth\Models\User::class, 'assigned_to');
    }

    public function checklistProgress(): array
    {
        $items = $this->checklist ?? [];
        $total = count($items);
        $done  = count(array_filter($items, fn($i) => $i['done'] ?? false));
        return ['total' => $total, 'done' => $done, 'percent' => $total > 0 ? (int) round(($done / $total) * 100) : 0];
    }

    public function isActive(): bool { return $this->stage === 'active'; }
}

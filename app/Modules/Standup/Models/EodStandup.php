<?php

namespace App\Modules\Standup\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EodStandup extends BaseModel
{
    use HasOrganization;

    protected $table = 'eod_standups';

    protected $fillable = [
        'organization_id', 'user_id', 'date', 'completed_today',
        'in_progress', 'blockers', 'tomorrow_plan', 'status', 'submitted_at',
    ];

    protected $casts = [
        'date'         => 'date',
        'submitted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Auth\Models\User::class);
    }

    public function hasBlockers(): bool
    {
        return !empty(trim((string) $this->blockers));
    }
}

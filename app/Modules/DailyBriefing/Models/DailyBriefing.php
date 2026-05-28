<?php

namespace App\Modules\DailyBriefing\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyBriefing extends BaseModel
{
    use HasOrganization;

    protected $table = 'daily_briefings';

    protected $fillable = [
        'organization_id',
        'user_id',
        'date',
        'status',
        'digest_raw',
        'digest_html',
        'digest_text',
        'ai_model',
        'ai_tokens_used',
        'delivered_via',
        'delivered_at',
    ];

    protected $casts = [
        'date' => 'date',
        'digest_raw' => 'array',
        'delivered_via' => 'array',
        'delivered_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Auth\Models\User::class);
    }
}

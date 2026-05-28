<?php

namespace App\Modules\Revenue\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProspectActivity extends BaseModel
{
    use HasOrganization;

    protected $table = 'prospect_activities';

    protected $fillable = [
        'organization_id', 'prospect_id', 'user_id', 'type', 'note',
        'scheduled_at', 'completed_at',
    ];

    protected $casts = [
        'scheduled_at'  => 'datetime',
        'completed_at'  => 'datetime',
    ];

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class, 'prospect_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Auth\Models\User::class);
    }
}

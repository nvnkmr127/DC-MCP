<?php

namespace App\Modules\Revenue\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientSurvey extends BaseModel
{
    use HasOrganization;

    protected $table = 'client_surveys';

    protected $fillable = [
        'organization_id', 'client_id', 'sent_by', 'public_token',
        'nps_score', 'feedback', 'sent_at', 'responded_at', 'status',
    ];

    protected $casts = [
        'sent_at'      => 'datetime',
        'responded_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\ProjectManagement\Models\Client::class);
    }
}

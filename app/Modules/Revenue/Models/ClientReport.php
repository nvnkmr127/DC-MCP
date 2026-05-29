<?php

namespace App\Modules\Revenue\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientReport extends BaseModel
{
    use HasOrganization;

    protected $table = 'client_reports';

    protected $fillable = [
        'organization_id', 'client_id', 'author_id', 'month_year',
        'status', 'highlights', 'challenges', 'metrics',
    ];

    protected $casts = [
        'metrics' => 'array',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\ProjectManagement\Models\Client::class);
    }
}

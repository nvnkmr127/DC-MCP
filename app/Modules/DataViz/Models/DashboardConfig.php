<?php

namespace App\Modules\DataViz\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardConfig extends BaseModel
{
    use HasOrganization;

    protected $table = 'dashboard_configs';

    protected $fillable = [
        'organization_id',
        'user_id',
        'role',
        'name',
        'is_default',
        'layout',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'layout' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Auth\Models\User::class);
    }
}

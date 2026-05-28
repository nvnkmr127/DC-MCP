<?php

namespace App\Modules\ClientPortal\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\ProjectManagement\Models\Client;
use App\Modules\Auth\Models\User;

class ClientPortalShare extends BaseModel
{
    use HasOrganization;

    protected $table = 'client_portal_shares';

    protected $fillable = [
        'organization_id', 'client_id', 'shared_by',
        'shareable_type', 'shareable_id', 'permissions', 'expires_at',
    ];

    protected $casts = [
        'permissions' => 'array',
        'expires_at'  => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function sharedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_by');
    }

    public function shareable()
    {
        return $this->morphTo();
    }
}

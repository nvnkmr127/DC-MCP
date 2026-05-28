<?php

namespace App\Modules\ClientPortal\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Modules\ProjectManagement\Models\Client;

class ClientPortalUser extends BaseModel
{
    use HasOrganization, SoftDeletes;

    protected $table = 'client_portal_users';

    protected $fillable = [
        'organization_id', 'client_id', 'name', 'email', 'is_active',
        'invite_token', 'invite_expires_at', 'invite_sent_at', 'last_login_at',
    ];

    protected $casts = [
        'is_active'         => 'boolean',
        'invite_expires_at' => 'datetime',
        'invite_sent_at'    => 'datetime',
        'last_login_at'     => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(ClientPortalRequest::class, 'portal_user_id');
    }
}

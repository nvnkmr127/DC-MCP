<?php

namespace App\Modules\ClientPortal\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PortalUser extends BaseModel
{
    use HasOrganization;

    protected $table = 'client_portal_users';

    protected $fillable = [
        'organization_id',
        'client_id',
        'name',
        'email',
        'password',
        'magic_token',
        'magic_token_expires_at',
        'is_active',
        'last_login_at',
        'permissions',
        'created_by',
    ];

    protected $hidden = ['password', 'magic_token'];

    protected $casts = [
        'is_active'              => 'boolean',
        'last_login_at'          => 'datetime',
        'magic_token_expires_at' => 'datetime',
        'permissions'            => 'array',
        'password'               => 'hashed',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\ProjectManagement\Models\Client::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(PortalRequest::class, 'portal_user_id');
    }

    public function can(string $permission): bool
    {
        $permissions = $this->permissions ?? [];
        return $permissions[$permission] ?? true; // default allow
    }
}

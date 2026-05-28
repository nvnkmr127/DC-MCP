<?php

namespace App\Modules\Auth\Models;

use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends BaseModel
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'roles';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'description',
        'is_system',
        'permissions',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_system' => 'boolean',
        'permissions' => 'array',
    ];

    /**
     * Get the organization that owns this role.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the users assigned to this role.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user', 'role_id', 'user_id')
            ->withPivot('assigned_by', 'assigned_at');
    }

    /**
     * Check if the role has a permission for a resource and action.
     */
    public function hasPermission(string $resource, string $action): bool
    {
        if (empty($this->permissions) || !is_array($this->permissions)) {
            return false;
        }

        // Wildcard permission check (e.g. CEO role has '*' -> ['*'])
        if (isset($this->permissions['*']) && is_array($this->permissions['*'])) {
            if (in_array('*', $this->permissions['*']) || in_array($action, $this->permissions['*'])) {
                return true;
            }
        }

        if (isset($this->permissions[$resource]) && is_array($this->permissions[$resource])) {
            return in_array('*', $this->permissions[$resource]) || in_array($action, $this->permissions[$resource]);
        }

        return false;
    }
}

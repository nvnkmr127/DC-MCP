<?php

namespace App\Modules\Auth\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Scout\Searchable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, Searchable;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'organization_id',
        'name',
        'display_name',
        'email',
        'password',
        'avatar_url',
        'phone',
        'timezone',
        'is_active',
        'preferences',
        'monthly_salary',
        'billable_rate',
        'bank_account_last4',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'id'              => 'string',
        'organization_id' => 'string',
        'is_active'       => 'boolean',
        'preferences'     => 'array',
        'email_verified_at' => 'datetime',
        'last_active_at'  => 'datetime',
        'password'        => 'hashed',
        'monthly_salary'  => 'decimal:2',
        'billable_rate'   => 'decimal:2',
    ];

    /**
     * The boot function to generate UUID v4 for User.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the organization that owns this user.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the user's connected accounts (OAuth providers).
     */
    public function connectedAccounts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ConnectedAccount::class);
    }

    /**
     * Get the roles assigned to this user.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id')
            ->withPivot('assigned_by', 'assigned_at');
    }

    /**
     * Get tasks assigned to this user.
     */
    public function assignedTasks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Modules\ProjectManagement\Models\Task::class, 'assigned_to');
    }

    /**
     * Check if the user has any of the specified roles.
     *
     * @param array|string $roles
     * @return bool
     */
    public function hasRoles(array|string $roles): bool
    {
        if (is_string($roles)) {
            return $this->roles->contains('slug', $roles);
        }

        foreach ($roles as $role) {
            if ($this->roles->contains('slug', $role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the user has a specific permission on a resource.
     *
     * @param string $resource
     * @param string $action
     * @return bool
     */
    public function hasPermission(string $resource, string $action): bool
    {
        // CEO has full access to everything in their organization
        if ($this->hasRoles('ceo')) {
            return true;
        }

        foreach ($this->roles as $role) {
            if ($role->hasPermission($resource, $action)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Virtual accessor so controllers can use $user->role as a scalar slug.
     * Returns the slug of the first attached role, or null if no roles are assigned.
     * Multiple controllers rely on this — e.g. in_array($user->role, ['ceo', 'project_manager']).
     */
    public function getRoleAttribute(): ?string
    {
        return $this->roles->first()?->slug;
    }

    public function toSearchableArray()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'organization_id' => $this->organization_id,
        ];
    }
}

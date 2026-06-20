<?php

namespace App\Modules\Auth\Models;

use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends BaseModel
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'organizations';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'logo_url',
        'website',
        'plan',
        'settings',
        'is_active',
        'is_onboarded',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
        'is_onboarded' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the users for the organization.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}


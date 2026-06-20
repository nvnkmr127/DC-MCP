<?php

namespace App\Modules\ProjectManagement\Models;

use Laravel\Scout\Searchable;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use App\Shared\Traits\HasClientScope;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends BaseModel
{
    use HasOrganization, HasClientScope, SoftDeletes, Searchable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'clients';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'organization_id',
        'name',
        'email',
        'phone',
        'company',
        'website',
        'industry',
        'tier',
        'status',
        'notes',
        'metadata',
        'assigned_to',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the user assigned to this client.
     *
     * @return BelongsTo
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Auth\Models\User::class, 'assigned_to');
    }

    /**
     * Get the projects for the client.
     *
     * @return HasMany
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function portalUsers(): HasMany
    {
        return $this->hasMany(\App\Modules\ClientPortal\Models\PortalUser::class, 'client_id');
    }

    public function contentItems(): HasMany
    {
        return $this->hasMany(\App\Modules\ContentCalendar\Models\ContentItem::class, 'client_id');
    }

    public function retainers(): HasMany
    {
        return $this->hasMany(\App\Modules\Revenue\Models\ClientRetainer::class, 'client_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(\App\Modules\Revenue\Models\Invoice::class, 'client_id');
    }

    public function sows(): HasMany
    {
        return $this->hasMany(\App\Modules\Revenue\Models\ClientSow::class, 'client_id');
    }

    public function prospects(): HasMany
    {
        return $this->hasMany(\App\Modules\Revenue\Models\Prospect::class, 'converted_client_id');
    }
}

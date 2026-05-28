<?php

namespace App\Modules\ProjectManagement\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use App\Shared\Traits\HasClientScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends BaseModel
{
    use HasOrganization, HasClientScope, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'projects';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'organization_id',
        'client_id',
        'name',
        'slug',
        'description',
        'type',
        'status',
        'priority',
        'start_date',
        'end_date',
        'actual_end_date',
        'budget',
        'budget_used',
        'project_manager_id',
        'settings',
        'tags',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'settings' => 'array',
        'tags' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'actual_end_date' => 'date',
        'budget' => 'decimal:2',
        'budget_used' => 'decimal:2',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the client that owns the project.
     *
     * @return BelongsTo
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the organization that owns the project.
     *
     * @return BelongsTo
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Auth\Models\Organization::class);
    }

    /**
     * Get the manager of the project.
     *
     * @return BelongsTo
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Auth\Models\User::class, 'project_manager_id');
    }

    /**
     * Get the sprints for the project.
     *
     * @return HasMany
     */
    public function sprints(): HasMany
    {
        return $this->hasMany(Sprint::class);
    }

    /**
     * Get the milestones for the project.
     *
     * @return HasMany
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class);
    }

    /**
     * Get the tasks for the project.
     *
     * @return HasMany
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}

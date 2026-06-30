<?php

namespace App\Modules\ProjectManagement\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use App\Shared\Traits\HasClientScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Project extends BaseModel
{
    use HasOrganization, HasClientScope, SoftDeletes, Searchable;

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
        'goal_id',
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

    public function goal(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Revenue\Models\Goal::class);
    }

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

    public function expenses(): HasMany
    {
        return $this->hasMany(\App\Modules\Revenue\Models\Expense::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(\App\Modules\Revenue\Models\Invoice::class);
    }

    public function campaignBudgets(): HasMany
    {
        return $this->hasMany(\App\Modules\Revenue\Models\CampaignBudget::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(AssetApproval::class);
    }

    /**
     * Compute task completion percentage.
     * Accepts pre-counted values when already loaded via withCount() to avoid extra queries.
     */
    public function completionPct(?int $total = null, ?int $completed = null): int
    {
        $total     ??= $this->tasks()->count();
        $completed ??= $this->tasks()->where('status', 'done')->count();
        return $total > 0 ? (int) round(($completed / $total) * 100) : 0;
    }

    public function toSearchableArray()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'organization_id' => $this->organization_id,
        ];
    }
}

<?php

namespace App\Modules\ProjectManagement\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends BaseModel
{
    use HasOrganization, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tasks';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'organization_id',
        'project_id',
        'sprint_id',
        'milestone_id',
        'parent_task_id',
        'title',
        'description',
        'type',
        'status',
        'priority',
        'assigned_to',
        'created_by',
        'role_required',
        'due_date',
        'completed_at',
        'estimated_hours',
        'actual_hours',
        'sla_hours',
        'sla_breached_at',
        'tags',
        'meta',
        'sort_order',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'tags' => 'array',
        'meta' => 'array',
        'due_date' => 'date',
        'completed_at' => 'datetime',
        'estimated_hours' => 'decimal:2',
        'actual_hours' => 'decimal:2',
        'sla_breached_at' => 'datetime',
        'deleted_at' => 'datetime',
        'sort_order' => 'integer',
        'sla_hours' => 'integer',
    ];

    /**
     * Get the organization that owns the task.
     *
     * @return BelongsTo
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Auth\Models\Organization::class);
    }

    /**
     * Get the project that this task belongs to.
     *
     * @return BelongsTo
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the sprint that this task belongs to.
     *
     * @return BelongsTo
     */
    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class);
    }

    /**
     * Get the milestone that this task belongs to.
     *
     * @return BelongsTo
     */
    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class);
    }

    /**
     * Get the parent task of this task.
     *
     * @return BelongsTo
     */
    public function parentTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_task_id');
    }

    /**
     * Get the subtasks of this task.
     *
     * @return HasMany
     */
    public function subtasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_task_id');
    }

    /**
     * Get the user assigned to this task.
     *
     * @return BelongsTo
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Auth\Models\User::class, 'assigned_to');
    }

    /**
     * Get the user who created this task.
     *
     * @return BelongsTo
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Auth\Models\User::class, 'created_by');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable')->with('user')->orderBy('created_at');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->orderByDesc('created_at');
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class)->with('user')->orderByDesc('logged_date');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(TaskLog::class)->with('actor')->orderByDesc('logged_at');
    }

    // Allow access via assignee_id as alias for assigned_to
    public function getAssigneeIdAttribute(): ?string
    {
        return $this->assigned_to;
    }
}

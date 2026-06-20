<?php

namespace App\Modules\ProjectManagement\Models;

use Laravel\Scout\Searchable;

use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sprint extends BaseModel
{
    use Searchable;

    public function toSearchableArray()
    {
        $array = $this->toArray();
        $array['organization_id'] = $this->project->organization_id ?? null;
        return $array;
    }
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'sprints';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'project_id',
        'name',
        'goal',
        'status',
        'start_date',
        'end_date',
        'velocity_planned',
        'velocity_actual',
        'retrospective_notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'velocity_planned' => 'integer',
        'velocity_actual' => 'integer',
    ];

    /**
     * Get the project that owns the sprint.
     *
     * @return BelongsTo
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the milestones associated with the sprint.
     *
     * @return HasMany
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class);
    }

    public function sprintTasks(): HasMany
    {
        return $this->hasMany(SprintTask::class);
    }

    /**
     * Get the tasks associated with the sprint.
     *
     * @return HasMany
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}

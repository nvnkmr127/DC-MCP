<?php

namespace App\Modules\ProjectManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskDependency extends Model
{
    protected $table = 'task_dependencies';

    protected $fillable = ['task_id', 'depends_on_task_id'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function dependency(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'depends_on_task_id');
    }
}

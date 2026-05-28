<?php

namespace App\Modules\ProjectManagement\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use App\Modules\Auth\Models\User;

class TaskLog extends Model
{
    use HasUuids;

    protected $table = 'task_logs';
    public $timestamps = false;

    protected $fillable = [
        'task_id',
        'user_id',
        'action',
        'old_value',
        'new_value',
        'comment',
        'logged_at',
    ];

    protected $casts = [
        'old_value' => 'array',
        'new_value' => 'array',
        'logged_at' => 'datetime',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Alias for dashboard use
    public function getSubjectLabelAttribute(): string
    {
        return $this->task?->title ?? '';
    }
}

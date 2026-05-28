<?php

namespace App\Modules\ProjectManagement\Events;

use App\Modules\ProjectManagement\Models\Task;
use App\Modules\Auth\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskStatusChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Task $task,
        public readonly string $oldStatus,
        public readonly string $newStatus,
        public readonly User $actor
    ) {}
}

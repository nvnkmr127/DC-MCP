<?php

namespace App\Modules\TaskEngine\Services;

use App\Modules\Auth\Models\User;
use App\Modules\ProjectManagement\Models\Task;
use Illuminate\Database\Eloquent\Collection;

class AutoAssignmentEngine
{
    /**
     * Find the best available team member for a given role using load balancing.
     * Picks the active user with the fewest in-progress + todo tasks.
     */
    public function findBestAssignee(string $orgId, string $roleRequired): ?User
    {
        $candidates = User::where('organization_id', $orgId)
            ->where('is_active', true)
            ->whereHas('roles', fn($q) => $q->where('slug', $roleRequired))
            ->withCount([
                'assignedTasks as active_task_count' => fn($q) => $q->whereIn('status', ['todo', 'in_progress', 'in_review'])
            ])
            ->orderBy('active_task_count')
            ->orderBy('name')
            ->get();

        return $candidates->first();
    }

    /**
     * Get workload summary for all team members with a given role.
     */
    public function getWorkloadByRole(string $orgId, string $roleRequired): Collection
    {
        return User::where('organization_id', $orgId)
            ->where('is_active', true)
            ->whereHas('roles', fn($q) => $q->where('slug', $roleRequired))
            ->withCount([
                'assignedTasks as active_task_count' => fn($q) => $q->whereIn('status', ['todo', 'in_progress', 'in_review']),
                'assignedTasks as overdue_task_count' => fn($q) => $q->whereNotIn('status', ['done', 'cancelled'])->whereDate('due_date', '<', now()),
            ])
            ->orderBy('active_task_count')
            ->get();
    }

    /**
     * Get overall org workload (all roles).
     */
    public function getOrgWorkload(string $orgId): Collection
    {
        return User::where('organization_id', $orgId)
            ->where('is_active', true)
            ->withCount([
                'assignedTasks as active_task_count' => fn($q) => $q->whereIn('status', ['todo', 'in_progress', 'in_review']),
                'assignedTasks as overdue_task_count' => fn($q) => $q->whereNotIn('status', ['done', 'cancelled'])->whereDate('due_date', '<', now()),
            ])
            ->with('roles:id,name,slug')
            ->orderBy('active_task_count')
            ->get();
    }
}

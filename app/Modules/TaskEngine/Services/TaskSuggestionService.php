<?php

namespace App\Modules\TaskEngine\Services;

use App\Modules\Auth\Models\User;
use App\Modules\DailyBriefing\Models\DailyBriefing;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\ProjectManagement\Models\Client;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\ProjectManagement\Models\Task;
use App\Modules\TaskEngine\Models\TaskSuggestion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TaskSuggestionService
{
    public function __construct(
        private AutoAssignmentEngine $assignmentEngine,
        private NotificationService $notificationService,
    ) {}

    /**
     * Parse raw AI suggestions and persist them linked to a briefing.
     */
    public function parseAndStoreFromBriefing(DailyBriefing $briefing, array $rawSuggestions): void
    {
        $orgId = $briefing->organization_id;

        foreach ($rawSuggestions as $raw) {
            if (empty($raw['title'])) {
                continue;
            }

            $clientId  = $this->resolveClientId($orgId, $raw['client_name'] ?? null);
            $projectId = $this->resolveProjectId($orgId, $clientId, $raw['project_name'] ?? null);

            TaskSuggestion::create([
                'organization_id' => $orgId,
                'briefing_id'     => $briefing->id,
                'title'           => $raw['title'],
                'description'     => $raw['description'] ?? null,
                'project_id'      => $projectId,
                'client_id'       => $clientId,
                'role_required'   => $raw['role_required'] ?? null,
                'priority'        => $this->normalizePriority($raw['priority'] ?? 'medium'),
                'due_date'        => $this->parseDate($raw['due_date'] ?? null),
                'estimated_hours' => isset($raw['estimated_hours']) ? (int) $raw['estimated_hours'] : null,
                'suggested_by'    => $briefing->ai_model ?? 'ai',
                'status'          => 'pending',
                'meta'            => ['reasoning' => $raw['reasoning'] ?? null],
            ]);
        }
    }

    /**
     * Approve a suggestion: create the task, auto-assign, and notify.
     */
    public function approve(TaskSuggestion $suggestion, User $approver, array $overrides = []): Task
    {
        return DB::transaction(function () use ($suggestion, $approver, $overrides) {
            $title       = $overrides['title']       ?? $suggestion->title;
            $description = $overrides['description'] ?? $suggestion->description;
            $priority    = $overrides['priority']    ?? $suggestion->priority;
            $dueDate     = $overrides['due_date']    ?? $suggestion->due_date?->toDateString();
            $projectId   = $overrides['project_id']  ?? $suggestion->project_id;
            $roleRequired = $suggestion->role_required;

            // Find best assignee
            $assignee = null;
            if ($roleRequired) {
                $assignee = $this->assignmentEngine->findBestAssignee($suggestion->organization_id, $roleRequired);
            }

            // Create the actual task
            $task = Task::create([
                'organization_id' => $suggestion->organization_id,
                'project_id'      => $projectId,
                'title'           => $title,
                'description'     => $description,
                'type'            => 'task',
                'status'          => 'todo',
                'priority'        => $priority,
                'role_required'   => $roleRequired,
                'assigned_to'     => $assignee?->id,
                'created_by'      => $approver->id,
                'due_date'        => $dueDate,
                'estimated_hours' => $suggestion->estimated_hours,
                'tags'            => ['ai-suggested'],
                'meta'            => [
                    'suggestion_id' => $suggestion->id,
                    'reasoning'     => $suggestion->meta['reasoning'] ?? null,
                ],
            ]);

            // Log creation
            DB::table('task_logs')->insert([
                'id'        => (string) Str::uuid(),
                'task_id'   => $task->id,
                'user_id'   => $approver->id,
                'action'    => 'created',
                'old_value' => null,
                'new_value' => json_encode(['source' => 'ai_suggestion', 'suggestion_id' => $suggestion->id]),
                'comment'   => "Task created from AI suggestion approved by {$approver->name}.",
                'logged_at' => now(),
            ]);

            // Mark suggestion approved
            $suggestion->update([
                'status'      => 'approved',
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'task_id'     => $task->id,
            ]);

            // Notify assignee
            if ($assignee) {
                try {
                    $this->notificationService->sendNotification(
                        $assignee,
                        'task_assigned',
                        'in_app',
                        "New Task: {$task->title}",
                        "You've been assigned a new task approved by {$approver->name}." .
                        ($task->due_date ? " Due: {$task->due_date->format('M d, Y')}." : ''),
                        ['task_id' => $task->id]
                    );
                } catch (\Exception $e) {
                    // Non-critical
                }
            }

            return $task;
        });
    }

    /**
     * Reject a suggestion with a reason.
     */
    public function reject(TaskSuggestion $suggestion, User $rejector, string $reason = ''): void
    {
        $suggestion->update([
            'status'           => 'rejected',
            'approved_by'      => $rejector->id,
            'approved_at'      => now(),
            'rejection_reason' => $reason,
        ]);
    }

    /**
     * Bulk approve all pending suggestions from a briefing.
     * Returns array of created Task models.
     */
    public function bulkApprove(Collection $suggestions, User $approver): array
    {
        $tasks = [];
        foreach ($suggestions as $suggestion) {
            if ($suggestion->isPending()) {
                $tasks[] = $this->approve($suggestion, $approver);
            }
        }
        return $tasks;
    }

    private function resolveClientId(string $orgId, ?string $clientName): ?string
    {
        if (!$clientName) {
            return null;
        }

        return Client::where('organization_id', $orgId)
            ->where(fn($q) => $q->where('name', 'ilike', "%{$clientName}%")
                ->orWhere('company', 'ilike', "%{$clientName}%"))
            ->value('id');
    }

    private function resolveProjectId(string $orgId, ?string $clientId, ?string $projectName): ?string
    {
        if (!$projectName) {
            return null;
        }

        $query = Project::where('organization_id', $orgId)
            ->whereNotIn('status', ['completed', 'cancelled', 'archived'])
            ->where('name', 'ilike', "%{$projectName}%");

        if ($clientId) {
            $query->where('client_id', $clientId);
        }

        return $query->value('id');
    }

    private function normalizePriority(string $priority): string
    {
        return in_array($priority, ['low', 'medium', 'high', 'urgent']) ? $priority : 'medium';
    }

    private function parseDate(?string $date): ?string
    {
        if (!$date) {
            return null;
        }
        try {
            return \Carbon\Carbon::parse($date)->toDateString();
        } catch (\Exception $e) {
            return null;
        }
    }
}

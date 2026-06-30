<?php

namespace App\Modules\TaskEngine\Services;

use App\Modules\TaskEngine\Models\RecurringTaskRule;
use App\Modules\ProjectManagement\Models\Task;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecurringTaskEngine
{
    public function __construct(private AutoAssignmentEngine $autoAssigner) {}

    public function spawnDue(string $orgId): int
    {
        $rules = RecurringTaskRule::where('organization_id', $orgId)
            ->where('is_active', true)
            ->where('next_spawn_at', '<=', now())
            ->get();

        $spawned = 0;
        foreach ($rules as $rule) {
            DB::transaction(function () use ($rule, &$spawned) {
                $this->spawnTask($rule);
                $rule->update([
                    'last_spawned_at' => now(),
                    'next_spawn_at'   => $this->calculateNextSpawn($rule),
                ]);
                $spawned++;
            });
        }

        return $spawned;
    }

    private function spawnTask(RecurringTaskRule $rule)
    {
        $dueDate = $rule->sla_hours
            ? now()->addHours($rule->sla_hours)
            : $this->calculateNextSpawn($rule);

        if ($rule->target_type === 'project' && $rule->target_template_id) {
            $template = \App\Modules\ProjectManagement\Models\ProjectTemplate::find($rule->target_template_id);
            if ($template) {
                $project = \App\Modules\ProjectManagement\Models\Project::create([
                    'organization_id' => $rule->organization_id,
                    'client_id'       => $rule->client_id,
                    'name'            => $rule->title,
                    'description'     => $rule->description,
                    'start_date'      => now(),
                    'end_date'        => $dueDate,
                    'status'          => 'planning',
                    'priority'        => $rule->priority,
                    'type'            => $template->service_type,
                ]);

                foreach ($template->tasks ?? [] as $taskDef) {
                    $taskDueDate = now()->addDays($taskDef['offset_days'] ?? 0);
                    $priority = ($taskDef['priority'] ?? 'medium') === 'urgent' ? 'critical' : ($taskDef['priority'] ?? 'medium');
                    Task::create([
                        'organization_id' => $rule->organization_id,
                        'project_id'      => $project->id,
                        'created_by'      => $rule->created_by,
                        'title'           => $taskDef['title'],
                        'priority'        => $priority,
                        'due_date'        => $taskDueDate,
                        'estimated_hours' => $taskDef['estimated_hours'] ?? null,
                        'status'          => 'todo',
                    ]);
                }
                return $project;
            }
        } elseif ($rule->target_type === 'audit_checklist') {
            $items = [
                ['id' => '1', 'label' => 'Initial Review', 'checked' => false, 'notes' => ''],
                ['id' => '2', 'label' => 'Detailed Audit', 'checked' => false, 'notes' => ''],
                ['id' => '3', 'label' => 'Final Report', 'checked' => false, 'notes' => ''],
            ];
            
            return \App\Modules\ProjectManagement\Models\AuditChecklist::create([
                'organization_id' => $rule->organization_id,
                'title'           => $rule->title,
                'type'            => 'general',
                'client_id'       => $rule->client_id,
                'project_id'      => $rule->project_id,
                'due_date'        => $dueDate,
                'items'           => $items,
            ]);
        }

        $assignee = $rule->role_required
            ? $this->autoAssigner->findBestAssignee($rule->organization_id, $rule->role_required)
            : null;

        return Task::create([
            'organization_id' => $rule->organization_id,
            'client_id'       => $rule->client_id,
            'project_id'      => $rule->project_id,
            'title'           => $rule->title,
            'description'     => $rule->description,
            'type'            => $rule->type,
            'role_required'   => $rule->role_required,
            'priority'        => $rule->priority,
            'status'          => 'todo',
            'assigned_to'     => $assignee?->id,
            'due_date'        => $dueDate,
            'estimated_hours' => $rule->estimated_hours,
            'meta'            => ['spawned_from_rule' => $rule->id],
        ]);
    }

    private function calculateNextSpawn(RecurringTaskRule $rule): Carbon
    {
        $base = now();

        return match($rule->frequency) {
            'daily'     => $base->addDay(),
            'weekly'    => $base->addWeek(),
            'monthly'   => $base->addMonth(),
            'quarterly' => $base->addMonths(3),
            default     => $base->addMonth(),
        };
    }
}

<?php

namespace App\Modules\Reporting\Templates;

class SprintReportTemplate implements ReportTemplateInterface
{
    public function getSections(): array
    {
        return [
            [
                'id' => 'sprint_goal',
                'title' => 'Sprint Goal & Overview',
                'description' => 'What this sprint set out to accomplish and whether the goal was achieved.',
            ],
            [
                'id' => 'velocity',
                'title' => 'Sprint Velocity & Story Points',
                'description' => 'Velocity comparison, planned vs completed story points/tasks.',
            ],
            [
                'id' => 'completed_tasks',
                'title' => 'Completed Tasks & Features',
                'description' => 'Deliverables shipped or moved to review stage.',
            ],
            [
                'id' => 'carried_over',
                'title' => 'Incomplete & Carried Over Tasks',
                'description' => 'Remaining backlog items, why they slipped, and mitigation plans.',
            ],
            [
                'id' => 'blockers',
                'title' => 'Team Blockers & Key Risks',
                'description' => 'Core impediments identified during the sprint cycle.',
            ],
            [
                'id' => 'next_sprint_plan',
                'title' => 'Next Sprint Planning Focus',
                'description' => 'Priority themes and key deliverables for the upcoming sprint.',
            ]
        ];
    }

    public function getRequiredMetrics(): array
    {
        return [
            'sprint_velocity_planned',
            'sprint_velocity_actual',
            'sprint_tasks_completed',
            'sprint_tasks_carried_over',
        ];
    }

    public function getDefaultDateRange(): string
    {
        return 'last_7d';
    }
}

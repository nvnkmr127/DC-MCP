<?php
namespace App\Modules\Revenue\Services;

use App\Modules\ProjectManagement\Models\Client;
use App\Modules\Revenue\Models\ClientOnboarding;

class OnboardingService
{
    const STAGES = [
        'prospect_won',
        'kickoff_scheduled',
        'kickoff_done',
        'access_shared',
        'sow_signed',
        'first_deliverable_sent',
        'active',
    ];

    const DEFAULT_CHECKLIST = [
        'prospect_won'            => ['Send welcome email', 'Collect business details', 'Schedule kickoff call'],
        'kickoff_scheduled'       => ['Send calendar invite', 'Prepare kickoff deck', 'Share onboarding guide'],
        'kickoff_done'            => ['Share meeting notes', 'Confirm deliverables list', 'Agree on communication channel'],
        'access_shared'           => ['Get Google Analytics access', 'Get ad account access', 'Get social media access', 'Get website access'],
        'sow_signed'              => ['Send SOW for review', 'Collect signed SOW', 'Set up project in DC-MCP'],
        'first_deliverable_sent'  => ['Deliver first report/creative', 'Collect feedback', 'Make revisions'],
        'active'                  => ['Set up recurring tasks', 'Schedule monthly review call'],
    ];

    public function createForClient(Client $client, array $data = []): ClientOnboarding
    {
        $stage     = 'prospect_won';
        $checklist = array_map(fn($title) => ['title' => $title, 'done' => false], self::DEFAULT_CHECKLIST[$stage]);

        return ClientOnboarding::create([
            'organization_id' => $client->organization_id,
            'client_id'       => $client->id,
            'stage'           => $stage,
            'checklist'       => $checklist,
            'target_go_live'  => $data['target_go_live'] ?? null,
            'assigned_to'     => $data['assigned_to'] ?? null,
            'notes'           => $data['notes'] ?? null,
        ]);
    }

    public function advanceStage(ClientOnboarding $onboarding): ClientOnboarding
    {
        $currentIndex = array_search($onboarding->stage, self::STAGES);
        if ($currentIndex === false || $currentIndex >= count(self::STAGES) - 1) {
            return $onboarding;
        }

        $nextStage = self::STAGES[$currentIndex + 1];
        $checklist = array_map(fn($title) => ['title' => $title, 'done' => false], self::DEFAULT_CHECKLIST[$nextStage] ?? []);

        $data = ['stage' => $nextStage, 'checklist' => $checklist];
        if ($nextStage === 'active') {
            $data['actual_go_live'] = now()->toDateString();
        }

        $onboarding->update($data);
        return $onboarding->fresh();
    }

    public function toggleChecklistItem(ClientOnboarding $onboarding, int $index): ClientOnboarding
    {
        $checklist = $onboarding->checklist ?? [];
        if (isset($checklist[$index])) {
            $checklist[$index]['done'] = !($checklist[$index]['done'] ?? false);
            $onboarding->update(['checklist' => $checklist]);
        }
        return $onboarding->fresh();
    }
}

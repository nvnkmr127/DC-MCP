<?php
namespace App\Modules\ProjectManagement\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\ProjectManagement\Models\Client;
use App\Modules\Revenue\Models\ClientOnboarding;
use App\Modules\Revenue\Services\OnboardingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingWebController extends Controller
{
    public function __construct(private OnboardingService $onboardingService) {}

    public function index(Request $request): Response
    {
        $orgId = $request->user()->organization_id;

        $onboardingsRaw = ClientOnboarding::where('organization_id', $orgId)
            ->with(['client:id,name,company', 'assignee:id,name'])
            ->orderBy('created_at')
            ->get();

        $totalActive = $onboardingsRaw->count();
        $stalled = $onboardingsRaw->filter(function ($o) {
            return $o->updated_at && now()->diffInDays($o->updated_at) > 5;
        })->count();

        $onboardings = $onboardingsRaw->map(fn($o) => [
            'id'              => $o->id,
            'stage'           => $o->stage,
            'checklist'       => $o->checklist ?? [],
            'progress'        => $o->checklistProgress(),
            'notes'           => $o->notes,
            'target_go_live'  => $o->target_go_live?->toDateString(),
            'actual_go_live'  => $o->actual_go_live?->toDateString(),
            'days_in_stage'   => $o->updated_at ? now()->diffInDays($o->updated_at) : 0,
            'nps_score'       => $o->nps_score,
            'nps_comment'     => $o->nps_comment,
            'client'          => $o->client ? [
                'id'           => $o->client->id, 
                'name'         => $o->client->name,
                'company_name' => $o->client->company,
            ] : null,
            'assignee'        => $o->assignee ? ['id' => $o->assignee->id, 'name' => $o->assignee->name] : null,
        ]);

        // Clients without onboarding
        $onboardedClientIds = $onboardingsRaw->pluck('client.id')->filter()->toArray();
        $availableClients = Client::where('organization_id', $orgId)
            ->where('status', 'active')
            ->whereNotIn('id', $onboardedClientIds)
            ->select('id', 'name', 'company')
            ->orderBy('name')
            ->get()
            ->map(fn($c) => [
                'id'           => $c->id,
                'name'         => $c->name,
                'company_name' => $c->company,
            ]);

        $team = User::where('organization_id', $orgId)
            ->where('is_active', true)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $byStage = collect(OnboardingService::STAGES)->mapWithKeys(fn($s) => [
            $s => $onboardings->where('stage', $s)->values(),
        ]);

        return Inertia::render('Onboarding/Index', [
            'onboardings' => $onboardings,
            'byStage'     => $byStage,
            'clients'     => $availableClients,
            'totalActive' => $totalActive,
            'stalled'     => $stalled,
            'team'        => $team,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'client_id'      => 'required|uuid',
            'target_go_live' => 'nullable|date',
            'assigned_to'    => 'nullable|uuid',
            'notes'          => 'nullable|string|max:1000',
        ]);

        $client     = Client::where('organization_id', $request->user()->organization_id)->findOrFail($validated['client_id']);
        $onboarding = $this->onboardingService->createForClient($client, $validated);

        $displayName = $client->company ?? $client->name;
        return back()->with('success', "Onboarding started for {$displayName}.");
    }

    public function advance(Request $request, ClientOnboarding $onboarding): RedirectResponse
    {
        $this->authorizeOrg($onboarding);
        $this->onboardingService->advanceStage($onboarding);
        return back()->with('success', 'Onboarding stage advanced.');
    }

    public function toggleChecklist(Request $request, ClientOnboarding $onboarding): RedirectResponse
    {
        $this->authorizeOrg($onboarding);
        $validated = $request->validate(['index' => 'required|integer|min:0']);
        $this->onboardingService->toggleChecklistItem($onboarding, $validated['index']);
        return back();
    }

    public function submitNps(Request $request, ClientOnboarding $onboarding): RedirectResponse
    {
        $this->authorizeOrg($onboarding);
        $validated = $request->validate([
            'nps_score'   => 'required|integer|min:0|max:10',
            'nps_comment' => 'nullable|string|max:1000',
        ]);
        $onboarding->update($validated);
        return back()->with('success', 'NPS score saved.');
    }
}

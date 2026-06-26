<?php

namespace App\Modules\ProjectManagement\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

use App\Modules\ProjectManagement\Models\Client;
use App\Modules\ProjectManagement\Services\ClientService;
use App\Modules\Revenue\Models\Proposal;
use App\Modules\Revenue\Models\ProposalLineItem;
use App\Modules\Revenue\Models\ClientSow;
use App\Modules\Revenue\Models\SowDeliverable;
use App\Modules\Revenue\Services\OnboardingService;

class ClientOnboardingWebController extends Controller
{
    public function __construct(
        protected ClientService $clientService,
        protected OnboardingService $onboardingService
    ) {}

    public function index(Request $request): Response
    {
        if (!$request->user()->hasPermission('create', 'client')) {
            abort(403);
        }

        return Inertia::render('Clients/OnboardWizard');
    }

    public function store(Request $request): RedirectResponse
    {
        if (!$request->user()->hasPermission('create', 'client')) {
            abort(403);
        }

        $validated = $request->validate([
            // Client Data
            'client.name'           => 'required|string|max:255',
            'client.email'          => 'required|email|max:255',
            'client.phone'          => 'nullable|string|max:50',
            'client.website'        => 'nullable|url|max:255',
            'client.tier'           => 'required|in:enterprise,mid-market,smb',
            'client.status'         => 'required|in:active,inactive,lead',
            'client.company'        => 'nullable|string|max:255',
            
            // SOW Data
            'sow.title'             => 'required|string|max:255',
            'sow.description'       => 'nullable|string',
            'sow.start_date'        => 'nullable|date',
            'sow.end_date'          => 'nullable|date|after_or_equal:sow.start_date',
            'sow.deliverables'      => 'nullable|array',
            'sow.deliverables.*.title'        => 'required_with:sow.deliverables|string',
            'sow.deliverables.*.service_type' => 'required_with:sow.deliverables|string',
            'sow.deliverables.*.frequency'    => 'required_with:sow.deliverables|in:one_time,weekly,monthly,quarterly',
            'sow.deliverables.*.quantity'     => 'required_with:sow.deliverables|integer|min:1',
            
            // Proposal Data
            'proposal.title'        => 'required|string|max:255',
            'proposal.valid_until'  => 'nullable|date',
            'proposal.notes'        => 'nullable|string',
            'proposal.line_items'   => 'nullable|array',
            'proposal.line_items.*.service'     => 'required_with:proposal.line_items|string|max:255',
            'proposal.line_items.*.description' => 'nullable|string',
            'proposal.line_items.*.unit_price'  => 'required_with:proposal.line_items|numeric|min:0',
            'proposal.line_items.*.quantity'    => 'required_with:proposal.line_items|numeric|min:0',
            'proposal.line_items.*.frequency'   => 'required_with:proposal.line_items|in:one_time,monthly,quarterly,annual',
            
            // Onboarding Data
            'onboarding.target_go_live' => 'nullable|date',
            'onboarding.assigned_to'    => 'nullable|uuid',
            'onboarding.notes'          => 'nullable|string|max:1000',
        ]);

        try {
            DB::beginTransaction();

            $orgId = $request->user()->organization_id;

            // 1. Create Client
            $client = $this->clientService->createClient($validated['client']);

            // 2. Create SOW
            $sow = ClientSow::create([
                'organization_id' => $orgId,
                'client_id'       => $client->id,
                'title'           => $validated['sow']['title'],
                'description'     => $validated['sow']['description'] ?? null,
                'status'          => 'draft',
                'start_date'      => $validated['sow']['start_date'] ?? null,
                'end_date'        => $validated['sow']['end_date'] ?? null,
                'created_by'      => $request->user()->id,
            ]);

            if (!empty($validated['sow']['deliverables'])) {
                foreach ($validated['sow']['deliverables'] as $del) {
                    SowDeliverable::create([
                        'organization_id'     => $orgId,
                        'sow_id'              => $sow->id,
                        'client_id'           => $client->id,
                        'title'               => $del['title'],
                        'service_type'        => $del['service_type'],
                        'frequency'           => $del['frequency'],
                        'quantity_per_period' => $del['quantity'],
                    ]);
                }
            }

            // 3. Create Proposal
            $subtotal = 0;
            if (!empty($validated['proposal']['line_items'])) {
                $subtotal = collect($validated['proposal']['line_items'])
                    ->sum(fn($li) => $li['unit_price'] * $li['quantity']);
            }

            $proposal = Proposal::create([
                'organization_id' => $orgId,
                'created_by'      => $request->user()->id,
                'title'           => $validated['proposal']['title'],
                'client_id'       => $client->id,
                'valid_until'     => $validated['proposal']['valid_until'] ?? null,
                'notes'           => $validated['proposal']['notes'] ?? null,
                'subtotal'        => $subtotal,
                'total_value'     => $subtotal,
            ]);
            
            // Link proposal to SOW
            $sow->update(['proposal_id' => $proposal->id]);

            if (!empty($validated['proposal']['line_items'])) {
                foreach ($validated['proposal']['line_items'] as $i => $li) {
                    ProposalLineItem::create([
                        'organization_id' => $orgId,
                        'proposal_id'     => $proposal->id,
                        'service'         => $li['service'],
                        'description'     => $li['description'] ?? null,
                        'unit_price'      => $li['unit_price'],
                        'quantity'        => $li['quantity'],
                        'frequency'       => $li['frequency'],
                        'sort_order'      => $i,
                    ]);
                }
            }

            // 4. Create Onboarding Checklist
            $onboardingData = $validated['onboarding'] ?? [];
            $this->onboardingService->createForClient($client, $onboardingData);

            DB::commit();

            return redirect()->route('web.clients.show', $client)->with('success', 'Client onboarding completed successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to complete onboarding: ' . $e->getMessage())->withInput();
        }
    }
}

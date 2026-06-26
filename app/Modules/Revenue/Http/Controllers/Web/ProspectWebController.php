<?php

namespace App\Modules\Revenue\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\Revenue\Models\Prospect;
use App\Modules\Revenue\Models\ProspectActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProspectWebController extends Controller
{
    public function index(Request $request): Response
    {
        $orgId = $request->user()->organization_id;

        $prospects = Prospect::where('organization_id', $orgId)
            ->with(['assignee:id,name', 'activities', 'convertedClient:id,name', 'convertedClient.proposals:id,client_id,title,status,total_value'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($p) => [
                'id'                  => $p->id,
                'company_name'        => $p->company_name,
                'contact_name'        => $p->contact_name,
                'contact_email'       => $p->contact_email,
                'source'              => $p->source,
                'stage'               => $p->stage,
                'estimated_value'     => (float) $p->estimated_value,
                'currency'            => $p->currency,
                'probability'         => $p->probability,
                'weighted_value'      => $p->weightedValue(),
                'services_interested' => $p->services_interested,
                'expected_close_date' => $p->expected_close_date?->toDateString(),
                'lost_reason'         => $p->lost_reason,
                'notes'               => $p->notes,
                'activities_count'    => $p->activities->count(),
                'last_activity_at'    => $p->activities->max('created_at'),
                'assignee'            => $p->assignee ? ['id' => $p->assignee->id, 'name' => $p->assignee->name] : null,
                'client'              => $p->convertedClient ? [
                    'id'   => $p->convertedClient->id,
                    'name' => $p->convertedClient->name,
                ] : null,
                'proposals'           => $p->convertedClient ? $p->convertedClient->proposals->map(fn($prop) => [
                    'id'          => $prop->id,
                    'title'       => $prop->title,
                    'status'      => $prop->status,
                    'total_value' => (float) $prop->total_value,
                ]) : [],
                'created_at'          => $p->created_at->toISOString(),
            ]);

        $stageOrder = ['lead', 'meeting_scheduled', 'proposal_sent', 'negotiation', 'won', 'lost'];
        $byStage = collect($stageOrder)->mapWithKeys(fn($s) => [
            $s => $prospects->where('stage', $s)->values(),
        ]);

        $totalPipeline   = $prospects->whereNotIn('stage', ['won', 'lost'])->sum('estimated_value');
        $weightedPipeline = $prospects->whereNotIn('stage', ['won', 'lost'])->sum('weighted_value');

        $team = User::where('organization_id', $orgId)
            ->where('is_active', true)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Pipeline/Index', [
            'prospects'        => $prospects,
            'byStage'          => $byStage,
            'totalPipeline'    => $totalPipeline,
            'weightedPipeline' => $weightedPipeline,
            'team'             => $team,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_name'        => 'required|string|max:255',
            'contact_name'        => 'nullable|string|max:255',
            'contact_email'       => 'nullable|email|max:255',
            'contact_phone'       => 'nullable|string|max:20',
            'source'              => 'required|in:referral,inbound,outbound,cold,event,social',
            'stage'               => 'required|in:lead,meeting_scheduled,proposal_sent,negotiation,won,lost',
            'estimated_value'     => 'nullable|numeric|min:0',
            'currency'            => 'nullable|string|size:3',
            'probability'         => 'nullable|integer|min:0|max:100',
            'services_interested' => 'nullable|array',
            'assigned_to'         => 'nullable|uuid',
            'expected_close_date' => 'nullable|date',
            'notes'               => 'nullable|string|max:2000',
        ]);

        Prospect::create([
            'organization_id' => $request->user()->organization_id,
            'currency'        => $validated['currency'] ?? 'INR',
            'probability'     => $validated['probability'] ?? 20,
            ...$validated,
        ]);

        return back()->with('success', "Prospect \"{$validated['company_name']}\" added.");
    }

    public function update(Request $request, Prospect $prospect): RedirectResponse
    {
        $this->authorizeOrg($prospect);

        $validated = $request->validate([
            'company_name'        => 'sometimes|string|max:255',
            'contact_name'        => 'sometimes|nullable|string|max:255',
            'contact_email'       => 'sometimes|nullable|email|max:255',
            'stage'               => 'sometimes|in:lead,meeting_scheduled,proposal_sent,negotiation,won,lost',
            'estimated_value'     => 'sometimes|nullable|numeric|min:0',
            'probability'         => 'sometimes|integer|min:0|max:100',
            'lost_reason'         => 'sometimes|nullable|string|max:255',
            'notes'               => 'sometimes|nullable|string|max:2000',
            'assigned_to'         => 'sometimes|nullable|uuid',
            'expected_close_date' => 'sometimes|nullable|date',
        ]);

        $prospect->update($validated);

        return back()->with('success', 'Prospect updated.');
    }

    public function destroy(Request $request, Prospect $prospect): RedirectResponse
    {
        $this->authorizeOrg($prospect);
        $prospect->delete();
        return back()->with('success', 'Prospect deleted.');
    }

    public function addActivity(Request $request, Prospect $prospect): RedirectResponse
    {
        $this->authorizeOrg($prospect);

        $validated = $request->validate([
            'type'         => 'required|in:call,email,meeting,proposal,follow_up,note',
            'note'         => 'required|string|max:2000',
            'scheduled_at' => 'nullable|date',
            'completed_at' => 'nullable|date',
        ]);

        ProspectActivity::create([
            'organization_id' => $request->user()->organization_id,
            'prospect_id'     => $prospect->id,
            'user_id'         => $request->user()->id,
            ...$validated,
        ]);

        return back()->with('success', 'Activity logged.');
    }

    public function convert(Request $request, Prospect $prospect): RedirectResponse
    {
        $this->authorizeOrg($prospect);

        if ($prospect->converted_client_id) {
            return back()->with('error', 'Prospect is already converted to a client.');
        }

        $client = \App\Modules\ProjectManagement\Models\Client::create([
            'organization_id' => $prospect->organization_id,
            'name'            => $prospect->company_name,
            'email'           => $prospect->contact_email,
            'phone'           => $prospect->contact_phone,
            'company'         => $prospect->company_name,
            'status'          => 'active',
            'tier'            => 'standard',
        ]);

        $prospect->update(['converted_client_id' => $client->id]);

        return back()->with('success', 'Prospect converted to client successfully.');
    }
}


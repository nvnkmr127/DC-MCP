<?php

namespace App\Modules\Revenue\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\ProjectManagement\Models\Client;
use App\Modules\Revenue\Models\ClientRetainer;
use App\Modules\Revenue\Models\ClientSow;
use App\Modules\Revenue\Models\SowDeliverable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SowWebController extends Controller
{
    public function index(Request $request): Response
    {
        $orgId = $request->user()->organization_id;

        $role = $request->user()->role ?? '';
        $canReview = in_array($role, ['ceo', 'project_manager']);

        $sows = ClientSow::where('organization_id', $orgId)
            ->with(['client:id,name,company', 'deliverables.latestSubmission.submitter:id,name', 'retainer:id,name'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($s) => [
                'id'               => $s->id,
                'title'            => $s->title,
                'description'      => $s->description,
                'status'           => $s->status,
                'start_date'       => $s->start_date?->toDateString(),
                'end_date'         => $s->end_date?->toDateString(),
                'deliverables'     => $s->deliverables->map(fn($d) => [
                    'id'                 => $d->id,
                    'title'              => $d->title,
                    'service_type'       => $d->service_type,
                    'frequency'          => $d->frequency,
                    'quantity_per_period'=> $d->quantity_per_period,
                    'notes'              => $d->notes,
                    'latest_submission'  => $d->latestSubmission ? [
                        'id'             => $d->latestSubmission->id,
                        'status'         => $d->latestSubmission->status,
                        'file_url'       => $d->latestSubmission->file_url,
                        'external_link'  => $d->latestSubmission->external_link,
                        'notes'          => $d->latestSubmission->notes,
                        'reviewer_notes' => $d->latestSubmission->reviewer_notes,
                        'revision_number'=> $d->latestSubmission->revision_number,
                        'reviewed_at'    => $d->latestSubmission->reviewed_at?->toISOString(),
                        'submitter'      => $d->latestSubmission->submitter ? [
                            'id'   => $d->latestSubmission->submitter->id,
                            'name' => $d->latestSubmission->submitter->name,
                        ] : null,
                    ] : null,
                ])->values(),
                'client'   => $s->client ? ['id' => $s->client->id, 'name' => $s->client->company ?? $s->client->name] : null,
                'retainer' => $s->retainer ? ['id' => $s->retainer->id, 'name' => $s->retainer->name] : null,
            ]);

        $clients = Client::where('organization_id', $orgId)
            ->where('status', 'active')
            ->select('id', 'name', 'company')
            ->orderBy('name')
            ->get();

        $retainers = ClientRetainer::where('organization_id', $orgId)
            ->where('status', 'active')
            ->select('id', 'name', 'client_id')
            ->orderBy('name')
            ->get();

        return Inertia::render('Sow/Index', [
            'sows'      => $sows,
            'clients'   => $clients,
            'retainers' => $retainers,
            'canReview' => $canReview,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'client_id'    => 'required|uuid',
            'retainer_id'  => 'nullable|uuid',
            'title'        => 'required|string|max:255',
            'description'  => 'nullable|string|max:2000',
            'start_date'   => 'required|date',
            'end_date'     => 'nullable|date|after:start_date',
            'deliverables' => 'array',
            'deliverables.*.title'              => 'required|string|max:255',
            'deliverables.*.service_type'       => 'required|in:seo,ads,social,content,design,dev,email,other',
            'deliverables.*.frequency'          => 'required|in:one_time,weekly,monthly,quarterly',
            'deliverables.*.quantity_per_period'=> 'nullable|integer|min:1',
        ]);

        $sow = ClientSow::create([
            'organization_id' => $request->user()->organization_id,
            'status'          => 'draft',
            ...$validated,
        ]);

        foreach ($validated['deliverables'] ?? [] as $d) {
            SowDeliverable::create([
                'organization_id'    => $request->user()->organization_id,
                'sow_id'             => $sow->id,
                'client_id'          => $validated['client_id'],
                'title'              => $d['title'],
                'service_type'       => $d['service_type'],
                'frequency'          => $d['frequency'],
                'quantity_per_period'=> $d['quantity_per_period'] ?? 1,
                'notes'              => $d['notes'] ?? null,
            ]);
        }

        return back()->with('success', "SOW \"{$sow->title}\" created.");
    }

    public function update(Request $request, ClientSow $sow): RedirectResponse
    {
        $this->authorizeOrg($sow);

        $validated = $request->validate([
            'title'       => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string|max:2000',
            'status'      => 'sometimes|in:draft,active,expired',
            'end_date'    => 'sometimes|nullable|date',
        ]);

        $sow->update($validated);

        return back()->with('success', 'SOW updated.');
    }

    public function destroy(Request $request, ClientSow $sow): RedirectResponse
    {
        $this->authorizeOrg($sow);
        $sow->delete();
        return back()->with('success', 'SOW deleted.');
    }
}

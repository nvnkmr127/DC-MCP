<?php

namespace App\Modules\ProjectManagement\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

use App\Modules\Revenue\Models\ClientSow;
use App\Modules\Revenue\Models\Proposal;
use App\Modules\Revenue\Models\Invoice;

use App\Modules\ProjectManagement\Models\Client;
use App\Modules\ProjectManagement\Services\ClientService;
use App\Modules\ProjectManagement\Http\Requests\StoreClientRequest;
use App\Modules\ProjectManagement\Http\Requests\UpdateClientRequest;

class ClientWebController extends Controller
{
    public function __construct(
        protected ClientService $clientService
    ) {}

    public function index(Request $request)
    {
        if (!$request->user()->hasPermission('view', 'client')) {
            abort(403);
        }

        $query = Client::withCount('projects')
            ->orderBy('name');

        if ($request->filled('tier')) {
            $query->where('tier', $request->tier);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $clients = $query->paginate(20)->through(fn($c) => [
            'id'             => $c->id,
            'name'           => $c->name,
            'email'          => $c->email,
            'phone'          => $c->phone,
            'website'        => $c->website,
            'tier'           => $c->tier,
            'status'         => $c->status,
            'projects_count' => $c->projects_count,
            'created_at'     => $c->created_at->toDateString(),
        ]);

        return Inertia::render('Clients/Index', [
            'clients' => $clients,
            'filters' => $request->only(['tier', 'status', 'search']),
        ]);
    }

    public function create(Request $request)
    {
        if (!$request->user()->hasPermission('create', 'client')) {
            abort(403);
        }
        return Inertia::render('Clients/Create');
    }

    public function store(StoreClientRequest $request)
    {
        $client = $this->clientService->createClient($request->validated());
        return redirect()->route('web.clients.show', $client)->with([
            'success' => 'Client created.',
            'show_wizard' => true
        ]);
    }

    public function show(Request $request, Client $client)
    {
        if (!$request->user()->hasPermission('view', 'client')) {
            abort(403);
        }

        $client->load(['projects' => fn($q) => $q->withCount('tasks')->orderByDesc('updated_at')]);

        $communications = \App\Modules\Revenue\Models\ClientCommunication::where('client_id', $client->id)
            ->with('user:id,name')
            ->orderByDesc('communicated_at')
            ->get()
            ->map(fn($c) => [
                'id'               => $c->id,
                'type'             => $c->type,
                'contact_person'   => $c->contact_person,
                'subject'          => $c->subject,
                'notes'            => $c->notes,
                'outcome'          => $c->outcome,
                'next_action'      => $c->next_action,
                'next_action_date' => $c->next_action_date?->toDateString(),
                'communicated_at'  => $c->communicated_at->toDateString(),
                'logged_by'        => $c->user?->name,
            ]);


        // Client Documents (Proposals)
        $proposals = Proposal::where('client_id', $client->id)
            ->with(['lineItems'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($p) => [
                'id'          => $p->id,
                'title'       => $p->title,
                'status'      => $p->status,
                'valid_until' => $p->valid_until?->toDateString(),
                'total_value' => (float) $p->total_value,
                'subtotal'    => (float) $p->subtotal,
                'discount'    => (float) $p->discount,
                'tax_amount'  => (float) $p->tax_amount,
                'notes'       => $p->notes,
                'sent_at'     => $p->sent_at?->toDateString(),
                'accepted_at' => $p->accepted_at?->toDateString(),
                'line_items'  => $p->lineItems->map(fn($li) => [
                    'id'          => $li->id,
                    'service'     => $li->service,
                    'description' => $li->description,
                    'unit_price'  => (float) $li->unit_price,
                    'quantity'    => (float) $li->quantity,
                    'frequency'   => $li->frequency,
                    'sort_order'  => $li->sort_order,
                ]),
            ]);

        // Client Documents (SOWs)
        $sows = ClientSow::where('client_id', $client->id)
            ->with(['deliverables.latestSubmission.submitter:id,name', 'retainer:id,name', 'proposal:id,title'])
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
                    'status'             => $d->status,
                    'latest_submission'  => $d->latestSubmission ? [
                        'id'           => $d->latestSubmission->id,
                        'status'       => $d->latestSubmission->status,
                        'submitted_at' => $d->latestSubmission->created_at->diffForHumans(),
                        'submitter'    => $d->latestSubmission->submitter?->name,
                    ] : null,
                ]),
                'retainer'         => $s->retainer ? ['id' => $s->retainer->id, 'name' => $s->retainer->name] : null,
                'proposal'         => $s->proposal ? ['id' => $s->proposal->id, 'title' => $s->proposal->title] : null,
            ]);

        $role = $request->user()->role ?? '';
        $canReview = in_array($role, ['ceo', 'project_manager']);

        $reports = \App\Modules\Revenue\Models\ClientReport::where('client_id', $client->id)
            ->orderByDesc('month_year')
            ->get()
            ->map(fn($r) => [
                'id'         => $r->id,
                'month_year' => $r->month_year,
                'status'     => $r->status,
                'highlights' => $r->highlights,
                'challenges' => $r->challenges,
                'metrics'    => $r->metrics ?? [],
            ]);

        $surveys = \App\Modules\Revenue\Models\ClientSurvey::where('client_id', $client->id)
            ->orderByDesc('sent_at')
            ->get()
            ->map(fn($s) => [
                'id'           => $s->id,
                'nps_score'    => $s->nps_score,
                'feedback'     => $s->feedback,
                'sent_at'      => $s->sent_at->toDateString(),
                'responded_at' => $s->responded_at?->toDateString(),
                'status'       => $s->status,
            ]);

        $invoices = Invoice::where('client_id', $client->id)
            ->orderByDesc('issue_date')
            ->get()
            ->map(fn($inv) => [
                'id'             => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'amount'         => (float) $inv->amount,
                'currency'       => $inv->currency,
                'status'         => $inv->status,
                'issue_date'     => $inv->issue_date?->toDateString(),
                'due_date'       => $inv->due_date?->toDateString(),
                'paid_at'        => $inv->paid_at?->toDateString(),
            ]);

        return Inertia::render('Clients/Show', [
            'proposals'      => $proposals,
            'sows'           => $sows,
            'canReview'      => $canReview,
            'client'         => $client,
            'communications' => $communications,
            'reports'        => $reports,
            'surveys'        => $surveys,
            'invoices'       => $invoices,
        ]);
    }

    public function edit(Request $request, Client $client)
    {
        if (!$request->user()->hasPermission('update', 'client')) {
            abort(403);
        }
        return Inertia::render('Clients/Edit', [
            'client' => $client,
        ]);
    }

    public function update(UpdateClientRequest $request, Client $client)
    {
        $client = $this->clientService->updateClient($client, $request->validated());
        return back()->with('success', 'Client updated.');
    }

    public function destroy(Request $request, Client $client)
    {
        if (!$request->user()->hasPermission('delete', 'client')) {
            abort(403);
        }
        $this->clientService->deleteClient($client);
        return redirect()->route('web.clients.index')->with('success', 'Client deleted.');
    }

    public function flagUpsell(Request $request, Client $client): \Illuminate\Http\RedirectResponse
    {
        if (!$request->user()->hasPermission('update', 'client')) {
            abort(403);
        }

        if ($request->boolean('clear')) {
            $client->update(['upsell_flagged' => false, 'upsell_notes' => null, 'upsell_potential' => null, 'upsell_flagged_at' => null]);
            return back()->with('success', 'Upsell flag cleared.');
        }

        $validated = $request->validate([
            'upsell_notes'     => 'nullable|string|max:1000',
            'upsell_potential' => 'nullable|numeric|min:0',
        ]);

        $client->update([
            'upsell_flagged'    => true,
            'upsell_notes'      => $validated['upsell_notes'] ?? null,
            'upsell_potential'  => $validated['upsell_potential'] ?? null,
            'upsell_flagged_at' => now(),
        ]);

        return back()->with('success', 'Client flagged for upsell.');
    }

    public function updateSuccessScore(Request $request, Client $client): \Illuminate\Http\RedirectResponse
    {
        if (!$request->user()->hasPermission('update', 'client')) {
            abort(403);
        }

        $validated = $request->validate([
            'overall_score' => 'required|integer|min:0|max:100',
        ]);

        $client->update(['success_score' => $validated['overall_score']]);
        return back()->with('success', 'Success score updated.');
    }
}

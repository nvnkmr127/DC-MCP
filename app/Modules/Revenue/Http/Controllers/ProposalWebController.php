<?php

namespace App\Modules\Revenue\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ProjectManagement\Models\Client;
use App\Modules\Revenue\Models\Proposal;
use App\Modules\Revenue\Models\ProposalLineItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProposalWebController extends Controller
{
    public function index(Request $request): Response
    {
        $orgId = $request->user()->organization_id;

        $proposals = Proposal::where('organization_id', $orgId)
            ->with(['client:id,name,company', 'lineItems'])
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
                'client'      => $p->client ? ['id' => $p->client->id, 'name' => $p->client->company ?? $p->client->name] : null,
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

        $totalSent     = $proposals->whereIn('status', ['sent', 'accepted', 'rejected'])->count();
        $acceptedValue = $proposals->where('status', 'accepted')->sum('total_value');
        $conversionRate = $totalSent > 0
            ? round(($proposals->where('status', 'accepted')->count() / $totalSent) * 100, 1)
            : 0;

        $clients = Client::where('organization_id', $orgId)->where('status', 'active')->select('id', 'name', 'company')->get();

        return Inertia::render('Proposals/Index', [
            'proposals'      => $proposals,
            'stats'          => ['total_sent' => $totalSent, 'accepted_value' => $acceptedValue, 'conversion_rate' => $conversionRate],
            'clients'        => $clients,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title'                    => 'required|string|max:255',
            'client_id'                => 'required|uuid|exists:clients,id',
            'valid_until'              => 'nullable|date',
            'notes'                    => 'nullable|string',
            'line_items'               => 'required|array|min:1',
            'line_items.*.service'     => 'required|string|max:255',
            'line_items.*.description' => 'nullable|string',
            'line_items.*.unit_price'  => 'required|numeric|min:0',
            'line_items.*.quantity'    => 'required|numeric|min:0',
            'line_items.*.frequency'   => 'required|in:one_time,monthly,quarterly,annual',
        ]);

        $subtotal = collect($validated['line_items'])->sum(fn($li) => $li['unit_price'] * $li['quantity']);
        $proposal = Proposal::create([
            'organization_id' => $request->user()->organization_id,
            'created_by'      => $request->user()->id,
            'title'           => $validated['title'],
            'client_id'       => $validated['client_id'],
            'valid_until'     => $validated['valid_until'] ?? null,
            'notes'           => $validated['notes'] ?? null,
            'subtotal'        => $subtotal,
            'total_value'     => $subtotal,
        ]);

        foreach ($validated['line_items'] as $i => $li) {
            ProposalLineItem::create([
                'organization_id' => $request->user()->organization_id,
                'proposal_id'     => $proposal->id,
                'service'         => $li['service'],
                'description'     => $li['description'] ?? null,
                'unit_price'      => $li['unit_price'],
                'quantity'        => $li['quantity'],
                'frequency'       => $li['frequency'],
                'sort_order'      => $i,
            ]);
        }

        return back()->with('success', 'Proposal created.');
    }

    public function show(Proposal $proposal): Response
    {
        $proposal->load(['lineItems', 'client:id,name,company']);
        return Inertia::render('Proposals/Show', ['proposal' => $proposal]);
    }

    public function update(Request $request, Proposal $proposal): RedirectResponse
    {
        abort_if($proposal->organization_id !== $request->user()->organization_id, 403);

        $validated = $request->validate([
            'title'                    => 'sometimes|string|max:255',
            'valid_until'              => 'nullable|date',
            'notes'                    => 'nullable|string',
            'line_items'               => 'sometimes|array',
            'line_items.*.service'     => 'required_with:line_items|string|max:255',
            'line_items.*.description' => 'nullable|string',
            'line_items.*.unit_price'  => 'required_with:line_items|numeric|min:0',
            'line_items.*.quantity'    => 'required_with:line_items|numeric|min:0',
            'line_items.*.frequency'   => 'required_with:line_items|in:one_time,monthly,quarterly,annual',
        ]);

        if (isset($validated['line_items'])) {
            $subtotal = collect($validated['line_items'])->sum(fn($li) => $li['unit_price'] * $li['quantity']);
            $proposal->lineItems()->delete();
            foreach ($validated['line_items'] as $i => $li) {
                ProposalLineItem::create([
                    'organization_id' => $request->user()->organization_id,
                    'proposal_id'     => $proposal->id,
                    'service'         => $li['service'],
                    'description'     => $li['description'] ?? null,
                    'unit_price'      => $li['unit_price'],
                    'quantity'        => $li['quantity'],
                    'frequency'       => $li['frequency'],
                    'sort_order'      => $i,
                ]);
            }
            $proposal->update(array_merge(
                array_diff_key($validated, ['line_items' => null]),
                ['subtotal' => $subtotal, 'total_value' => $subtotal]
            ));
        } else {
            $proposal->update(array_diff_key($validated, ['line_items' => null]));
        }

        return back()->with('success', 'Proposal updated.');
    }

    public function destroy(Request $request, Proposal $proposal): RedirectResponse
    {
        abort_if($proposal->organization_id !== $request->user()->organization_id, 403);
        $proposal->delete();
        return back()->with('success', 'Proposal deleted.');
    }

    public function markSent(Request $request, Proposal $proposal): RedirectResponse
    {
        abort_if($proposal->organization_id !== $request->user()->organization_id, 403);
        $proposal->update([
            'status'       => 'sent',
            'sent_at'      => now(),
            'public_token' => Str::random(60),
        ]);
        return back()->with('success', 'Proposal marked as sent.');
    }

    public function accept(Request $request, Proposal $proposal): RedirectResponse
    {
        abort_if($proposal->organization_id !== $request->user()->organization_id, 403);
        $proposal->update(['status' => 'accepted', 'accepted_at' => now()]);
        return back()->with('success', 'Proposal accepted.');
    }

    public function reject(Request $request, Proposal $proposal): RedirectResponse
    {
        abort_if($proposal->organization_id !== $request->user()->organization_id, 403);
        $proposal->update(['status' => 'rejected']);
        return back()->with('success', 'Proposal rejected.');
    }
}

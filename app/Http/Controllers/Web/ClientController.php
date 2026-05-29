<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Modules\ProjectManagement\Models\Client;

class ClientController extends Controller
{
    public function index(Request $request)
    {
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

    public function create()
    {
        return Inertia::render('Clients/Create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'    => 'required|string|max:200',
            'email'   => 'nullable|email',
            'phone'   => 'nullable|string',
            'website' => 'nullable|url',
            'tier'    => 'required|in:standard,premium,enterprise',
            'status'  => 'required|in:active,inactive,prospect',
            'notes'   => 'nullable|string',
        ]);

        $client = Client::create([
            ...$data,
            'organization_id' => $request->user()->organization_id,
        ]);

        return redirect()->route('web.clients.show', $client)->with('success', 'Client created.');
    }

    public function show(Client $client)
    {
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

        return Inertia::render('Clients/Show', [
            'client'         => $client,
            'communications' => $communications,
        ]);
    }

    public function edit(Client $client)
    {
        return Inertia::render('Clients/Edit', [
            'client' => $client,
        ]);
    }

    public function update(Request $request, Client $client)
    {
        $data = $request->validate([
            'name'    => 'sometimes|string|max:200',
            'email'   => 'nullable|email',
            'phone'   => 'nullable|string',
            'website' => 'nullable|url',
            'tier'    => 'sometimes|in:standard,premium,enterprise',
            'status'  => 'sometimes|in:active,inactive,prospect',
            'notes'   => 'nullable|string',
        ]);

        $client->update($data);
        return back()->with('success', 'Client updated.');
    }

    public function destroy(Client $client)
    {
        $client->delete();
        return redirect()->route('web.clients.index')->with('success', 'Client deleted.');
    }

    public function flagUpsell(Request $request, Client $client): \Illuminate\Http\RedirectResponse
    {
        abort_if($client->organization_id !== $request->user()->organization_id, 403);

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
        abort_if($client->organization_id !== $request->user()->organization_id, 403);

        $validated = $request->validate([
            'overall_score' => 'required|integer|min:0|max:100',
        ]);

        $client->update(['success_score' => $validated['overall_score']]);
        return back()->with('success', 'Success score updated.');
    }
}

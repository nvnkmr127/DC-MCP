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

        return Inertia::render('Clients/Show', [
            'client' => $client,
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
}

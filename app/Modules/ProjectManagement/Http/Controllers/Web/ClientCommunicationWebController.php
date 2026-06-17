<?php

namespace App\Modules\ProjectManagement\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\ProjectManagement\Models\Client;
use App\Modules\Revenue\Models\ClientCommunication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ClientCommunicationWebController extends Controller
{
    public function store(Request $request, Client $client): RedirectResponse
    {
        $this->authorizeOrg($client);

        $validated = $request->validate([
            'type'             => 'required|in:call,email,whatsapp,meeting,linkedin,other',
            'contact_person'   => 'nullable|string|max:200',
            'subject'          => 'required|string|max:255',
            'notes'            => 'required|string',
            'outcome'          => 'nullable|string|max:500',
            'next_action'      => 'nullable|string|max:500',
            'next_action_date' => 'nullable|date',
            'communicated_at'  => 'required|date',
        ]);

        ClientCommunication::create([
            'organization_id' => $request->user()->organization_id,
            'client_id'       => $client->id,
            'user_id'         => $request->user()->id,
            ...$validated,
        ]);

        return back()->with('success', 'Communication logged.');
    }

    public function update(Request $request, ClientCommunication $communication): RedirectResponse
    {
        $this->authorizeOrg($communication);

        $validated = $request->validate([
            'subject'          => 'sometimes|string|max:255',
            'notes'            => 'sometimes|string',
            'outcome'          => 'sometimes|nullable|string|max:500',
            'next_action'      => 'sometimes|nullable|string|max:500',
            'next_action_date' => 'sometimes|nullable|date',
        ]);

        $communication->update($validated);
        return back()->with('success', 'Communication updated.');
    }

    public function destroy(Request $request, ClientCommunication $communication): RedirectResponse
    {
        $this->authorizeOrg($communication);
        $communication->delete();
        return back()->with('success', 'Communication deleted.');
    }
}

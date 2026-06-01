<?php

namespace App\Modules\ClientPortal\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ProjectManagement\Models\Client;
use App\Modules\Revenue\Models\ClientSurvey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ClientSurveyWebController extends Controller
{
    public function index(Request $request): Response
    {
        $orgId = $request->user()->organization_id;

        $surveys = ClientSurvey::where('organization_id', $orgId)
            ->with('client:id,name,company')
            ->orderByDesc('sent_at')
            ->get()
            ->map(fn($s) => [
                'id'           => $s->id,
                'nps_score'    => $s->nps_score,
                'feedback'     => $s->feedback,
                'sent_at'      => $s->sent_at->toDateString(),
                'responded_at' => $s->responded_at?->toDateString(),
                'status'       => $s->status,
                'client'       => $s->client ? ['id' => $s->client->id, 'name' => $s->client->company ?? $s->client->name] : null,
            ]);

        $responded = $surveys->where('status', 'responded');
        $avgScore  = $responded->count() > 0 ? round($responded->avg('nps_score'), 1) : null;
        $promoters = $responded->where('nps_score', '>=', 9)->count();
        $passives  = $responded->whereBetween('nps_score', [7, 8])->count();
        $detractors= $responded->where('nps_score', '<', 7)->count();

        $clients = Client::where('organization_id', $orgId)->where('status', 'active')->select('id', 'name', 'company')->get();

        return Inertia::render('ClientSurveys/Index', [
            'surveys'    => $surveys,
            'clients'    => $clients,
            'npsStats'   => ['avg' => $avgScore, 'promoters' => $promoters, 'passives' => $passives, 'detractors' => $detractors],
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'client_id' => 'required|uuid|exists:clients,id',
        ]);

        ClientSurvey::create([
            'organization_id' => $request->user()->organization_id,
            'client_id'       => $validated['client_id'],
            'sent_by'         => $request->user()->id,
            'public_token'    => Str::random(60),
            'sent_at'         => now(),
        ]);

        return back()->with('success', 'Survey sent.');
    }

    public function showForm(string $token): Response
    {
        $survey = ClientSurvey::where('public_token', $token)->with('client:id,name')->firstOrFail();
        return Inertia::render('Survey/Respond', [
            'survey'   => ['id' => $survey->id, 'status' => $survey->status, 'client_name' => $survey->client?->name ?? ''],
            'token'    => $token,
            'submitted'=> $survey->status === 'responded',
        ]);
    }

    public function respond(Request $request, string $token): RedirectResponse
    {
        $survey = ClientSurvey::where('public_token', $token)->firstOrFail();

        $validated = $request->validate([
            'nps_score' => 'required|integer|min:0|max:10',
            'feedback'  => 'nullable|string|max:2000',
        ]);

        $survey->update([
            'nps_score'    => $validated['nps_score'],
            'feedback'     => $validated['feedback'] ?? null,
            'responded_at' => now(),
            'status'       => 'responded',
        ]);

        return back()->with('success', 'Thank you for your feedback!');
    }

    public function destroy(Request $request, ClientSurvey $survey): RedirectResponse
    {
        abort_if($survey->organization_id !== $request->user()->organization_id, 403);
        $survey->delete();
        return back()->with('success', 'Survey deleted.');
    }
}

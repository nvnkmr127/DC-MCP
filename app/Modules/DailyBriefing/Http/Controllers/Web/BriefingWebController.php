<?php

namespace App\Modules\DailyBriefing\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Modules\DailyBriefing\Models\DailyBriefing;
use App\Modules\DailyBriefing\Jobs\GenerateDailyBriefingJob;
use App\Modules\ProjectManagement\Models\Client;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\TaskEngine\Models\TaskSuggestion;

class BriefingWebController extends Controller
{
    public function index(Request $request)
    {
        $briefings = DailyBriefing::where('user_id', $request->user()->id)
            ->orderByDesc('date')
            ->paginate(20)
            ->through(fn($b) => [
                'id'          => $b->id,
                'date'        => $b->date->toDateString(),
                'status'      => $b->status,
                'digest_text' => $b->digest_text ? substr($b->digest_text, 0, 200) : null,
                'digest_html' => null, // don't send full HTML in list
                'delivered_at' => $b->delivered_at?->toISOString(),
            ]);

        return Inertia::render('Briefings/Index', [
            'briefings' => $briefings,
        ]);
    }

    public function show(DailyBriefing $briefing)
    {
        if ($briefing->user_id !== auth()->id()) {
            abort(403);
        }

        $briefing->load(['suggestions.project', 'suggestions.client', 'suggestions.approver', 'suggestions.task']);

        $orgId = auth()->user()->organization_id;

        $projects = Project::where('organization_id', $orgId)
            ->whereNotIn('status', ['completed', 'cancelled', 'archived'])
            ->select('id', 'name', 'client_id')
            ->orderBy('name')
            ->get();

        $clients = Client::where('organization_id', $orgId)
            ->where('status', 'active')
            ->select('id', 'name', 'company')
            ->orderBy('name')
            ->get();

        $formattedSuggestions = $briefing->suggestions->map(function ($s) {
            return [
                'id'               => $s->id,
                'title'            => $s->title,
                'description'      => $s->description,
                'role_required'    => $s->role_required,
                'priority'         => $s->priority,
                'due_date'         => $s->due_date?->toDateString(),
                'estimated_hours'  => $s->estimated_hours,
                'status'           => $s->status,
                'suggested_by'     => $s->suggested_by,
                'reasoning'        => $s->meta['reasoning'] ?? null,
                'rejection_reason' => $s->rejection_reason,
                'approved_at'      => $s->approved_at?->toISOString(),
                'created_at'       => $s->created_at->toISOString(),
                'project'          => $s->project ? ['id' => $s->project->id, 'name' => $s->project->name] : null,
                'client'           => $s->client  ? ['id' => $s->client->id,  'name' => $s->client->company ?? $s->client->name] : null,
                'approver'         => $s->approver ? ['id' => $s->approver->id, 'name' => $s->approver->name] : null,
                'task'             => $s->task ? ['id' => $s->task->id, 'title' => $s->task->title, 'status' => $s->task->status] : null,
            ];
        });

        return Inertia::render('Briefings/Show', [
            'briefing' => [
                'id'          => $briefing->id,
                'date'        => $briefing->date->toDateString(),
                'status'      => $briefing->status,
                'digest_text' => $briefing->digest_text,
                'digest_html' => $briefing->digest_html,
                'delivered_at' => $briefing->delivered_at?->toISOString(),
            ],
            'suggestions' => $formattedSuggestions,
            'projects' => $projects,
            'clients' => $clients,
        ]);
    }

    public function generate(Request $request)
    {
        $user = $request->user();
        $today = now()->toDateString();

        $briefing = DailyBriefing::firstOrCreate(
            ['user_id' => $user->id, 'date' => $today],
            [
                'organization_id' => $user->organization_id,
                'status'          => 'pending',
            ]
        );

        if (in_array($briefing->status, ['generating', 'ready', 'delivered'])) {
            return back()->with('success', 'Briefing already ' . $briefing->status . '.');
        }

        $briefing->update(['status' => 'generating']);
        GenerateDailyBriefingJob::dispatch($user, $today);

        return back()->with('success', 'Briefing generation started.');
    }
}

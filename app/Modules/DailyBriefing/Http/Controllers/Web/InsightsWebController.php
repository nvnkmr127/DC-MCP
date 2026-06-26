<?php

namespace App\Modules\DailyBriefing\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Modules\DailyBriefing\Models\DailyBriefing;
use App\Modules\ProjectManagement\Models\Client;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\TaskEngine\Models\TaskSuggestion;

class InsightsWebController extends Controller
{
    public function index(Request $request)
    {
        $user  = $request->user();
        $orgId = $user->organization_id;

        // Fetch Briefings
        $briefings = DailyBriefing::where('user_id', $user->id)
            ->orderByDesc('date')
            ->paginate(20)
            ->through(fn($b) => [
                'id'          => $b->id,
                'date'        => $b->date->toDateString(),
                'status'      => $b->status,
                'digest_text' => $b->digest_text ? substr($b->digest_text, 0, 200) : null,
                'delivered_at' => $b->delivered_at?->toISOString(),
            ]);

        // Fetch Suggestions
        $pending = TaskSuggestion::where('organization_id', $orgId)
            ->where('status', 'pending')
            ->with(['project:id,name', 'client:id,name,company'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($s) => $this->formatSuggestion($s));

        $recent = TaskSuggestion::where('organization_id', $orgId)
            ->whereIn('status', ['approved', 'rejected', 'modified'])
            ->with(['project:id,name', 'client:id,name,company', 'approver:id,name', 'task:id,title,status'])
            ->orderByDesc('approved_at')
            ->limit(30)
            ->get()
            ->map(fn($s) => $this->formatSuggestion($s));

        $suggestionStats = [
            'pending_count'  => $pending->count(),
            'approved_today' => TaskSuggestion::where('organization_id', $orgId)
                ->where('status', 'approved')
                ->whereDate('approved_at', today())
                ->count(),
            'rejected_today' => TaskSuggestion::where('organization_id', $orgId)
                ->where('status', 'rejected')
                ->whereDate('approved_at', today())
                ->count(),
        ];

        // Projects & clients for the edit dropdown (Suggestions)
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

        return Inertia::render('Insights/Index', [
            'briefings'       => $briefings,
            'pending'         => $pending,
            'recent'          => $recent,
            'suggestionStats' => $suggestionStats,
            'projects'        => $projects,
            'clients'         => $clients,
        ]);
    }

    private function formatSuggestion(TaskSuggestion $s): array
    {
        return [
            'id'          => $s->id,
            'source'      => $s->source,
            'title'       => $s->title,
            'description' => $s->description,
            'priority'    => $s->priority,
            'due_date'    => $s->due_date?->toDateString(),
            'status'      => $s->status,
            'metadata'    => $s->metadata,
            'created_at'  => $s->created_at->diffForHumans(),
            'approved_at' => $s->approved_at?->diffForHumans(),
            'project'     => $s->project ? ['id' => $s->project->id, 'name' => $s->project->name] : null,
            'client'      => $s->client ? ['id' => $s->client->id, 'name' => $s->client->name] : null,
            'approver'    => $s->approver ? ['id' => $s->approver->id, 'name' => $s->approver->name] : null,
            'task'        => $s->task ? ['id' => $s->task->id, 'title' => $s->task->title, 'status' => $s->task->status] : null,
        ];
    }
}

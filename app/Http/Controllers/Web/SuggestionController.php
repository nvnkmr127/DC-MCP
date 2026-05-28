<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\ProjectManagement\Models\Client;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\TaskEngine\Models\TaskSuggestion;
use App\Modules\TaskEngine\Services\TaskSuggestionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SuggestionController extends Controller
{
    public function __construct(private TaskSuggestionService $suggestionService) {}

    public function index(Request $request): Response
    {
        $user  = $request->user();
        $orgId = $user->organization_id;

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

        $stats = [
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

        // Projects & clients for the edit dropdown
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

        return Inertia::render('Suggestions/Index', [
            'pending'  => $pending,
            'recent'   => $recent,
            'stats'    => $stats,
            'projects' => $projects,
            'clients'  => $clients,
        ]);
    }

    public function approve(Request $request, TaskSuggestion $suggestion): RedirectResponse
    {
        $this->authorizeSuggestion($suggestion, $request);

        $validated = $request->validate([
            'title'       => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'priority'    => 'sometimes|in:low,medium,high,urgent',
            'due_date'    => 'sometimes|nullable|date',
            'project_id'  => 'sometimes|nullable|uuid',
        ]);

        $task = $this->suggestionService->approve($suggestion, $request->user(), $validated);

        return back()->with('success', "Task \"{$task->title}\" created" .
            ($task->assignee ? " and assigned to {$task->assignee->name}" : '') . '.');
    }

    public function reject(Request $request, TaskSuggestion $suggestion): RedirectResponse
    {
        $this->authorizeSuggestion($suggestion, $request);

        $validated = $request->validate([
            'reason' => 'sometimes|nullable|string|max:500',
        ]);

        $this->suggestionService->reject($suggestion, $request->user(), $validated['reason'] ?? '');

        return back()->with('success', 'Suggestion dismissed.');
    }

    /**
     * Create a task suggestion from a client email (Zoho Mail → Task flow).
     * CEO hits "Create Task" on an email in the briefing.
     */
    public function fromEmail(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'subject'    => 'required|string|max:255',
            'from'       => 'required|string|max:255',
            'body'       => 'nullable|string|max:2000',
            'client_id'  => 'nullable|uuid',
            'project_id' => 'nullable|uuid',
        ]);

        $user = $request->user();

        TaskSuggestion::create([
            'organization_id' => $user->organization_id,
            'title'           => "Follow up: {$validated['subject']}",
            'description'     => "Client email from {$validated['from']}.\n\n" . ($validated['body'] ?? ''),
            'client_id'       => $validated['client_id'] ?? null,
            'project_id'      => $validated['project_id'] ?? null,
            'role_required'   => 'project_manager',
            'priority'        => 'high',
            'suggested_by'    => 'zoho_mail',
            'status'          => 'pending',
            'meta'            => ['email_from' => $validated['from'], 'email_subject' => $validated['subject']],
        ]);

        return back()->with('success', 'Task suggestion created from email.');
    }

    public function bulkApprove(Request $request): RedirectResponse
    {
        $user  = $request->user();
        $orgId = $user->organization_id;

        $validated = $request->validate([
            'ids'   => 'sometimes|array',
            'ids.*' => 'uuid',
        ]);

        $query = TaskSuggestion::where('organization_id', $orgId)->where('status', 'pending');

        if (!empty($validated['ids'])) {
            $query->whereIn('id', $validated['ids']);
        }

        $suggestions = $query->get();
        $tasks       = $this->suggestionService->bulkApprove($suggestions, $user);

        return back()->with('success', count($tasks) . ' tasks created from AI suggestions.');
    }

    private function formatSuggestion(TaskSuggestion $s): array
    {
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
    }

    private function authorizeSuggestion(TaskSuggestion $suggestion, Request $request): void
    {
        if ($suggestion->organization_id !== $request->user()->organization_id) {
            abort(403);
        }
        if (!$suggestion->isPending()) {
            abort(422, 'Suggestion has already been actioned.');
        }
    }
}

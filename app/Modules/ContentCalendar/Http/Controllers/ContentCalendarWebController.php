<?php

namespace App\Modules\ContentCalendar\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ContentCalendar\Models\ContentItem;
use App\Modules\ProjectManagement\Models\Client;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\ProjectManagement\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ContentCalendarWebController extends Controller
{
    public function index(Request $request): Response
    {
        $user  = $request->user();
        $orgId = $user->organization_id;

        $clientId = $request->query('client_id');
        $type     = $request->query('type');
        $status   = $request->query('status');
        $month    = $request->query('month', now()->format('Y-m'));

        [$year, $monthNum] = explode('-', $month);

        $query = ContentItem::where('organization_id', $orgId)
            ->with(['client:id,name,company', 'project:id,name', 'assignee:id,name']);

        if ($clientId) $query->where('client_id', $clientId);
        if ($type)     $query->where('type', $type);
        if ($status)   $query->where('status', $status);

        // Calendar view: items with due_date or scheduled_at in the month
        $calendarItems = (clone $query)
            ->where(fn($q) =>
                $q->whereYear('due_date', $year)->whereMonth('due_date', $monthNum)
                  ->orWhere(fn($q2) =>
                      $q2->whereYear('scheduled_at', $year)->whereMonth('scheduled_at', $monthNum)
                  )
            )
            ->orderBy('due_date')
            ->get()
            ->map(fn($i) => $this->formatItem($i));

        // List view: all items by status
        $listItems = (clone $query)
            ->orderByRaw("CASE status
                WHEN 'in_review' THEN 1
                WHEN 'in_progress' THEN 2
                WHEN 'idea' THEN 3
                WHEN 'approved' THEN 4
                WHEN 'scheduled' THEN 5
                WHEN 'published' THEN 6
                ELSE 7 END")
            ->orderBy('due_date')
            ->paginate(50)
            ->through(fn($i) => $this->formatItem($i));

        $stats = [
            'total'       => ContentItem::where('organization_id', $orgId)->count(),
            'in_review'   => ContentItem::where('organization_id', $orgId)->where('status', 'in_review')->count(),
            'approved'    => ContentItem::where('organization_id', $orgId)->where('status', 'approved')->count(),
            'scheduled'   => ContentItem::where('organization_id', $orgId)->where('status', 'scheduled')->count(),
            'published'   => ContentItem::where('organization_id', $orgId)->whereMonth('published_at', $monthNum)->count(),
        ];

        $clients  = Client::where('organization_id', $orgId)->where('status', 'active')->select('id', 'name', 'company')->orderBy('name')->get();
        $projects = Project::where('organization_id', $orgId)->whereNotIn('status', ['completed', 'cancelled'])->select('id', 'name', 'client_id')->orderBy('name')->get();

        return Inertia::render('ContentCalendar/Index', [
            'calendarItems' => $calendarItems,
            'listItems'     => $listItems,
            'stats'         => $stats,
            'clients'       => $clients,
            'projects'      => $projects,
            'filters'       => compact('clientId', 'type', 'status', 'month'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'client_id'    => 'required|uuid',
            'project_id'   => 'nullable|uuid',
            'title'        => 'required|string|max:255',
            'body'         => 'nullable|string',
            'type'         => 'required|in:social_post,blog,ad_campaign',
            'platform'     => 'nullable|in:instagram,facebook,twitter,linkedin,youtube,website,google_ads,meta_ads,email',
            'status'       => 'nullable|in:idea,in_progress,in_review,approved,scheduled,published,cancelled',
            'due_date'     => 'nullable|date',
            'scheduled_at' => 'nullable|date',
            'assigned_to'  => 'nullable|uuid',
            'tags'         => 'nullable|array',
        ]);

        $item = ContentItem::create(array_merge($validated, [
            'organization_id' => $request->user()->organization_id,
            'created_by'      => $request->user()->id,
            'status'          => $validated['status'] ?? 'idea',
        ]));

        return back()->with('success', "Content item \"{$item->title}\" created.");
    }

    public function update(Request $request, ContentItem $contentItem): RedirectResponse
    {
        $this->authorizeItem($contentItem, $request);

        $validated = $request->validate([
            'title'        => 'sometimes|string|max:255',
            'body'         => 'sometimes|nullable|string',
            'type'         => 'sometimes|in:social_post,blog,ad_campaign',
            'platform'     => 'sometimes|nullable|in:instagram,facebook,twitter,linkedin,youtube,website,google_ads,meta_ads,email',
            'status'       => 'sometimes|in:idea,in_progress,in_review,approved,scheduled,published,cancelled',
            'due_date'     => 'sometimes|nullable|date',
            'scheduled_at' => 'sometimes|nullable|date',
            'assigned_to'  => 'sometimes|nullable|uuid',
            'project_id'   => 'sometimes|nullable|uuid',
            'tags'         => 'sometimes|nullable|array',
        ]);

        // Track approval
        if (isset($validated['status']) && $validated['status'] === 'approved' && !$contentItem->isApproved()) {
            $validated['approved_by'] = $request->user()->id;
            $validated['approved_at'] = now();
        }

        $contentItem->update($validated);

        return back()->with('success', 'Content item updated.');
    }

    public function destroy(ContentItem $contentItem, Request $request): RedirectResponse
    {
        $this->authorizeItem($contentItem, $request);
        $contentItem->delete();
        return back()->with('success', 'Content item deleted.');
    }

    /**
     * Convert a content item into a task (and link them).
     */
    public function convertToTask(Request $request, ContentItem $contentItem): RedirectResponse
    {
        $this->authorizeItem($contentItem, $request);

        if ($contentItem->task_id) {
            return back()->with('error', 'Content item already has a linked task.');
        }

        $task = Task::create([
            'organization_id' => $contentItem->organization_id,
            'project_id'      => $contentItem->project_id,
            'title'           => $contentItem->title,
            'description'     => $contentItem->body,
            'type'            => 'task',
            'status'          => 'todo',
            'priority'        => 'medium',
            'role_required'   => match ($contentItem->type) {
                'social_post'  => 'copywriter',
                'blog'         => 'copywriter',
                'ad_campaign'  => 'marketer',
                default        => null,
            },
            'assigned_to'     => $contentItem->assigned_to,
            'created_by'      => $request->user()->id,
            'due_date'        => $contentItem->due_date,
            'tags'            => array_merge($contentItem->tags ?? [], ['content']),
            'meta'            => ['content_item_id' => $contentItem->id, 'content_type' => $contentItem->type],
        ]);

        $contentItem->update(['task_id' => $task->id]);

        DB::table('task_logs')->insert([
            'id'        => (string) Str::uuid(),
            'task_id'   => $task->id,
            'user_id'   => $request->user()->id,
            'action'    => 'created',
            'old_value' => null,
            'new_value' => json_encode(['source' => 'content_item', 'content_item_id' => $contentItem->id]),
            'comment'   => "Task created from content item: {$contentItem->title}.",
            'logged_at' => now(),
        ]);

        return back()->with('success', "Task created and linked to content item.");
    }

    private function formatItem(ContentItem $i): array
    {
        return [
            'id'           => $i->id,
            'title'        => $i->title,
            'body'         => $i->body,
            'type'         => $i->type,
            'platform'     => $i->platform,
            'status'       => $i->status,
            'due_date'     => $i->due_date?->toDateString(),
            'scheduled_at' => $i->scheduled_at?->toISOString(),
            'published_at' => $i->published_at?->toISOString(),
            'tags'         => $i->tags ?? [],
            'client'       => $i->client ? ['id' => $i->client->id, 'name' => $i->client->company ?? $i->client->name] : null,
            'project'      => $i->project ? ['id' => $i->project->id, 'name' => $i->project->name] : null,
            'assignee'     => $i->assignee ? ['id' => $i->assignee->id, 'name' => $i->assignee->name] : null,
            'task_id'      => $i->task_id,
        ];
    }

    private function authorizeItem(ContentItem $item, Request $request): void
    {
        if ($item->organization_id !== $request->user()->organization_id) {
            abort(403);
        }
    }
}

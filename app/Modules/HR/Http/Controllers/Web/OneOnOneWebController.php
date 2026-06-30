<?php

namespace App\Modules\HR\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\Standup\Models\OneOnOneNote;
use App\Modules\HR\Models\PerformanceReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OneOnOneWebController extends Controller
{
    public function index(Request $request): Response
    {
        $orgId = $request->user()->organization_id;

        $teamMembers = User::where('organization_id', $orgId)
            ->where('is_active', true)
            ->where('id', '!=', $request->user()->id)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $notes = OneOnOneNote::where('organization_id', $orgId)
            ->with(['manager:id,name', 'member:id,name'])
            ->orderByDesc('meeting_date')
            ->get()
            ->map(fn($n) => [
                'id'               => $n->id,
                'manager'          => ['id' => $n->manager->id, 'name' => $n->manager->name],
                'member'           => ['id' => $n->member->id, 'name' => $n->member->name],
                'meeting_date'     => $n->meeting_date->toDateString(),
                'wins'             => $n->wins,
                'challenges'       => $n->challenges,
                'action_items'     => $n->action_items ?? [],
                'mood'             => $n->mood,
                'next_meeting_date'=> $n->next_meeting_date?->toDateString(),
                'template_name'    => $n->template_name,
                'performance_review_id' => $n->performance_review_id,
            ]);

        $latestByMember = $notes->groupBy('member.id')->map->first();
        
        $reviews = PerformanceReview::where('organization_id', $orgId)
            ->whereIn('status', ['draft', 'active'])
            ->with(['employee:id,name'])
            ->get()
            ->map(fn($r) => [
                'id' => $r->id, 
                'title' => $r->title, 
                'employee_id' => $r->employee_id, 
                'employee_name' => $r->employee->name ?? ''
            ]);

        $templates = [
            ['id' => 'weekly_checkin', 'name' => 'Weekly Check-in', 'questions' => "1. What were your key wins this week?\n2. What are you planning to focus on next week?\n3. Any blockers?"],
            ['id' => 'career_growth', 'name' => 'Career Growth (Monthly)', 'questions' => "1. Are you feeling challenged in your current role?\n2. What skills do you want to develop?\n3. How can I support your growth?"],
        ];

        return Inertia::render('OneOnOne/Index', [
            'teamMembers'    => $teamMembers,
            'notes'          => $notes->values(),
            'latestByMember' => $latestByMember->values(),
            'performanceReviews' => $reviews,
            'templates' => $templates,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'member_id'        => 'required|uuid',
            'meeting_date'     => 'required|date',
            'wins'             => 'nullable|string',
            'challenges'       => 'nullable|string',
            'action_items'     => 'nullable|array',
            'action_items.*.text'     => 'required|string',
            'action_items.*.due_date' => 'nullable|date',
            'mood'             => 'nullable|in:great,good,neutral,concerned,struggling',
            'next_meeting_date'=> 'nullable|date',
            'template_name'    => 'nullable|string',
            'performance_review_id' => [
                'nullable', 'uuid',
                Rule::exists('performance_reviews', 'id')->where('organization_id', $request->user()->organization_id),
            ]
        ]);

        $items = array_map(fn($item) => array_merge($item, [
            'id'   => (string) Str::uuid(),
            'done' => false,
        ]), $validated['action_items'] ?? []);

        OneOnOneNote::create([
            'organization_id'   => $request->user()->organization_id,
            'manager_id'        => $request->user()->id,
            'member_id'         => $validated['member_id'],
            'meeting_date'      => $validated['meeting_date'],
            'wins'              => $validated['wins'] ?? null,
            'challenges'        => $validated['challenges'] ?? null,
            'action_items'      => $items,
            'mood'              => $validated['mood'] ?? null,
            'next_meeting_date' => $validated['next_meeting_date'] ?? null,
            'template_name'     => $validated['template_name'] ?? null,
            'performance_review_id' => $validated['performance_review_id'] ?? null,
        ]);

        return back()->with('success', '1:1 note saved.');
    }

    public function update(Request $request, OneOnOneNote $oneOnOneNote): RedirectResponse
    {
        $this->authorizeOrg($oneOnOneNote);

        $validated = $request->validate([
            'wins'             => 'sometimes|nullable|string',
            'challenges'       => 'sometimes|nullable|string',
            'action_items'     => 'sometimes|array',
            'mood'             => 'sometimes|nullable|in:great,good,neutral,concerned,struggling',
            'next_meeting_date'=> 'sometimes|nullable|date',
        ]);

        $oneOnOneNote->update($validated);
        return back()->with('success', '1:1 note updated.');
    }

    public function toggleActionItem(Request $request, OneOnOneNote $oneOnOneNote): RedirectResponse
    {
        $this->authorizeOrg($oneOnOneNote);

        $validated = $request->validate(['id' => 'required|string']);

        $items = array_map(function ($item) use ($validated) {
            if ($item['id'] === $validated['id']) {
                $item['done'] = !($item['done'] ?? false);
            }
            return $item;
        }, $oneOnOneNote->action_items ?? []);

        $oneOnOneNote->update(['action_items' => $items]);
        return back()->with('success', 'Action item updated.');
    }
}

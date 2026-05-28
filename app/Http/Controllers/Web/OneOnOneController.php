<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\Standup\Models\OneOnOneNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class OneOnOneController extends Controller
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
            ]);

        $latestByMember = $notes->groupBy('member.id')->map->first();

        return Inertia::render('OneOnOne/Index', [
            'teamMembers'    => $teamMembers,
            'notes'          => $notes->values(),
            'latestByMember' => $latestByMember->values(),
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
        ]);

        return back()->with('success', '1:1 note saved.');
    }

    public function update(Request $request, OneOnOneNote $oneOnOneNote): RedirectResponse
    {
        abort_if($oneOnOneNote->organization_id !== $request->user()->organization_id, 403);

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
        abort_if($oneOnOneNote->organization_id !== $request->user()->organization_id, 403);

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

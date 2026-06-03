<?php

namespace App\Modules\Standup\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\Standup\Models\EodStandup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StandupWebController extends Controller
{
    public function index(Request $request): Response
    {
        $user  = $request->user();
        $orgId = $user->organization_id;
        $date  = $request->input('date', today()->toDateString());

        $myStandup = EodStandup::where('organization_id', $orgId)
            ->where('user_id', $user->id)
            ->whereDate('date', $date)
            ->first();

        $teamStandups = EodStandup::where('organization_id', $orgId)
            ->whereDate('date', $date)
            ->with('user:id,name')
            ->orderByDesc('submitted_at')
            ->get()
            ->map(fn($s) => [
                'id'              => $s->id,
                'completed_today' => $s->completed_today,
                'in_progress'     => $s->in_progress,
                'blockers'        => $s->blockers,
                'tomorrow_plan'   => $s->tomorrow_plan,
                'status'          => $s->status,
                'submitted_at'    => $s->submitted_at?->toISOString(),
                'has_blockers'    => $s->hasBlockers(),
                'user'            => $s->user ? ['id' => $s->user->id, 'name' => $s->user->name] : null,
            ]);

        $activeTeam = User::where('organization_id', $orgId)
            ->where('is_active', true)
            ->count();

        $submitted = $teamStandups->count();

        return Inertia::render('Standup/Index', [
            'myStandup'    => $myStandup ? [
                'id'              => $myStandup->id,
                'completed_today' => $myStandup->completed_today,
                'in_progress'     => $myStandup->in_progress,
                'blockers'        => $myStandup->blockers,
                'tomorrow_plan'   => $myStandup->tomorrow_plan,
                'status'          => $myStandup->status,
            ] : null,
            'teamStandups' => $teamStandups,
            'date'         => $date,
            'stats'        => [
                'total_team' => $activeTeam,
                'submitted'  => $submitted,
                'pending'    => max(0, $activeTeam - $submitted),
                'blockers'   => $teamStandups->where('has_blockers', true)->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user  = $request->user();
        $orgId = $user->organization_id;

        $validated = $request->validate([
            'completed_today' => 'required|string|max:2000',
            'in_progress'     => 'nullable|string|max:2000',
            'blockers'        => 'nullable|string|max:1000',
            'tomorrow_plan'   => 'nullable|string|max:2000',
        ]);

        EodStandup::updateOrCreate(
            [
                'organization_id' => $orgId,
                'user_id'         => $user->id,
                'date'            => today(),
            ],
            [
                ...$validated,
                'status'       => 'submitted',
                'submitted_at' => now(),
            ]
        );

        return back()->with('success', 'Standup submitted!');
    }

    public function markReviewed(Request $request, EodStandup $standup): RedirectResponse
    {
        $this->authorizeOrg($standup);
        $standup->update(['status' => 'reviewed']);
        return back()->with('success', 'Standup marked as reviewed.');
    }
}

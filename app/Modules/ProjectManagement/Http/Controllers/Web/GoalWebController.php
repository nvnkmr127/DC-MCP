<?php

namespace App\Modules\ProjectManagement\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\Revenue\Models\Goal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class GoalWebController extends Controller
{
    public function index(Request $request): Response
    {
        $orgId = $request->user()->organization_id;
        $year  = $request->integer('year', now()->year);

        $goals = Goal::where('organization_id', $orgId)
            ->where('year', $year)
            ->with('owner:id,name')
            ->orderBy('period')
            ->get()
            ->map(fn($g) => [
                'id'          => $g->id,
                'title'       => $g->title,
                'description' => $g->description,
                'period'      => $g->period,
                'year'        => $g->year,
                'status'      => $g->status,
                'progress'    => $g->progress,
                'key_results' => $g->key_results ?? [],
                'owner'       => $g->owner ? ['id' => $g->owner->id, 'name' => $g->owner->name] : null,
            ]);

        $byPeriod = $goals->groupBy('period');

        $currentMonth  = now()->month;
        $currentPeriod = match (true) {
            $currentMonth <= 3  => 'q1',
            $currentMonth <= 6  => 'q2',
            $currentMonth <= 9  => 'q3',
            default             => 'q4',
        };

        $activeGoals  = $goals->where('status', 'active');
        $orgProgress  = $activeGoals->count() > 0
            ? (int) round($activeGoals->avg('progress'))
            : 0;

        $team = User::where('organization_id', $orgId)->where('is_active', true)->select('id', 'name')->orderBy('name')->get();

        return Inertia::render('Goals/Index', [
            'goals'          => $goals->values(),
            'byPeriod'       => $byPeriod,
            'currentPeriod'  => $currentPeriod,
            'orgProgress'    => $orgProgress,
            'year'           => $year,
            'team'           => $team,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'period'      => 'required|in:q1,q2,q3,q4,annual',
            'year'        => 'required|integer|min:2020|max:2050',
            'owner_id'    => 'nullable|uuid',
            'status'      => 'nullable|in:draft,active,completed,cancelled',
            'key_results' => 'nullable|array',
            'key_results.*.title'  => 'required|string',
            'key_results.*.target' => 'required|numeric|min:0',
            'key_results.*.unit'   => 'nullable|string',
        ]);

        $krs = array_map(fn($kr) => array_merge($kr, [
            'id'      => (string) Str::uuid(),
            'current' => 0,
        ]), $validated['key_results'] ?? []);

        Goal::create([
            'organization_id' => $request->user()->organization_id,
            'title'           => $validated['title'],
            'description'     => $validated['description'] ?? null,
            'period'          => $validated['period'],
            'year'            => $validated['year'],
            'owner_id'        => $validated['owner_id'] ?? null,
            'status'          => $validated['status'] ?? 'active',
            'progress'        => 0,
            'key_results'     => $krs,
        ]);

        return back()->with('success', 'Goal created.');
    }

    public function update(Request $request, Goal $goal): RedirectResponse
    {
        $this->authorizeOrg($goal);

        $validated = $request->validate([
            'title'       => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'status'      => 'sometimes|in:draft,active,completed,cancelled',
            'key_results' => 'sometimes|array',
        ]);

        $goal->update($validated);
        if (isset($validated['key_results'])) {
            $goal->recalculateProgress();
        }

        return back()->with('success', 'Goal updated.');
    }

    public function destroy(Request $request, Goal $goal): RedirectResponse
    {
        $this->authorizeOrg($goal);
        $goal->delete();
        return back()->with('success', 'Goal deleted.');
    }

    public function updateKeyResult(Request $request, Goal $goal): RedirectResponse
    {
        $this->authorizeOrg($goal);

        $validated = $request->validate([
            'kr_id'   => 'required|string',
            'current' => 'required|numeric|min:0',
        ]);

        $krs = array_map(function ($kr) use ($validated) {
            if ($kr['id'] === $validated['kr_id']) {
                $kr['current'] = (float) $validated['current'];
            }
            return $kr;
        }, $goal->key_results ?? []);

        $goal->key_results = $krs;
        $goal->save();
        $goal->recalculateProgress();

        return back()->with('success', 'Key result updated.');
    }
}

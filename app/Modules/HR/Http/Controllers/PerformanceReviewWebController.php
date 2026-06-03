<?php

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\HR\Models\PerformanceReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PerformanceReviewWebController extends Controller
{
    public function index(Request $request): Response
    {
        $user  = $request->user();
        $orgId = $user->organization_id;

        if ($user->role === 'ceo') {
            $written = PerformanceReview::where('organization_id', $orgId)
                ->with(['reviewer:id,name', 'reviewee:id,name'])
                ->orderByDesc('created_at')
                ->get();
            $received = collect();
        } else {
            $written = PerformanceReview::where('reviewer_id', $user->id)
                ->with(['reviewer:id,name', 'reviewee:id,name'])
                ->orderByDesc('created_at')
                ->get();
            $received = PerformanceReview::where('reviewee_id', $user->id)
                ->with(['reviewer:id,name', 'reviewee:id,name'])
                ->orderByDesc('created_at')
                ->get();
        }

        $mapReview = fn($r) => [
            'id'                    => $r->id,
            'period'                => $r->period,
            'year'                  => $r->year,
            'overall_rating'        => $r->overall_rating,
            'technical_rating'      => $r->technical_rating,
            'communication_rating'  => $r->communication_rating,
            'teamwork_rating'       => $r->teamwork_rating,
            'strengths'             => $r->strengths,
            'improvements'          => $r->improvements,
            'goals_next'            => $r->goals_next,
            'status'                => $r->status,
            'acknowledged_at'       => $r->acknowledged_at?->toDateString(),
            'reviewer'              => ['id' => $r->reviewer?->id, 'name' => $r->reviewer?->name],
            'reviewee'              => ['id' => $r->reviewee?->id, 'name' => $r->reviewee?->name],
        ];

        $users = User::where('organization_id', $orgId)->select('id', 'name')->orderBy('name')->get();

        return Inertia::render('HR/Reviews/Index', [
            'written'  => $written->map($mapReview),
            'received' => $received->map($mapReview),
            'users'    => $users,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'reviewee_id'           => 'required|uuid|exists:users,id',
            'period'                => 'required|in:q1,q2,q3,q4,annual',
            'year'                  => 'required|integer|min:2020|max:2099',
            'overall_rating'        => 'nullable|integer|min:1|max:5',
            'technical_rating'      => 'nullable|integer|min:1|max:5',
            'communication_rating'  => 'nullable|integer|min:1|max:5',
            'teamwork_rating'       => 'nullable|integer|min:1|max:5',
            'strengths'             => 'nullable|string',
            'improvements'          => 'nullable|string',
            'goals_next'            => 'nullable|string',
        ]);

        PerformanceReview::create([
            'organization_id' => $request->user()->organization_id,
            'reviewer_id'     => $request->user()->id,
            ...$validated,
        ]);

        return back()->with('success', 'Review created.');
    }

    public function update(Request $request, PerformanceReview $review): RedirectResponse
    {
        $this->authorizeOrg($review);

        $validated = $request->validate([
            'overall_rating'       => 'nullable|integer|min:1|max:5',
            'technical_rating'     => 'nullable|integer|min:1|max:5',
            'communication_rating' => 'nullable|integer|min:1|max:5',
            'teamwork_rating'      => 'nullable|integer|min:1|max:5',
            'strengths'            => 'nullable|string',
            'improvements'         => 'nullable|string',
            'goals_next'           => 'nullable|string',
        ]);

        $review->update($validated);
        return back()->with('success', 'Review updated.');
    }

    public function submit(Request $request, PerformanceReview $review): RedirectResponse
    {
        $this->authorizeOrg($review);
        $review->update(['status' => 'submitted']);
        return back()->with('success', 'Review submitted.');
    }

    public function acknowledge(Request $request, PerformanceReview $review): RedirectResponse
    {
        $this->authorizeOrg($review);
        $review->update(['status' => 'acknowledged', 'acknowledged_at' => now()]);
        return back()->with('success', 'Review acknowledged.');
    }
}

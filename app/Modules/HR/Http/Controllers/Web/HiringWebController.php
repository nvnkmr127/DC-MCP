<?php

namespace App\Modules\HR\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\HR\Models\Candidate;
use App\Modules\HR\Models\JobOpening;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HiringWebController extends Controller
{
    public function index(Request $request): Response
    {
        $orgId = $request->user()->organization_id;

        $openings = JobOpening::where('organization_id', $orgId)
            ->withCount('candidates')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($o) => [
                'id'               => $o->id,
                'title'            => $o->title,
                'department'       => $o->department,
                'status'           => $o->status,
                'salary_min'       => $o->salary_min ? (float) $o->salary_min : null,
                'salary_max'       => $o->salary_max ? (float) $o->salary_max : null,
                'target_date'      => $o->target_date?->toDateString(),
                'candidates_count' => $o->candidates_count,
                'candidates'       => Candidate::where('job_opening_id', $o->id)->get()->map(fn($c) => [
                    'id'              => $c->id,
                    'name'            => $c->name,
                    'email'           => $c->email,
                    'phone'           => $c->phone,
                    'source'          => $c->source,
                    'stage'           => $c->stage,
                    'rating'          => $c->rating,
                    'notes'           => $c->notes,
                    'rejected_reason' => $c->rejected_reason,
                    'hired_at'        => $c->hired_at?->toDateString(),
                ]),
            ]);

        return Inertia::render('HR/Hiring/Index', [
            'openings' => $openings,
        ]);
    }

    public function storeOpening(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'department'  => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'requirements'=> 'nullable|string',
            'salary_min'  => 'nullable|numeric|min:0',
            'salary_max'  => 'nullable|numeric|min:0',
            'target_date' => 'nullable|date',
            'status'      => 'sometimes|in:open,on_hold,closed',
        ]);

        JobOpening::create([
            'organization_id' => $request->user()->organization_id,
            ...$validated,
        ]);

        return back()->with('success', 'Job opening created.');
    }

    public function updateOpening(Request $request, JobOpening $opening): RedirectResponse
    {
        $this->authorizeOrg($opening);

        $validated = $request->validate([
            'title'       => 'sometimes|string|max:255',
            'department'  => 'nullable|string',
            'status'      => 'sometimes|in:open,on_hold,closed',
            'salary_min'  => 'nullable|numeric',
            'salary_max'  => 'nullable|numeric',
            'target_date' => 'nullable|date',
        ]);

        $opening->update($validated);
        return back()->with('success', 'Opening updated.');
    }

    public function destroyOpening(Request $request, JobOpening $opening): RedirectResponse
    {
        $this->authorizeOrg($opening);
        $opening->delete();
        return back()->with('success', 'Opening deleted.');
    }

    public function storeCandidate(Request $request, JobOpening $opening): RedirectResponse
    {
        $this->authorizeOrg($opening);

        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'email'      => 'nullable|email',
            'phone'      => 'nullable|string',
            'resume_url' => 'nullable|string',
            'source'     => 'required|in:referral,job_portal,linkedin,direct,other',
            'notes'      => 'nullable|string',
        ]);

        Candidate::create([
            'organization_id' => $request->user()->organization_id,
            'job_opening_id'  => $opening->id,
            ...$validated,
        ]);

        return back()->with('success', 'Candidate added.');
    }

    public function updateCandidate(Request $request, Candidate $candidate): RedirectResponse
    {
        $this->authorizeOrg($candidate);

        $validated = $request->validate([
            'stage'           => 'sometimes|in:applied,screening,interview_1,interview_2,offer,hired,rejected',
            'rating'          => 'nullable|integer|min:1|max:5',
            'notes'           => 'nullable|string',
            'rejected_reason' => 'nullable|string',
        ]);

        $candidate->update($validated);
        return back()->with('success', 'Candidate updated.');
    }

    public function destroyCandidate(Request $request, Candidate $candidate): RedirectResponse
    {
        $this->authorizeOrg($candidate);
        $candidate->delete();
        return back()->with('success', 'Candidate removed.');
    }
}

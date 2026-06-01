<?php

namespace App\Modules\ProjectManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Revenue\Models\DeliverableSubmission;
use App\Modules\Revenue\Models\SowDeliverable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DeliverableWebController extends Controller
{
    public function submit(Request $request, SowDeliverable $sowDeliverable): RedirectResponse
    {
        abort_if($sowDeliverable->organization_id !== $request->user()->organization_id, 403);

        $validated = $request->validate([
            'file_url'      => 'nullable|string|max:1000',
            'external_link' => 'nullable|url|max:1000',
            'notes'         => 'nullable|string|max:2000',
        ]);

        $lastRevision = DeliverableSubmission::where('sow_deliverable_id', $sowDeliverable->id)
            ->max('revision_number') ?? 0;

        DeliverableSubmission::create([
            'organization_id'   => $request->user()->organization_id,
            'sow_deliverable_id'=> $sowDeliverable->id,
            'submitted_by'      => $request->user()->id,
            'revision_number'   => $lastRevision + 1,
            'status'            => 'submitted',
            ...$validated,
        ]);

        return back()->with('success', 'Deliverable submitted for review.');
    }

    public function approve(Request $request, DeliverableSubmission $deliverableSubmission): RedirectResponse
    {
        abort_if($deliverableSubmission->organization_id !== $request->user()->organization_id, 403);

        $validated = $request->validate([
            'reviewer_notes' => 'nullable|string|max:2000',
        ]);

        $deliverableSubmission->update([
            'status'         => 'approved',
            'reviewer_id'    => $request->user()->id,
            'reviewed_at'    => now(),
            'reviewer_notes' => $validated['reviewer_notes'] ?? null,
        ]);

        return back()->with('success', 'Deliverable approved.');
    }

    public function requestRevision(Request $request, DeliverableSubmission $deliverableSubmission): RedirectResponse
    {
        abort_if($deliverableSubmission->organization_id !== $request->user()->organization_id, 403);

        $validated = $request->validate([
            'reviewer_notes' => 'required|string|max:2000',
        ]);

        $deliverableSubmission->update([
            'status'         => 'revision_requested',
            'reviewer_id'    => $request->user()->id,
            'reviewed_at'    => now(),
            'reviewer_notes' => $validated['reviewer_notes'],
        ]);

        return back()->with('success', 'Revision requested.');
    }
}

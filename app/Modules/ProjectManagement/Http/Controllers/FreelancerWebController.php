<?php

namespace App\Modules\ProjectManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Models\Freelancer;
use App\Modules\HR\Models\FreelancerAssignment;
use App\Modules\ProjectManagement\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FreelancerWebController extends Controller
{
    public function index(Request $request): Response
    {
        $orgId = $request->user()->organization_id;

        $freelancers = Freelancer::where('organization_id', $orgId)
            ->withCount('assignments')
            ->orderBy('name')
            ->get()
            ->map(fn($f) => [
                'id'                => $f->id,
                'name'              => $f->name,
                'email'             => $f->email,
                'phone'             => $f->phone,
                'skill_set'         => $f->skill_set,
                'status'            => $f->status,
                'rate_per_hour'     => $f->rate_per_hour ? (float) $f->rate_per_hour : null,
                'payment_method'    => $f->payment_method,
                'notes'             => $f->notes,
                'assignments_count' => $f->assignments_count,
                'assignments'       => FreelancerAssignment::where('freelancer_id', $f->id)->with('project:id,name')->get()->map(fn($a) => [
                    'id'           => $a->id,
                    'project'      => $a->project ? ['id' => $a->project->id, 'name' => $a->project->name] : null,
                    'agreed_rate'  => $a->agreed_rate ? (float) $a->agreed_rate : null,
                    'start_date'   => $a->start_date?->toDateString(),
                    'end_date'     => $a->end_date?->toDateString(),
                    'hours_worked' => (float) $a->hours_worked,
                    'total_paid'   => (float) $a->total_paid,
                    'status'       => $a->status,
                    'notes'        => $a->notes,
                ]),
            ]);

        $projects = Project::where('organization_id', $orgId)->select('id', 'name')->orderBy('name')->get();

        return Inertia::render('Freelancers/Index', [
            'freelancers' => $freelancers,
            'projects'    => $projects,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'email'          => 'nullable|email',
            'phone'          => 'nullable|string',
            'skill_set'      => 'nullable|string',
            'rate_per_hour'  => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string',
            'notes'          => 'nullable|string',
        ]);

        Freelancer::create([
            'organization_id' => $request->user()->organization_id,
            ...$validated,
        ]);

        return back()->with('success', 'Freelancer added.');
    }

    public function update(Request $request, Freelancer $freelancer): RedirectResponse
    {
        abort_if($freelancer->organization_id !== $request->user()->organization_id, 403);

        $validated = $request->validate([
            'name'           => 'sometimes|string|max:255',
            'email'          => 'nullable|email',
            'phone'          => 'nullable|string',
            'skill_set'      => 'nullable|string',
            'status'         => 'sometimes|in:active,inactive,blacklisted',
            'rate_per_hour'  => 'nullable|numeric',
            'payment_method' => 'nullable|string',
            'notes'          => 'nullable|string',
        ]);

        $freelancer->update($validated);
        return back()->with('success', 'Freelancer updated.');
    }

    public function destroy(Request $request, Freelancer $freelancer): RedirectResponse
    {
        abort_if($freelancer->organization_id !== $request->user()->organization_id, 403);
        $freelancer->delete();
        return back()->with('success', 'Freelancer deleted.');
    }

    public function storeAssignment(Request $request, Freelancer $freelancer): RedirectResponse
    {
        abort_if($freelancer->organization_id !== $request->user()->organization_id, 403);

        $validated = $request->validate([
            'project_id'  => 'nullable|uuid|exists:projects,id',
            'agreed_rate' => 'nullable|numeric|min:0',
            'start_date'  => 'nullable|date',
            'end_date'    => 'nullable|date',
            'notes'       => 'nullable|string',
        ]);

        FreelancerAssignment::create([
            'organization_id' => $request->user()->organization_id,
            'freelancer_id'   => $freelancer->id,
            ...$validated,
        ]);

        return back()->with('success', 'Assignment created.');
    }

    public function updateAssignment(Request $request, FreelancerAssignment $assignment): RedirectResponse
    {
        abort_if($assignment->organization_id !== $request->user()->organization_id, 403);

        $validated = $request->validate([
            'hours_worked' => 'sometimes|numeric|min:0',
            'total_paid'   => 'sometimes|numeric|min:0',
            'status'       => 'sometimes|in:active,completed,cancelled',
            'notes'        => 'nullable|string',
        ]);

        $assignment->update($validated);
        return back()->with('success', 'Assignment updated.');
    }

    public function destroyAssignment(Request $request, FreelancerAssignment $assignment): RedirectResponse
    {
        abort_if($assignment->organization_id !== $request->user()->organization_id, 403);
        $assignment->delete();
        return back()->with('success', 'Assignment deleted.');
    }
}

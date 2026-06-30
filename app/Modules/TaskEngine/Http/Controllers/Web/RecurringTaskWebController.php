<?php

namespace App\Modules\TaskEngine\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\TaskEngine\Models\RecurringTaskRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecurringTaskWebController extends Controller
{
    public function index(Request $request): Response
    {
        $orgId = $request->user()->organization_id;

        $rules = RecurringTaskRule::where('organization_id', $orgId)
            ->with(['client:id,name', 'project:id,name'])
            ->orderBy('title')
            ->get()
            ->map(fn($r) => [
                'id'              => $r->id,
                'title'           => $r->title,
                'description'     => $r->description,
                'frequency'       => $r->frequency,
                'frequency_day'   => $r->frequency_day,
                'priority'        => $r->priority,
                'role_required'   => $r->role_required,
                'estimated_hours' => $r->estimated_hours,
                'target_type'        => $r->target_type,
                'target_template_id' => $r->target_template_id,
                'is_active'          => $r->is_active,
                'last_spawned_at'    => $r->last_spawned_at?->toDateString(),
                'next_spawn_at'      => $r->next_spawn_at?->toDateString(),
                'client'          => $r->client ? ['id' => $r->client->id, 'name' => $r->client->name] : null,
                'project'         => $r->project ? ['id' => $r->project->id, 'name' => $r->project->name] : null,
            ]);

        $team = User::where('organization_id', $orgId)->where('is_active', true)->select('id', 'name')->orderBy('name')->get();

        return Inertia::render('RecurringTasks/Index', [
            'rules' => $rules,
            'team'  => $team,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title'           => 'required|string|max:255',
            'description'     => 'nullable|string',
            'frequency'       => 'required|in:daily,weekly,monthly,quarterly',
            'frequency_day'   => 'nullable|integer|min:0|max:31',
            'priority'        => 'required|in:low,medium,high,critical',
            'role_required'   => 'nullable|in:ceo,project_manager,analyst,marketer,developer,designer,copywriter',
            'estimated_hours' => 'nullable|numeric|min:0',
            'client_id'          => 'nullable|uuid',
            'project_id'         => 'nullable|uuid',
            'target_type'        => 'required|in:task,project,audit_checklist',
            'target_template_id' => 'nullable|uuid',
        ]);

        $nextSpawn = match ($validated['frequency']) {
            'daily'     => now()->addDay()->startOfDay(),
            'weekly'    => now()->next('Monday')->startOfDay(),
            'monthly'   => now()->addMonthNoOverflow()->startOfMonth(),
            'quarterly' => now()->addMonthsNoOverflow(3)->startOfMonth(),
            default     => now()->addDay(),
        };

        RecurringTaskRule::create([
            'organization_id' => $request->user()->organization_id,
            'created_by'      => $request->user()->id,
            'is_active'       => true,
            'next_spawn_at'   => $nextSpawn,
            ...$validated,
        ]);

        return back()->with('success', 'Recurring task rule created.');
    }

    public function update(Request $request, RecurringTaskRule $recurringTaskRule): RedirectResponse
    {
        $this->authorizeOrg($recurringTaskRule);

        $validated = $request->validate([
            'title'         => 'sometimes|string|max:255',
            'is_active'     => 'sometimes|boolean',
            'priority'      => 'sometimes|in:low,medium,high,critical',
            'frequency_day' => 'sometimes|nullable|integer|min:0|max:31',
        ]);

        $recurringTaskRule->update($validated);
        return back()->with('success', 'Rule updated.');
    }

    public function destroy(Request $request, RecurringTaskRule $recurringTaskRule): RedirectResponse
    {
        $this->authorizeOrg($recurringTaskRule);
        $recurringTaskRule->delete();
        return back()->with('success', 'Rule deleted.');
    }
}

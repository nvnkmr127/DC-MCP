<?php

namespace App\Modules\ProjectManagement\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\ProjectManagement\Models\Client;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\ProjectManagement\Models\ProjectTemplate;
use App\Modules\ProjectManagement\Models\Task;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

use App\Modules\Auth\Models\User;
use App\Modules\TaskEngine\Models\RecurringTaskRule;

class ProjectTemplateWebController extends Controller
{
    public function index(Request $request): Response
    {
        $orgId = $request->user()->organization_id;

        $templates = ProjectTemplate::where('organization_id', $orgId)
            ->orderBy('name')
            ->get()
            ->map(fn($t) => [
                'id'           => $t->id,
                'name'         => $t->name,
                'description'  => $t->description,
                'service_type' => $t->service_type,
                'tasks'        => $t->tasks ?? [],
                'task_count'   => count($t->tasks ?? []),
            ]);

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
                'is_active'       => $r->is_active,
                'last_spawned_at' => $r->last_spawned_at?->toDateString(),
                'next_spawn_at'   => $r->next_spawn_at?->toDateString(),
                'client'          => $r->client ? ['id' => $r->client->id, 'name' => $r->client->name] : null,
                'project'         => $r->project ? ['id' => $r->project->id, 'name' => $r->project->name] : null,
            ]);

        $clients = Client::where('organization_id', $orgId)->where('status', 'active')->select('id', 'name', 'company')->get();
        
        $team = User::where('organization_id', $orgId)->where('is_active', true)->select('id', 'name')->orderBy('name')->get();

        return Inertia::render('Templates/Index', [
            'templates' => $templates,
            'rules'     => $rules,
            'clients'   => $clients,
            'team'      => $team,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $orgId = $request->user()->organization_id;
        $validated = $request->validate([
            'name'                          => 'required|string|max:255',
            'service_type'                  => 'nullable|string|max:255',
            'description'                   => 'nullable|string',
            'tasks'                         => 'nullable|array',
            'tasks.*.title'                 => 'required|string|max:255',
            'tasks.*.priority'              => 'nullable|in:critical,high,medium,low',
            'tasks.*.offset_days'           => 'nullable|integer|min:0',
            'tasks.*.estimated_hours'       => 'nullable|numeric|min:0',
        ]);

        ProjectTemplate::create([
            'organization_id' => $orgId,
            ...$validated,
            'tasks' => $validated['tasks'] ?? [],
        ]);

        return back()->with('success', 'Template created.');
    }

    public function update(Request $request, ProjectTemplate $template): RedirectResponse
    {
        $this->authorizeOrg($template);

        $validated = $request->validate([
            'name'                    => 'sometimes|string|max:255',
            'service_type'            => 'nullable|string',
            'description'             => 'nullable|string',
            'tasks'                   => 'nullable|array',
            'tasks.*.title'           => 'required|string|max:255',
            'tasks.*.priority'        => 'nullable|in:critical,high,medium,low',
            'tasks.*.offset_days'     => 'nullable|integer|min:0',
            'tasks.*.estimated_hours' => 'nullable|numeric|min:0',
        ]);

        $template->update($validated);
        return back()->with('success', 'Template updated.');
    }

    public function destroy(Request $request, ProjectTemplate $template): RedirectResponse
    {
        $this->authorizeOrg($template);
        $template->delete();
        return back()->with('success', 'Template deleted.');
    }

}

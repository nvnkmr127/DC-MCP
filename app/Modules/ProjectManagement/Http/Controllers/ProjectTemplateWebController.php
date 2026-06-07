<?php

namespace App\Modules\ProjectManagement\Http\Controllers;

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

        $clients = Client::where('organization_id', $orgId)->where('status', 'active')->select('id', 'name', 'company')->get();

        return Inertia::render('ProjectTemplates/Index', [
            'templates' => $templates,
            'clients'   => $clients,
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

    public function createProject(Request $request, ProjectTemplate $template): RedirectResponse
    {
        $this->authorizeOrg($template);

        $orgId = $request->user()->organization_id;
        $validated = $request->validate([
            'client_id'  => [
                'required',
                'uuid',
                Rule::exists('clients', 'id')
                    ->where('organization_id', $orgId)
                    ->whereNull('deleted_at'),
            ],
            'name'       => 'required|string|max:255',
            'start_date' => 'required|date',
        ]);

        $project = Project::create([
            'organization_id' => $orgId,
            'client_id'       => $validated['client_id'],
            'name'            => $validated['name'],
            'start_date'      => $validated['start_date'],
            'status'          => 'planning',
            'priority'        => 'medium',
        ]);

        $startDate = Carbon::parse($validated['start_date']);
        foreach ($template->tasks ?? [] as $taskDef) {
            $dueDate = $startDate->copy()->addDays($taskDef['offset_days'] ?? 0);
            $priority = ($taskDef['priority'] ?? 'medium') === 'urgent' ? 'critical' : ($taskDef['priority'] ?? 'medium');
            Task::create([
                'organization_id' => $orgId,
                'project_id'      => $project->id,
                'created_by'      => $request->user()->id,
                'title'           => $taskDef['title'],
                'priority'        => $priority,
                'due_date'        => $dueDate,
                'estimated_hours' => $taskDef['estimated_hours'] ?? null,
                'status'          => 'todo',
            ]);
        }

        return redirect("/projects/{$project->id}")->with('success', 'Project created from template.');
    }
}

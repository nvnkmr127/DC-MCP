<?php

namespace App\Modules\Auth\Http\Controllers\Web;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use App\Modules\ProjectManagement\Models\Task;
use App\Modules\ProjectManagement\Models\Project;

class TrashController extends Controller
{
    public function index(Request $request)
    {
        $orgId = session('current_organization_id');

        $tasks = Task::onlyTrashed()
            ->where('organization_id', $orgId)
            ->get()
            ->map(fn($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'type' => 'task',
                'deleted_at' => $t->deleted_at->diffForHumans()
            ]);

        $projects = Project::onlyTrashed()
            ->where('organization_id', $orgId)
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'title' => $p->name,
                'type' => 'project',
                'deleted_at' => $p->deleted_at->diffForHumans()
            ]);

        $items = $tasks->concat($projects)->sortByDesc('deleted_at')->values();

        $org = \App\Modules\Auth\Models\Organization::find($orgId);
        $retentionDays = $org->settings['trash_retention_days'] ?? 30;

        return Inertia::render('Settings/Trash', [
            'items' => $items,
            'retentionDays' => (int) $retentionDays,
        ]);
    }

    public function restore(Request $request, $type, $id)
    {
        $orgId = session('current_organization_id');

        if ($type === 'task') {
            $model = Task::onlyTrashed()->where('organization_id', $orgId)->findOrFail($id);
        } else if ($type === 'project') {
            $model = Project::onlyTrashed()->where('organization_id', $orgId)->findOrFail($id);
        } else {
            abort(404);
        }

        $model->restore();

        return redirect()->back()->with('success', ucfirst($type) . ' restored successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $orgId = session('current_organization_id');
        $items = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|uuid',
            'items.*.type' => 'required|in:task,project',
        ])['items'];

        $taskIds = collect($items)->where('type', 'task')->pluck('id')->toArray();
        $projectIds = collect($items)->where('type', 'project')->pluck('id')->toArray();

        if (count($taskIds) > 0) {
            Task::onlyTrashed()->where('organization_id', $orgId)->whereIn('id', $taskIds)->forceDelete();
        }

        if (count($projectIds) > 0) {
            Project::onlyTrashed()->where('organization_id', $orgId)->whereIn('id', $projectIds)->forceDelete();
        }

        return redirect()->back()->with('success', 'Selected items permanently deleted.');
    }
}
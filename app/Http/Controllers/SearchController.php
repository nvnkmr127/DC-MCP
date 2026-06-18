<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use App\Modules\ProjectManagement\Models\Task;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\Auth\Models\User;

class SearchController extends BaseController
{
    public function __invoke(Request $request)
    {
        $query = $request->input('q');

        if (!$query || strlen($query) < 2) {
            return response()->json([
                'tasks' => [],
                'projects' => [],
                'users' => []
            ]);
        }

        $organizationId = session('current_organization_id');

        $tasks = Task::where('organization_id', $organizationId)
            ->where(function($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%");
            })
            ->limit(5)
            ->get()
            ->map(fn($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'subtitle' => $t->project ? $t->project->name : 'No Project',
                'url' => "/tasks/{$t->id}",
                'type' => 'task'
            ]);

        $projects = Project::where('organization_id', $organizationId)
            ->where(function($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%");
            })
            ->limit(5)
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'title' => $p->name,
                'subtitle' => $p->client ? $p->client->name : 'No Client',
                'url' => "/projects/{$p->id}",
                'type' => 'project'
            ]);

        $users = User::where('name', 'like', "%{$query}%")
            ->orWhere('email', 'like', "%{$query}%")
            ->limit(5)
            ->get()
            ->map(fn($u) => [
                'id' => $u->id,
                'title' => $u->name,
                'subtitle' => $u->email,
                'url' => "/settings/team", // Assuming team page
                'type' => 'user'
            ]);

        return response()->json([
            'tasks' => $tasks,
            'projects' => $projects,
            'users' => $users
        ]);
    }
}

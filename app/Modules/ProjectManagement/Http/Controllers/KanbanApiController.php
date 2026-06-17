<?php

namespace App\Modules\ProjectManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\ProjectManagement\Http\Resources\TaskResource;
use App\Shared\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KanbanApiController extends Controller
{
    public function board(Request $request, Project $project): JsonResponse
    {
        if (!$request->user()->hasPermission('view', 'project')) {
            abort(403, 'Unauthorized action.');
        }

        $tasks = $project->tasks()
            ->with(['assignee', 'sprint', 'milestone'])
            ->orderBy('sort_order')
            ->get();

        $grouped = [
            'backlog' => [],
            'todo' => [],
            'in_progress' => [],
            'in_review' => [],
            'blocked' => [],
            'done' => [],
            'cancelled' => [],
        ];

        foreach ($tasks as $task) {
            $status = is_object($task->status) ? $task->status->value : $task->status;
            if (array_key_exists($status, $grouped)) {
                $grouped[$status][] = new TaskResource($task);
            }
        }

        return ApiResponse::success($grouped);
    }
}

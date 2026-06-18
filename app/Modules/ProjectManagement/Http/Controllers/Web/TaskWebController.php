<?php

namespace App\Modules\ProjectManagement\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\ProjectManagement\Services\TaskService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use App\Modules\ProjectManagement\Models\Task;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\ProjectManagement\Models\Comment;
use App\Modules\ProjectManagement\Models\Attachment;
use App\Modules\ProjectManagement\Models\TimeEntry;
use App\Modules\ProjectManagement\Models\TaskDependency;
use App\Modules\Auth\Models\User;
use App\Models\Activity;
use Illuminate\Validation\Rule;
use App\Modules\ProjectManagement\Http\Requests\StoreTaskRequest;
use App\Modules\ProjectManagement\Http\Requests\UpdateTaskRequest;
use App\Shared\Enums\TaskStatus;
use App\Shared\Enums\TaskType;
use App\Shared\Enums\TaskPriority;
use App\Shared\Enums\RoleType;
use App\Traits\Exportable;

class TaskWebController extends Controller
{
    use Exportable;

    public function __construct(private TaskService $taskService) {}
    public function index(Request $request)
    {
        if (!$request->user()->hasPermission('view', 'task')) {
            abort(403);
        }

        $query = Task::with(['project:id,name', 'assignee:id,name'])
            ->whereNull('parent_task_id')
            ->orderByDesc('updated_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }
        if ($request->assigned === 'me') {
            $query->where('assigned_to', $request->user()->id);
        }
        if ($request->overdue === '1') {
            $query->whereDate('due_date', '<', now())->whereNotIn('status', ['done', 'cancelled']);
        }

        $tasks = $query->paginate(25)->through(fn($t) => [
            'id'              => $t->id,
            'title'           => $t->title,
            'status'          => is_object($t->status) ? $t->status->value : $t->status,
            'priority'        => is_object($t->priority) ? $t->priority->value : $t->priority,
            'due_date'        => $t->due_date?->toDateString(),
            'estimated_hours' => (float) $t->estimated_hours,
            'project_id'      => $t->project_id,
            'project'         => $t->project ? ['id' => $t->project->id, 'name' => $t->project->name] : null,
            'assignee'        => $t->assignee ? ['id' => $t->assignee->id, 'name' => $t->assignee->name] : null,
            'tags'            => $t->tags ?? [],
        ]);

        return Inertia::render('Tasks/Index', [
            'tasks'   => $tasks,
            'filters' => $request->only(['status', 'priority', 'assigned', 'overdue', 'project_id']),
        ]);
    }

    public function export(Request $request)
    {
        if (!$request->user()->hasPermission('view', 'task')) {
            abort(403);
        }

        $query = Task::with(['project:id,name', 'assignee:id,name'])
            ->whereNull('parent_task_id')
            ->orderByDesc('updated_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }
        if ($request->assigned === 'me') {
            $query->where('assigned_to', $request->user()->id);
        }
        if ($request->overdue === '1') {
            $query->whereDate('due_date', '<', now())->whereNotIn('status', ['done', 'cancelled']);
        }

        $headers = [
            'id' => 'ID',
            'title' => 'Title',
            'status' => 'Status',
            'priority' => 'Priority',
            'due_date' => 'Due Date',
            'estimated_hours' => 'Estimated Hours',
            'actual_hours' => 'Actual Hours',
            'project.name' => 'Project',
            'assignee.name' => 'Assignee',
            'created_at' => 'Created At',
        ];

        return $this->exportCsv($query, 'tasks_export_' . now()->format('Ymd_His'), $headers);
    }

    public function create(Request $request)
    {
        if (!$request->user()->hasPermission('create', 'task')) {
            abort(403);
        }

        $projects = Project::select('id', 'name')->orderBy('name')->get();
        $members  = User::where('organization_id', $request->user()->organization_id)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Tasks/Create', [
            'projects' => $projects,
            'members'  => $members,
            'defaults' => [
                'project_id' => $request->query('project_id'),
                'status'     => $request->query('status', 'todo'),
            ],
        ]);
    }

    public function store(StoreTaskRequest $request)
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;
        $task = Task::create($data);

        return redirect()->route('web.tasks.show', $task)->with('success', 'Task created.');
    }

    public function show(Request $request, Task $task)
    {
        if (!$request->user()->hasPermission('view', 'task')) {
            abort(403);
        }

        $task->load([
            'project:id,name',
            'assignee:id,name',
            'creator:id,name',
            'comments.user:id,name',
            'timeEntries.user:id,name',
        ]);

        $depIds = TaskDependency::where('task_id', $task->id)->pluck('depends_on_task_id');
        $depTasks = Task::whereIn('id', $depIds)->with('project:id,name')->get()->map(fn($t) => [
            'id'         => $t->id,
            'title'      => $t->title,
            'status'     => is_object($t->status) ? $t->status->value : $t->status,
            'project_id' => $t->project_id,
            'project'    => $t->project ? ['name' => $t->project->name] : null,
        ]);

        $projectTasks = $task->project_id
            ? Task::where('project_id', $task->project_id)
                ->where('id', '!=', $task->id)
                ->select('id', 'title', 'status', 'project_id')
                ->orderBy('title')
                ->get()
                ->map(fn($t) => ['id' => $t->id, 'title' => $t->title, 'status' => is_object($t->status) ? $t->status->value : $t->status, 'project_id' => $t->project_id])
            : [];

        $attachments = Attachment::where('attachable_type', 'task')
            ->where('attachable_id', $task->id)
            ->with('uploader:id,name')
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($a) {
                if ($a->storage_disk === 's3') {
                    $disk = Storage::disk('s3');
                    assert($disk instanceof \Illuminate\Filesystem\FilesystemAdapter);
                    $url = $disk->temporaryUrl($a->storage_path, now()->addHours(2));
                } else {
                    $disk = Storage::disk($a->storage_disk);
                    assert($disk instanceof \Illuminate\Filesystem\FilesystemAdapter);
                    $url = $disk->url($a->storage_path);
                }

                return [
                    'id'                => $a->id,
                    'original_filename' => $a->original_name,
                    'url'               => $url,
                    'size'              => (int) $a->size_bytes,
                    'created_at'        => $a->created_at->toISOString(),
                ];
            });

        $activities = Activity::where('subject_type', 'task')
            ->where('subject_id', $task->id)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($a) => [
                'id'          => $a->id,
                'event'       => $a->event,
                'description' => $a->description,
                'changes'     => $a->changes,
                'created_at'  => $a->created_at->toISOString(),
                'user'        => $a->user ? ['id' => $a->user->id, 'name' => $a->user->name] : null,
            ]);

        return Inertia::render('Tasks/Show', [
            'task' => array_merge(
                $task->toArray(),
                [
                    'project_id'   => $task->project_id,
                    'project'      => $task->project ? ['id' => $task->project->id, 'name' => $task->project->name] : null,
                    'assignee'     => $task->assignee ? ['id' => $task->assignee->id, 'name' => $task->assignee->name] : null,
                    'due_date'     => $task->due_date?->toDateString(),
                    'comments'     => $task->comments->map(fn($c) => [
                        'id'         => $c->id,
                        'body'       => $c->body,
                        'user'       => ['id' => $c->user?->id, 'name' => $c->user?->name],
                        'created_at' => $c->created_at->toISOString(),
                    ]),
                    'attachments'  => $attachments,
                    'time_entries' => $task->timeEntries->map(fn($e) => [
                        'id'          => $e->id,
                        'hours'       => (float) $e->hours,
                        'description' => $e->description,
                        'logged_date' => $e->logged_date?->toDateString(),
                        'user'        => ['id' => $e->user?->id, 'name' => $e->user?->name],
                    ]),
                    'dependencies' => $depTasks,
                    'activities'   => $activities,
                ]
            ),
            'projectTasks' => $projectTasks,
        ]);
    }

    public function edit(Request $request, Task $task)
    {
        if (!$request->user()->hasPermission('update', 'task')) {
            abort(403);
        }

        $projects = Project::select('id', 'name')->orderBy('name')->get();
        $members  = User::where('organization_id', request()->user()->organization_id)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Tasks/Edit', [
            'task'     => $task->load('project:id,name', 'assignee:id,name'),
            'projects' => $projects,
            'members'  => $members,
        ]);
    }

    public function update(UpdateTaskRequest $request, Task $task)
    {
        $data = $request->validated();

        if (isset($data['project_id']) && $data['project_id'] !== $task->project_id) {
            $task->sprint_id = null;
            $task->milestone_id = null;
        }

        // Route status changes through the service so events are dispatched,
        // dependencies are unlocked, and SLA breach logging happens consistently
        // with the API path. Non-status fields update directly.
        if (isset($data['status']) && $data['status'] !== $task->status) {
            $newStatus = $data['status'];
            unset($data['status']);
            if (!empty($data)) {
                $task->update($data);
            }
            $this->taskService->updateTaskStatus($task, $newStatus, $request->user());
        } else {
            $task->update($data);
        }

        return back()->with('success', 'Task updated.');
    }

    public function destroy(Request $request, Task $task)
    {
        if (!$request->user()->hasPermission('delete', 'task')) {
            abort(403);
        }

        $task->delete();
        return redirect()->route('web.tasks.index')->with('success', 'Task deleted.');
    }

    public function move(Request $request, Task $task)
    {
        $data = $request->validate(['status' => 'required|in:backlog,todo,in_progress,in_review,blocked,done,cancelled']);
        $this->taskService->updateTaskStatus($task, $data['status'], $request->user());
        return back();
    }

    public function storeComment(Request $request, Task $task)
    {
        if (!$request->user()->hasPermission('update', 'task')) {
            abort(403);
        }

        $data = $request->validate(['body' => 'required|string|max:5000']);

        Comment::create([
            'commentable_type' => 'task',
            'commentable_id'   => $task->id,
            'user_id'          => $request->user()->id,
            'body'             => $data['body'],
        ]);

        return back()->with('success', 'Comment added.');
    }

    public function logTime(Request $request, Task $task)
    {
        $data = $request->validate([
            'hours'       => 'required|numeric|min:0.25|max:24',
            'description' => 'nullable|string|max:500',
            'logged_date' => 'required|date',
            'is_billable' => 'nullable|boolean',
        ]);

        TimeEntry::create([
            'task_id'         => $task->id,
            'project_id'      => $task->project_id,
            'user_id'         => $request->user()->id,
            'organization_id' => $request->user()->organization_id,
            'hours'           => $data['hours'],
            'description'     => $data['description'] ?? null,
            'logged_date'     => $data['logged_date'],
            'is_billable'     => $data['is_billable'] ?? true,
        ]);

        // Update actual_hours on task
        $task->increment('actual_hours', $data['hours']);

        return back()->with('success', 'Time logged.');
    }

    public function uploadAttachment(Request $request)
    {
        $request->validate([
            'file'             => 'required|file|mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx,txt,csv,mp4,webm|max:51200', // 50MB
            'attachable_type'  => 'required|in:task,project',
            'attachable_id'    => 'required|uuid',
        ]);

        if ($request->attachable_type === 'task') {
            Task::where('id', $request->attachable_id)
                ->where('organization_id', $request->user()->organization_id)
                ->firstOrFail();
        }
        if ($request->attachable_type === 'project') {
            Project::where('id', $request->attachable_id)
                ->where('organization_id', $request->user()->organization_id)
                ->firstOrFail();
        }

        $file = $request->file('file');
        $disk = config('filesystems.default', 'local');
        $path = $file->store(
            'attachments/' . $request->attachable_type . '/' . $request->attachable_id,
            $disk
        );

        Attachment::create([
            'attachable_type' => $request->attachable_type,
            'attachable_id'   => $request->attachable_id,
            'organization_id' => $request->user()->organization_id,
            'filename'        => $file->hashName(),
            'original_name'   => $file->getClientOriginalName(),
            'mime_type'       => $file->getMimeType(),
            'size_bytes'      => $file->getSize(),
            'storage_path'    => $path,
            'storage_disk'    => $disk,
            'uploaded_by'     => $request->user()->id,
        ]);

        return back()->with('success', 'File uploaded.');
    }

    public function destroyComment(Request $request, Task $task, Comment $comment)
    {
        if (!$request->user()->hasPermission('update', 'task')) {
            abort(403);
        }

        abort_if($comment->commentable_type !== 'task' || $comment->commentable_id !== $task->id, 403);
        $comment->delete();
        return back()->with('success', 'Comment deleted.');
    }

    public function destroyTimeEntry(Request $request, Task $task, TimeEntry $timeEntry)
    {
        if (!$request->user()->hasPermission('delete', 'time_entry')) {
            abort(403);
        }

        abort_if($timeEntry->task_id !== $task->id, 403);
        $task->decrement('actual_hours', $timeEntry->hours);
        $timeEntry->delete();
        return back()->with('success', 'Time entry deleted.');
    }

    public function destroyAttachment(Request $request, Attachment $attachment)
    {
        if (!$request->user()->hasPermission('update', 'task')) {
            abort(403);
        }

        Storage::disk($attachment->storage_disk)->delete($attachment->storage_path);
        $attachment->delete();
        return back()->with('success', 'Attachment deleted.');
    }

    public function bulkStore(Request $request, Project $project): \Illuminate\Http\RedirectResponse
    {
        if (!$request->user()->hasPermission('create', 'task')) {
            abort(403);
        }

        $data = $request->validate([
            'tasks'                  => 'required|array|min:1',
            'tasks.*.title'          => 'required|string|max:300',
            'tasks.*.priority'       => 'nullable|in:low,medium,high,critical',
            'tasks.*.due_date'       => 'nullable|date',
            'tasks.*.assigned_to'    => [
                'nullable',
                'uuid',
                Rule::exists('users', 'id')
                    ->where('organization_id', $request->user()->organization_id)
                    ->whereNull('deleted_at'),
            ],
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($data, $request, $project) {
            foreach ($data['tasks'] as $taskData) {
                Task::create([
                    'organization_id' => $request->user()->organization_id,
                    'created_by'      => $request->user()->id,
                    'project_id'      => $project->id,
                    'title'           => $taskData['title'],
                    'priority'        => $taskData['priority'] ?? 'medium',
                    'due_date'        => $taskData['due_date'] ?? null,
                    'assigned_to'     => $taskData['assigned_to'] ?? null,
                    'status'          => 'todo',
                ]);
            }
        });

        return back()->with('success', count($data['tasks']) . ' tasks created.');
    }

    public function addDependency(Request $request, Task $task): \Illuminate\Http\RedirectResponse
    {
        if (!$request->user()->hasPermission('update', 'task')) {
            abort(403);
        }

        $validated = $request->validate([
            'depends_on_task_id' => 'required|uuid',
        ]);

        abort_if($validated['depends_on_task_id'] === $task->id, 422, 'Task cannot depend on itself.');

        $depTask = Task::where('organization_id', $request->user()->organization_id)
            ->where('project_id', $task->project_id)
            ->findOrFail($validated['depends_on_task_id']);

        $isCircular = TaskDependency::where('task_id', $depTask->id)
            ->where('depends_on_task_id', $task->id)
            ->exists();
        abort_if($isCircular, 422, 'Circular dependency detected.');

        TaskDependency::firstOrCreate([
            'task_id'            => $task->id,
            'depends_on_task_id' => $depTask->id,
        ]);

        return back()->with('success', 'Dependency added.');
    }

    public function removeDependency(Request $request, Task $task, Task $dependency): \Illuminate\Http\RedirectResponse
    {
        if (!$request->user()->hasPermission('update', 'task')) {
            abort(403);
        }

        TaskDependency::where('task_id', $task->id)
            ->where('depends_on_task_id', $dependency->id)
            ->delete();

        return back()->with('success', 'Dependency removed.');
    }

    public function bulkDestroy(Request $request): \Illuminate\Http\RedirectResponse
    {
        if (!$request->user()->hasPermission('delete', 'task')) {
            abort(403);
        }

        $data = $request->validate([
            'task_ids'   => 'required|array|min:1',
            'task_ids.*' => 'required|uuid',
        ]);

        Task::whereIn('id', $data['task_ids'])
            ->where('organization_id', $request->user()->organization_id)
            ->delete();

        return back()->with('success', count($data['task_ids']) . ' tasks deleted.');
    }
    public function bulkUpdate(Request $request): \Illuminate\Http\RedirectResponse
    {
        if (!$request->user()->hasPermission('update', 'task')) {
            abort(403);
        }

        $data = $request->validate([
            'task_ids'   => 'required|array|min:1',
            'task_ids.*' => 'required|uuid',
            'status'     => 'nullable|in:backlog,todo,in_progress,in_review,blocked,done,cancelled',
        ]);

        $tasks = Task::whereIn('id', $data['task_ids'])
            ->where('organization_id', $request->user()->organization_id)
            ->get();

        foreach ($tasks as $task) {
            if (isset($data['status'])) {
                $this->taskService->updateTaskStatus($task, $data['status'], $request->user());
            }
        }

        return back()->with('success', count($data['task_ids']) . ' tasks updated.');
    }
}

<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use App\Modules\ProjectManagement\Models\Task;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\ProjectManagement\Models\Comment;
use App\Modules\ProjectManagement\Models\Attachment;
use App\Modules\ProjectManagement\Models\TimeEntry;
use App\Modules\ProjectManagement\Models\TaskLog;
use App\Modules\Auth\Models\User;

class TaskController extends Controller
{
    public function index(Request $request)
    {
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
            'status'          => $t->status,
            'priority'        => $t->priority,
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

    public function create(Request $request)
    {
        $projects = Project::select('id', 'name')->orderBy('name')->get();
        $members  = User::select('id', 'name')->orderBy('name')->get();

        return Inertia::render('Tasks/Create', [
            'projects' => $projects,
            'members'  => $members,
            'defaults' => [
                'project_id' => $request->query('project_id'),
                'status'     => $request->query('status', 'todo'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'           => 'required|string|max:300',
            'description'     => 'nullable|string',
            'project_id'      => 'required|uuid|exists:projects,id',
            'status'          => 'required|in:backlog,todo,in_progress,in_review,blocked,done,cancelled',
            'priority'        => 'required|in:urgent,high,medium,low',
            'assigned_to'     => 'nullable|uuid|exists:users,id',
            'due_date'        => 'nullable|date',
            'estimated_hours' => 'nullable|numeric|min:0',
            'tags'            => 'nullable|array',
            'type'            => 'nullable|string',
            'sprint_id'       => 'nullable|uuid',
        ]);

        $task = Task::create([
            ...$data,
            'organization_id' => $request->user()->organization_id,
            'created_by'      => $request->user()->id,
        ]);

        return redirect()->route('web.tasks.show', $task)->with('success', 'Task created.');
    }

    public function show(Task $task)
    {
        $task->load([
            'project:id,name',
            'assignee:id,name',
            'creator:id,name',
            'comments.user:id,name',
            'timeEntries.user:id,name',
        ]);

        $attachments = Attachment::where('attachable_type', 'task')
            ->where('attachable_id', $task->id)
            ->with('uploader:id,name')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($a) => [
                'id'                => $a->id,
                'original_filename' => $a->original_name,
                'url'               => $a->storage_disk === 's3'
                    ? Storage::disk('s3')->temporaryUrl($a->storage_path, now()->addHours(2))
                    : Storage::disk($a->storage_disk)->url($a->storage_path),
                'size'              => (int) $a->size_bytes,
                'created_at'        => $a->created_at->toISOString(),
            ]);

        return Inertia::render('Tasks/Show', [
            'task' => array_merge(
                $task->toArray(),
                [
                    'project_id'  => $task->project_id,
                    'project'     => $task->project ? ['id' => $task->project->id, 'name' => $task->project->name] : null,
                    'assignee'    => $task->assignee ? ['id' => $task->assignee->id, 'name' => $task->assignee->name] : null,
                    'due_date'    => $task->due_date?->toDateString(),
                    'comments'    => $task->comments->map(fn($c) => [
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
                ]
            ),
        ]);
    }

    public function edit(Task $task)
    {
        $projects = Project::select('id', 'name')->orderBy('name')->get();
        $members  = User::select('id', 'name')->orderBy('name')->get();

        return Inertia::render('Tasks/Edit', [
            'task'     => $task->load('project:id,name', 'assignee:id,name'),
            'projects' => $projects,
            'members'  => $members,
        ]);
    }

    public function update(Request $request, Task $task)
    {
        $data = $request->validate([
            'title'           => 'sometimes|string|max:300',
            'description'     => 'nullable|string',
            'status'          => 'sometimes|in:backlog,todo,in_progress,in_review,blocked,done,cancelled',
            'priority'        => 'sometimes|in:urgent,high,medium,low',
            'assigned_to'     => 'nullable|uuid|exists:users,id',
            'due_date'        => 'nullable|date',
            'estimated_hours' => 'nullable|numeric|min:0',
            'tags'            => 'nullable|array',
        ]);

        $oldStatus = $task->status;
        $task->update($data);

        // Log status change
        if (isset($data['status']) && $oldStatus !== $data['status']) {
            TaskLog::create([
                'task_id'   => $task->id,
                'user_id'   => $request->user()->id,
                'action'    => 'status_changed',
                'old_value' => ['status' => $oldStatus],
                'new_value' => ['status' => $data['status']],
                'logged_at' => now(),
            ]);
        }

        return back()->with('success', 'Task updated.');
    }

    public function destroy(Task $task)
    {
        $task->delete();
        return redirect()->route('web.tasks.index')->with('success', 'Task deleted.');
    }

    public function move(Request $request, Task $task)
    {
        $data = $request->validate(['status' => 'required|in:backlog,todo,in_progress,in_review,blocked,done,cancelled']);
        $task->update($data);
        return back();
    }

    public function storeComment(Request $request, Task $task)
    {
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
        ]);

        TimeEntry::create([
            'task_id'         => $task->id,
            'project_id'      => $task->project_id,
            'user_id'         => $request->user()->id,
            'organization_id' => $request->user()->organization_id,
            'hours'           => $data['hours'],
            'description'     => $data['description'] ?? null,
            'logged_date'     => $data['logged_date'],
        ]);

        // Update actual_hours on task
        $task->increment('actual_hours', $data['hours']);

        return back()->with('success', 'Time logged.');
    }

    public function uploadAttachment(Request $request)
    {
        $request->validate([
            'file'             => 'required|file|max:51200', // 50MB
            'attachable_type'  => 'required|in:task,project',
            'attachable_id'    => 'required|string',
        ]);

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

    public function destroyComment(Task $task, Comment $comment)
    {
        abort_if($comment->commentable_type !== 'task' || $comment->commentable_id !== $task->id, 403);
        $comment->delete();
        return back()->with('success', 'Comment deleted.');
    }

    public function destroyTimeEntry(Task $task, TimeEntry $timeEntry)
    {
        abort_if($timeEntry->task_id !== $task->id, 403);
        $task->decrement('actual_hours', $timeEntry->hours);
        $timeEntry->delete();
        return back()->with('success', 'Time entry deleted.');
    }

    public function destroyAttachment(Request $request, Attachment $attachment)
    {
        Storage::disk($attachment->storage_disk)->delete($attachment->storage_path);
        $attachment->delete();
        return back()->with('success', 'Attachment deleted.');
    }
}

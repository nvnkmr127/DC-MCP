<?php
use Illuminate\Support\Facades\Route;
use App\Modules\ProjectManagement\Http\Controllers\ProjectWebController;
use App\Modules\ProjectManagement\Http\Controllers\TaskWebController;
use App\Modules\ProjectManagement\Http\Controllers\ClientWebController;
use App\Modules\ProjectManagement\Http\Controllers\SprintWebController;
use App\Modules\ProjectManagement\Http\Controllers\TimesheetWebController;
use App\Modules\ProjectManagement\Http\Controllers\CalendarWebController;
use App\Modules\ProjectManagement\Http\Controllers\AssetApprovalWebController;
use App\Modules\ProjectManagement\Http\Controllers\IssueWebController;
use App\Modules\ProjectManagement\Http\Controllers\ProjectTemplateWebController;
use App\Modules\ProjectManagement\Http\Controllers\ClientCommunicationWebController;
use App\Modules\ProjectManagement\Http\Controllers\DeliverableWebController;
use App\Modules\ProjectManagement\Http\Controllers\GoalWebController;

Route::middleware(['auth'])->group(function () {
    // Projects
    Route::get('/projects',                    [ProjectWebController::class, 'index'])->name('web.projects.index');
    Route::get('/projects/create',             [ProjectWebController::class, 'create'])->name('web.projects.create');
    Route::post('/projects',                   [ProjectWebController::class, 'store'])->name('web.projects.store');
    Route::get('/projects/{project}',          [ProjectWebController::class, 'show'])->name('web.projects.show');
    Route::get('/projects/{project}/edit',     [ProjectWebController::class, 'edit'])->name('web.projects.edit');
    Route::patch('/projects/{project}',        [ProjectWebController::class, 'update'])->name('web.projects.update');
    Route::delete('/projects/{project}',       [ProjectWebController::class, 'destroy'])->name('web.projects.destroy');
    Route::get('/projects/{project}/kanban',   [ProjectWebController::class, 'kanban'])->name('web.projects.kanban');
    Route::get('/projects/{project}/stats',    [ProjectWebController::class, 'stats'])->name('web.projects.stats');
    Route::post('/projects/{project}/bulk-tasks',            [TaskWebController::class, 'bulkStore'])->name('web.projects.bulk-tasks');

    // Tasks
    Route::get('/tasks',                       [TaskWebController::class, 'index'])->name('web.tasks.index');
    Route::get('/tasks/create',                [TaskWebController::class, 'create'])->name('web.tasks.create');
    Route::post('/tasks',                      [TaskWebController::class, 'store'])->name('web.tasks.store');
    Route::get('/tasks/{task}',                [TaskWebController::class, 'show'])->name('web.tasks.show');
    Route::get('/tasks/{task}/edit',           [TaskWebController::class, 'edit'])->name('web.tasks.edit');
    Route::patch('/tasks/{task}',              [TaskWebController::class, 'update'])->name('web.tasks.update');
    Route::delete('/tasks/{task}',             [TaskWebController::class, 'destroy'])->name('web.tasks.destroy');
    Route::post('/tasks/{task}/move',          [TaskWebController::class, 'move'])->name('web.tasks.move');
    Route::post('/tasks/{task}/comments',      [TaskWebController::class, 'storeComment'])->name('web.tasks.comments.store');
    Route::post('/tasks/{task}/log-time',      [TaskWebController::class, 'logTime'])->name('web.tasks.time.store');
    Route::post('/attachments',                [TaskWebController::class, 'uploadAttachment'])->name('web.attachments.store');
    Route::delete('/attachments/{attachment}', [TaskWebController::class, 'destroyAttachment'])->name('web.attachments.destroy');
    Route::delete('/tasks/{task}/comments/{comment}',       [TaskWebController::class, 'destroyComment'])->name('web.tasks.comments.destroy');
    Route::delete('/tasks/{task}/time-entries/{timeEntry}', [TaskWebController::class, 'destroyTimeEntry'])->name('web.tasks.time.destroy');
    Route::post('/tasks/{task}/dependencies',                [TaskWebController::class, 'addDependency'])->name('web.tasks.dependencies.add');
    Route::delete('/tasks/{task}/dependencies/{dependency}', [TaskWebController::class, 'removeDependency'])->name('web.tasks.dependencies.remove');

    // Clients
    Route::get('/clients',                     [ClientWebController::class, 'index'])->name('web.clients.index');
    Route::get('/clients/create',              [ClientWebController::class, 'create'])->name('web.clients.create');
    Route::post('/clients',                    [ClientWebController::class, 'store'])->name('web.clients.store');
    Route::get('/clients/{client}',            [ClientWebController::class, 'show'])->name('web.clients.show');
    Route::get('/clients/{client}/edit',       [ClientWebController::class, 'edit'])->name('web.clients.edit');
    Route::patch('/clients/{client}',          [ClientWebController::class, 'update'])->name('web.clients.update');
    Route::delete('/clients/{client}',         [ClientWebController::class, 'destroy'])->name('web.clients.destroy');
    Route::patch('/clients/{client}/upsell',                 [ClientWebController::class, 'flagUpsell'])->name('web.clients.upsell');
    Route::post('/clients/{client}/success-score',           [ClientWebController::class, 'updateSuccessScore'])->name('web.clients.success-score');
    Route::post('/clients/{client}/communications',          [ClientCommunicationWebController::class, 'store'])->name('web.client-communications.store');
    Route::patch('/client-communications/{communication}',   [ClientCommunicationWebController::class, 'update'])->name('web.client-communications.update');
    Route::delete('/client-communications/{communication}',  [ClientCommunicationWebController::class, 'destroy'])->name('web.client-communications.destroy');

    // Sprints
    Route::get('/sprints',                                   [SprintWebController::class, 'index'])->name('web.sprints.index');
    Route::post('/sprints',                                  [SprintWebController::class, 'store'])->name('web.sprints.store');
    Route::patch('/sprints/{sprint}',                        [SprintWebController::class, 'update'])->name('web.sprints.update');
    Route::delete('/sprints/{sprint}',                       [SprintWebController::class, 'destroy'])->name('web.sprints.destroy');
    Route::post('/sprints/{sprint}/tasks',                   [SprintWebController::class, 'addTask'])->name('web.sprints.tasks.add');
    Route::delete('/sprints/{sprint}/tasks/{task}',          [SprintWebController::class, 'removeTask'])->name('web.sprints.tasks.remove');

    // Timesheets
    Route::get('/timesheets',                                [TimesheetWebController::class, 'index'])->name('web.timesheets.index');
    Route::post('/timesheets/timer/start',                   [TimesheetWebController::class, 'startTimer'])->name('web.timesheets.timer.start');
    Route::post('/timesheets/timer/{timeEntry}/stop',        [TimesheetWebController::class, 'stopTimer'])->name('web.timesheets.timer.stop');

    // Calendar
    Route::get('/calendar',                    [CalendarWebController::class, 'index'])->name('web.calendar.index');

    // Asset Approvals
    Route::get('/asset-approvals',                           [AssetApprovalWebController::class, 'index'])->name('web.asset-approvals.index');
    Route::post('/asset-approvals',                          [AssetApprovalWebController::class, 'store'])->name('web.asset-approvals.store');
    Route::patch('/asset-approvals/{approval}',              [AssetApprovalWebController::class, 'update'])->name('web.asset-approvals.update');
    Route::delete('/asset-approvals/{approval}',             [AssetApprovalWebController::class, 'destroy'])->name('web.asset-approvals.destroy');

    // Issues
    Route::get('/issues',                                    [IssueWebController::class, 'index'])->name('web.issues.index');
    Route::post('/issues',                                   [IssueWebController::class, 'store'])->name('web.issues.store');
    Route::patch('/issues/{issue}',                          [IssueWebController::class, 'update'])->name('web.issues.update');
    Route::delete('/issues/{issue}',                         [IssueWebController::class, 'destroy'])->name('web.issues.destroy');
    Route::post('/issues/{issue}/task',                      [IssueWebController::class, 'convertToTask'])->name('web.issues.task');

    // Project Templates
    Route::get('/project-templates',                         [ProjectTemplateWebController::class, 'index'])->name('web.project-templates.index');
    Route::post('/project-templates',                        [ProjectTemplateWebController::class, 'store'])->name('web.project-templates.store');
    Route::patch('/project-templates/{template}',            [ProjectTemplateWebController::class, 'update'])->name('web.project-templates.update');
    Route::delete('/project-templates/{template}',           [ProjectTemplateWebController::class, 'destroy'])->name('web.project-templates.destroy');
    Route::post('/project-templates/{template}/create-project', [ProjectTemplateWebController::class, 'createProject'])->name('web.project-templates.create-project');

    // Deliverable Submissions
    Route::post('/sow/deliverables/{sowDeliverable}/submit',        [DeliverableWebController::class, 'submit'])->name('web.deliverables.submit');
    Route::post('/deliverables/{deliverableSubmission}/approve',    [DeliverableWebController::class, 'approve'])->name('web.deliverables.approve');
    Route::post('/deliverables/{deliverableSubmission}/revision',   [DeliverableWebController::class, 'requestRevision'])->name('web.deliverables.revision');

    // Goals & OKRs
    Route::get('/goals',                                    [GoalWebController::class, 'index'])->name('web.goals.index');
    Route::post('/goals',                                   [GoalWebController::class, 'store'])->name('web.goals.store');
    Route::patch('/goals/{goal}',                           [GoalWebController::class, 'update'])->name('web.goals.update');
    Route::delete('/goals/{goal}',                          [GoalWebController::class, 'destroy'])->name('web.goals.destroy');
    Route::patch('/goals/{goal}/kr',                        [GoalWebController::class, 'updateKeyResult'])->name('web.goals.kr');
});

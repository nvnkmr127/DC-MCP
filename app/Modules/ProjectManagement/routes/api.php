<?php

use Illuminate\Support\Facades\Route;
use App\Modules\ProjectManagement\Http\Controllers\Api\V1\KanbanApiController;
use App\Modules\ProjectManagement\Http\Controllers\Api\V1\ProjectApiController;
use App\Modules\ProjectManagement\Http\Controllers\Api\V1\TimeEntryApiController;
use App\Modules\ProjectManagement\Http\Controllers\Api\V1\TaskApiController;
use App\Modules\ProjectManagement\Http\Controllers\Api\V1\SprintApiController;
use App\Modules\ProjectManagement\Http\Controllers\Api\V1\MilestoneApiController;
use App\Modules\ProjectManagement\Http\Controllers\Api\V1\ClientApiController;
use App\Modules\ProjectManagement\Http\Controllers\Api\V1\DeliverableApiController;
use App\Modules\ProjectManagement\Http\Controllers\Api\V1\GoalApiController;
use App\Modules\ProjectManagement\Http\Controllers\Api\V1\CapacityApiController;
use App\Modules\ProjectManagement\Http\Controllers\Api\V1\OnboardingApiController;
use App\Modules\ProjectManagement\Http\Controllers\Api\V1\AuditChecklistApiController;

Route::prefix('v1')->group(function () {
    Route::get('organizations/team-workload', [ProjectApiController::class, 'teamWorkload']);
    Route::get('projects/{project}/kanban', [KanbanApiController::class, 'board']);
    Route::get('projects/{project}/stats', [ProjectApiController::class, 'stats']);
    
    Route::apiResource('projects', ProjectApiController::class);
    
    Route::post('sprints/{sprint}/start', [SprintApiController::class, 'start']);
    Route::post('sprints/{sprint}/complete', [SprintApiController::class, 'complete']);
    Route::apiResource('sprints', SprintApiController::class);
    
    Route::apiResource('milestones', MilestoneApiController::class);
    
    Route::post('tasks/{task}/assign', [TaskApiController::class, 'assign']);
    Route::post('tasks/{task}/log-time', [TaskApiController::class, 'logTime']);
    Route::post('tasks/{task}/move', [TaskApiController::class, 'move']);
    Route::apiResource('tasks', TaskApiController::class);
    
    Route::get('time-entries/summary', [TimeEntryApiController::class, 'summary']);
    Route::apiResource('time-entries', TimeEntryApiController::class);
    
    Route::apiResource('clients', ClientApiController::class);
    
    Route::apiResource('deliverables', DeliverableApiController::class);
    Route::apiResource('goals', GoalApiController::class);
    Route::get('capacity', [CapacityApiController::class, 'index']);
    Route::apiResource('onboardings', OnboardingApiController::class);
    Route::apiResource('audit-checklists', AuditChecklistApiController::class);
    
});
<?php

use Illuminate\Support\Facades\Route;
use App\Modules\ProjectManagement\Http\Controllers\Api\KanbanApiController;
use App\Modules\ProjectManagement\Http\Controllers\Api\ProjectApiController;
use App\Modules\ProjectManagement\Http\Controllers\Api\TimeEntryApiController;
use App\Modules\ProjectManagement\Http\Controllers\Api\TaskApiController;
use App\Modules\ProjectManagement\Http\Controllers\Api\SprintApiController;
use App\Modules\ProjectManagement\Http\Controllers\Api\MilestoneApiController;
use App\Modules\ProjectManagement\Http\Controllers\Api\ClientApiController;

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

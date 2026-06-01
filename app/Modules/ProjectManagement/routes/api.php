<?php

use Illuminate\Support\Facades\Route;

Route::get('organizations/team-workload', 'ProjectApiController@teamWorkload');
Route::get('projects/{project}/kanban', 'KanbanApiController@board');
Route::get('projects/{project}/stats', 'ProjectApiController@stats');

Route::apiResource('projects', 'ProjectApiController');

Route::post('sprints/{sprint}/start', 'SprintApiController@start');
Route::post('sprints/{sprint}/complete', 'SprintApiController@complete');
Route::apiResource('sprints', 'SprintApiController');

Route::apiResource('milestones', 'MilestoneApiController');

Route::post('tasks/{task}/assign', 'TaskApiController@assign');
Route::post('tasks/{task}/log-time', 'TaskApiController@logTime');
Route::post('tasks/{task}/move', 'TaskApiController@move');
Route::apiResource('tasks', 'TaskApiController');

Route::get('time-entries/summary', 'TimeEntryApiController@summary');
Route::apiResource('time-entries', 'TimeEntryApiController');

Route::apiResource('clients', 'ClientApiController');

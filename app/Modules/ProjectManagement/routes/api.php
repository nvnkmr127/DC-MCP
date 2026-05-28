<?php

use Illuminate\Support\Facades\Route;

Route::get('organizations/team-workload', 'ProjectController@teamWorkload');
Route::get('projects/{project}/kanban', 'KanbanController@board');
Route::get('projects/{project}/stats', 'ProjectController@stats');

Route::apiResource('projects', 'ProjectController');

Route::post('sprints/{sprint}/start', 'SprintController@start');
Route::post('sprints/{sprint}/complete', 'SprintController@complete');
Route::apiResource('sprints', 'SprintController');

Route::apiResource('milestones', 'MilestoneController');

Route::post('tasks/{task}/assign', 'TaskController@assign');
Route::post('tasks/{task}/log-time', 'TaskController@logTime');
Route::post('tasks/{task}/move', 'TaskController@move');
Route::apiResource('tasks', 'TaskController');

Route::get('time-entries/summary', 'TimeEntryController@summary');
Route::apiResource('time-entries', 'TimeEntryController');

Route::apiResource('clients', 'ClientController');

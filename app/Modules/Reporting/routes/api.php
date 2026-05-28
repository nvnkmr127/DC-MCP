<?php

use Illuminate\Support\Facades\Route;

Route::get('reports/tasks', 'ReportingController@taskSummary');
Route::get('reports/projects', 'ReportingController@projectSummary');
Route::get('reports/team-productivity', 'ReportingController@teamProductivity');
Route::get('reports/time', 'ReportingController@timeReport');

// Core Report routes
Route::get('reports', 'ReportController@index');
Route::post('reports', 'ReportController@store');
Route::get('reports/{report}', 'ReportController@show');
Route::post('reports/{report}/generate', 'ReportController@generate');
Route::get('reports/{report}/download', 'ReportController@download');
Route::post('reports/{report}/send', 'ReportController@send');

// Scheduled Report routes
Route::get('report-schedules', 'ReportScheduleController@index');
Route::post('report-schedules', 'ReportScheduleController@store');
Route::put('report-schedules/{schedule}', 'ReportScheduleController@update');
Route::delete('report-schedules/{schedule}', 'ReportScheduleController@destroy');

<?php

use Illuminate\Support\Facades\Route;

Route::get('reports/tasks', 'ReportingApiController@taskSummary');
Route::get('reports/projects', 'ReportingApiController@projectSummary');
Route::get('reports/team-productivity', 'ReportingApiController@teamProductivity');
Route::get('reports/time', 'ReportingApiController@timeReport');

// Core Report routes
Route::get('reports', 'ReportApiController@index');
Route::post('reports', 'ReportApiController@store');
Route::get('reports/{report}', 'ReportApiController@show');
Route::post('reports/{report}/generate', 'ReportApiController@generate');
Route::get('reports/{report}/download', 'ReportApiController@download');
Route::post('reports/{report}/send', 'ReportApiController@send');

// Scheduled Report routes
Route::get('report-schedules', 'ReportScheduleApiController@index');
Route::post('report-schedules', 'ReportScheduleApiController@store');
Route::put('report-schedules/{schedule}', 'ReportScheduleApiController@update');
Route::delete('report-schedules/{schedule}', 'ReportScheduleApiController@destroy');

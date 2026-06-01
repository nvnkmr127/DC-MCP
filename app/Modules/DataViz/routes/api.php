<?php

use Illuminate\Support\Facades\Route;

Route::get('dashboard/overview', 'DashboardApiController@overview');
Route::get('dashboard/project-velocity', 'DashboardApiController@projectVelocity');
Route::get('dashboard/meta-ads', 'DashboardApiController@metaAdsSnapshot');

// Customizable Dashboard configs
Route::get('dashboards', 'DashboardApiController@index');
Route::post('dashboards', 'DashboardApiController@store');
Route::put('dashboards/{dashboard}', 'DashboardApiController@update');
Route::get('dashboards/{dashboard}/data', 'DashboardApiController@data');

// Viz Query Engine
Route::get('viz/kpis', 'DashboardApiController@kpis');
Route::post('viz/query', 'DashboardApiController@query');

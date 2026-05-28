<?php

use Illuminate\Support\Facades\Route;

Route::get('dashboard/overview', 'DashboardController@overview');
Route::get('dashboard/project-velocity', 'DashboardController@projectVelocity');
Route::get('dashboard/meta-ads', 'DashboardController@metaAdsSnapshot');

// Customizable Dashboard configs
Route::get('dashboards', 'DashboardController@index');
Route::post('dashboards', 'DashboardController@store');
Route::put('dashboards/{dashboard}', 'DashboardController@update');
Route::get('dashboards/{dashboard}/data', 'DashboardController@data');

// Viz Query Engine
Route::get('viz/kpis', 'DashboardController@kpis');
Route::post('viz/query', 'DashboardController@query');

<?php

use Illuminate\Support\Facades\Route;
use App\Modules\DataViz\Http\Controllers\Api\DashboardApiController;

Route::get('dashboard/overview', [DashboardApiController::class, 'overview']);
Route::get('dashboard/project-velocity', [DashboardApiController::class, 'projectVelocity']);
Route::get('dashboard/meta-ads', [DashboardApiController::class, 'metaAdsSnapshot']);

// Customizable Dashboard configs
Route::get('dashboards', [DashboardApiController::class, 'index']);
Route::post('dashboards', [DashboardApiController::class, 'store']);
Route::put('dashboards/{dashboard}', [DashboardApiController::class, 'update']);
Route::get('dashboards/{dashboard}/data', [DashboardApiController::class, 'data']);

// Viz Query Engine
Route::get('viz/kpis', [DashboardApiController::class, 'kpis']);
Route::post('viz/query', [DashboardApiController::class, 'query']);

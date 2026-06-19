<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Reporting\Http\Controllers\Api\V1\ReportApiController;
use App\Modules\Reporting\Http\Controllers\Api\V1\ReportScheduleApiController;
use App\Modules\Reporting\Http\Controllers\Api\V1\ReportingApiController;

Route::prefix('v1')->group(function () {
    Route::get('reports/tasks', [ReportingApiController::class, 'taskSummary']);
    Route::get('reports/projects', [ReportingApiController::class, 'projectSummary']);
    Route::get('reports/team-productivity', [ReportingApiController::class, 'teamProductivity']);
    Route::get('reports/time', [ReportingApiController::class, 'timeReport']);
    
    // Core Report routes
    Route::get('reports', [ReportApiController::class, 'index']);
    Route::post('reports', [ReportApiController::class, 'store']);
    Route::get('reports/{report}', [ReportApiController::class, 'show']);
    Route::post('reports/{report}/generate', [ReportApiController::class, 'generate']);
    Route::get('reports/{report}/download', [ReportApiController::class, 'download']);
    Route::post('reports/{report}/send', [ReportApiController::class, 'send']);
    
    // Scheduled Report routes
    Route::get('report-schedules', [ReportScheduleApiController::class, 'index']);
    Route::post('report-schedules', [ReportScheduleApiController::class, 'store']);
    Route::put('report-schedules/{schedule}', [ReportScheduleApiController::class, 'update']);
    Route::delete('report-schedules/{schedule}', [ReportScheduleApiController::class, 'destroy']);
    
});
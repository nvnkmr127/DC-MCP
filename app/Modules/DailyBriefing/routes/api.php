<?php

use Illuminate\Support\Facades\Route;
use App\Modules\DailyBriefing\Http\Controllers\Api\DailyBriefingApiController;

Route::get('daily-briefings/today', [DailyBriefingApiController::class, 'today']);
Route::post('daily-briefings/generate', [DailyBriefingApiController::class, 'generate']);
Route::apiResource('daily-briefings', DailyBriefingApiController::class)
    ->only(['index', 'show'])
    ->parameters(['daily-briefings' => 'briefing']);

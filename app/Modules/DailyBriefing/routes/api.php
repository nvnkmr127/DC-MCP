<?php

use Illuminate\Support\Facades\Route;

Route::get('daily-briefings/today', 'DailyBriefingApiController@today');
Route::post('daily-briefings/generate', 'DailyBriefingApiController@generate');
Route::apiResource('daily-briefings', 'DailyBriefingApiController')
    ->only(['index', 'show'])
    ->parameters(['daily-briefings' => 'briefing']);

<?php

use Illuminate\Support\Facades\Route;

Route::get('daily-briefings/today', 'DailyBriefingController@today');
Route::post('daily-briefings/generate', 'DailyBriefingController@generate');
Route::apiResource('daily-briefings', 'DailyBriefingController')
    ->only(['index', 'show'])
    ->parameters(['daily-briefings' => 'briefing']);

<?php

use Illuminate\Support\Facades\Route;

// Public auth endpoints — api middleware + auth rate limiter
Route::prefix('api/v1')->middleware(['api', 'throttle:auth'])->group(function () {
    Route::post('auth/register', 'RegisterController@register');
    Route::post('auth/login', 'LoginController@login');
});

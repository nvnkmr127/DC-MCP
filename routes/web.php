<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — Inertia SPA
|--------------------------------------------------------------------------
|
| The root routes file now only handles base-level actions and redirects.
| Domain-specific routes are modularized inside their respective modules routes folders.
|
*/

Route::middleware(['auth'])->group(function () {
    // Redirect root to dashboard
    Route::get('/', fn() => redirect()->to('/dashboard'));
    
    // /settings -> redirect to profile
    Route::get('/settings', fn() => redirect()->to('/settings/profile'));
    
    // Help page
    Route::get('/help', fn() => \Inertia\Inertia::render('Help/Index'))->name('web.help');
});

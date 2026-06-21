<?php

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

// Detailed health check — restricted to localhost and trusted monitoring IPs.
// Set HEALTH_ALLOWED_IPS=127.0.0.1,10.0.0.0/8 in production.
Route::get('/health/detailed', HealthController::class)
    ->middleware(\App\Http\Middleware\AllowedHealthIps::class);

// Lightweight canary deployment health check (accessible to ELB/K8s probes)
Route::get('/health/canary', [HealthController::class, 'canary']);

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
    
    // Global Search
    Route::get('/api/search', \App\Http\Controllers\SearchController::class);
    
    // /settings -> redirect to profile
    Route::get('/settings', fn() => redirect()->to('/settings/profile'));
    
    // Help page
    Route::get('/help', fn() => \Inertia\Inertia::render('Help/Index'))->name('web.help');

    // Admin Diagnostics
    Route::middleware(['role:super_admin'])->group(function () {
        Route::get('/admin/diagnostics', [\App\Http\Controllers\Admin\DiagnosticController::class, 'index'])
            ->name('admin.diagnostics');
    });
});

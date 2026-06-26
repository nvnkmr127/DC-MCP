<?php

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

// Detailed health check — restricted to localhost and trusted monitoring IPs.
// Set HEALTH_ALLOWED_IPS=127.0.0.1,10.0.0.0/8 in production.
Route::get('/health/detailed', HealthController::class)
    ->middleware(\App\Http\Middleware\AllowedHealthIps::class);

// Lightweight canary deployment health check (accessible to ELB/K8s probes)
Route::get('/health/canary', [HealthController::class, 'canary']);

// Webhook Routes (Public, protected by their own signature/IP verification)
Route::post('/webhooks/mcp/{provider}/{connectionId}', [\App\Modules\MCP\Http\Controllers\Api\V1\McpConnectionApiController::class, 'webhook'])
    ->middleware([
        'throttle:webhooks',
        \App\Http\Middleware\McpWebhookIpAllowlist::class,
        'webhook.signature'
    ]);

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

    // Admin Diagnostics & Hub
    Route::middleware(['role:super_admin'])->group(function () {
        Route::post('/admin/impersonate/{user}', [\App\Http\Controllers\Admin\McpAdminController::class, 'impersonate'])
            ->name('admin.impersonate');
        Route::get('/admin/integrations', [\App\Http\Controllers\Admin\IntegrationsController::class, 'index']);
        Route::get('/admin/mcp/{connection}/history', [\App\Http\Controllers\Admin\McpAdminController::class, 'history'])
            ->name('admin.mcp.history');
        Route::post('/admin/mcp/{connection}/migrate', [\App\Http\Controllers\Admin\McpAdminController::class, 'migrate'])
            ->name('admin.mcp.migrate');
        Route::post('/admin/mcp/providers', [\App\Http\Controllers\Admin\McpAdminController::class, 'storeProvider'])
            ->name('admin.mcp.providers.store');
        Route::put('/admin/mcp/providers/{provider}', [\App\Http\Controllers\Admin\McpAdminController::class, 'updateProvider'])
            ->name('admin.mcp.providers.update');
        
        // Audit Logs
        Route::get('/admin/audit-logs', [\App\Http\Controllers\Admin\AuditLogController::class, 'index'])
            ->name('admin.audit-logs');
            
        // Feature Flags
        Route::get('/admin/feature-flags', [\App\Http\Controllers\Admin\FeatureFlagController::class, 'index'])
            ->name('admin.feature-flags');
        Route::post('/admin/feature-flags', [\App\Http\Controllers\Admin\FeatureFlagController::class, 'store'])
            ->name('admin.feature-flags.store');
        Route::post('/admin/feature-flags/{featureFlag}/toggle', [\App\Http\Controllers\Admin\FeatureFlagController::class, 'toggle'])
            ->name('admin.feature-flags.toggle');
        Route::delete('/admin/feature-flags/{featureFlag}', [\App\Http\Controllers\Admin\FeatureFlagController::class, 'destroy'])
            ->name('admin.feature-flags.destroy');
    });
});

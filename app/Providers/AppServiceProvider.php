<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        // General API: 120 requests per minute per user (or 30 per IP for guests)
        RateLimiter::for('api', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(120)->by($request->user()->id)
                : Limit::perMinute(30)->by($request->ip());
        });

        // Auth endpoints: 10 attempts per minute per IP to limit brute-force
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // MCP sync triggers: 20 per minute per org
        RateLimiter::for('mcp-sync', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(20)->by($request->user()->organization_id)
                : Limit::perMinute(5)->by($request->ip());
        });
    }
}

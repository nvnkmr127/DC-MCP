<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class TenantIsolationMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $isPgsql = DB::connection()->getDriverName() === 'pgsql';

        if (auth()->check() && auth()->user()->organization_id) {
            $orgId = auth()->user()->organization_id;
            if ($isPgsql) {
                DB::statement("SET app.current_tenant_id = '{$orgId}'");
                DB::statement("SET app.bypass_rls = 'off'");
            }
        } else {
            if ($isPgsql) {
                DB::statement("SET app.bypass_rls = 'on'");
            }
        }

        return $next($request);
    }
}

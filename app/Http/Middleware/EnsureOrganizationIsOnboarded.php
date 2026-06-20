<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationIsOnboarded
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || !$request->user()->organization) {
            return $next($request);
        }

        $org = $request->user()->organization;

        if (!$org->is_onboarded) {
            $except = [
                'setup',
                'setup/*',
                'logout',
                'settings/profile',
                'api/v1/auth/*',
            ];

            if (!$request->is(...$except)) {
                return redirect()->route('web.setup.index');
            }
        }

        return $next($request);
    }
}

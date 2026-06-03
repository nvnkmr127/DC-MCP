<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AllowedHealthIps
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowed = array_filter(array_map(
            'trim',
            explode(',', env('HEALTH_ALLOWED_IPS', '127.0.0.1,::1'))
        ));

        if (!in_array($request->ip(), $allowed, true)) {
            abort(403);
        }

        return $next($request);
    }
}

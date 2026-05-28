<?php

namespace App\Modules\Auth\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!auth()->check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user = auth()->user();
        if (!$user || !$user->hasRoles($roles)) {
            return response()->json(['message' => 'Unauthorized. Required roles: ' . implode(', ', $roles)], 403);
        }

        return $next($request);
    }
}

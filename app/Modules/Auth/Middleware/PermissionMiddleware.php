<?php

namespace App\Modules\Auth\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $action, string $resource): Response
    {
        if (!auth()->check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user = auth()->user();
        if (!$user || !$user->hasPermission($resource, $action)) {
            return response()->json(['message' => "Unauthorized. Missing permission: {$action} on {$resource}"], 403);
        }

        return $next($request);
    }
}

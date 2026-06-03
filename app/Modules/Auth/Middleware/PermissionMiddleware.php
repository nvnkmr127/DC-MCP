<?php

namespace App\Modules\Auth\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
            Log::warning('Permission denied', [
                'user_id'  => $user?->id,
                'route'    => $request->path(),
                'method'   => $request->method(),
                'resource' => $resource,
                'action'   => $action,
                'ip'       => $request->ip(),
            ]);
            return response()->json(['message' => "Unauthorized. Missing permission: {$action} on {$resource}"], 403);
        }

        return $next($request);
    }
}

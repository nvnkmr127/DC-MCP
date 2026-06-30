<?php

namespace App\Modules\Auth\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
        
        // Handle piped roles like 'ceo|project_manager'
        $parsedRoles = [];
        foreach ($roles as $roleGroup) {
            $parsedRoles = array_merge($parsedRoles, explode('|', $roleGroup));
        }

        if (!$user || !$user->hasRoles($parsedRoles)) {
            Log::warning('Role check denied', [
                'user_id'        => $user?->id,
                'user_role'      => $user?->role,
                'route'          => $request->path(),
                'method'         => $request->method(),
                'required_roles' => $roles,
                'ip'             => $request->ip(),
            ]);
            return response()->json(['message' => 'Unauthorized. Required roles: ' . implode(', ', $roles)], 403);
        }

        return $next($request);
    }
}

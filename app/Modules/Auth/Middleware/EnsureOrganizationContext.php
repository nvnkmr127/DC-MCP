<?php

namespace App\Modules\Auth\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationContext
{
    /**
     * Ensure the authenticated user belongs to an active organization.
     * Sets the organization in the application container for the request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user = auth()->user();

        if (!$user->organization_id) {
            return response()->json(['message' => 'No organization context.'], 403);
        }

        $organization = $user->organization;

        if (!$organization || !$organization->is_active) {
            return response()->json(['message' => 'Organization is inactive or not found.'], 403);
        }

        // Bind organization into the container for the duration of this request
        app()->instance('current.organization', $organization);

        return $next($request);
    }
}

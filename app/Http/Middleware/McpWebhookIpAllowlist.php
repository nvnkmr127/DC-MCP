<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\IpUtils;

class McpWebhookIpAllowlist
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Global allowed IPs from config/env
        $globalAllowed = config('services.mcp.webhook_allowed_ips', '');
        
        if (!empty($globalAllowed)) {
            $allowedList = array_map('trim', explode(',', $globalAllowed));
            if (!IpUtils::checkIp($request->ip(), $allowedList)) {
                return response()->json(['message' => 'IP address not allowed globally.'], 403);
            }
        }

        // Connection-specific allowlist is checked inside the controller, 
        // since we need the connection record which might not be resolved in middleware easily.

        return $next($request);
    }
}

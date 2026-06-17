<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookSignature
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $signature = $request->header('X-Hub-Signature-256');

        if (! $signature) {
            return response()->json(['message' => 'Missing signature.'], 401);
        }

        $secret = env('MCP_WEBHOOK_SECRET', '');
        
        if (empty($secret)) {
            // If no secret is configured, fail closed to prevent unsecured webhooks
            return response()->json(['message' => 'Webhook secret not configured.'], 500);
        }

        $payload = $request->getContent();
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $secret);

        if (! hash_equals($expectedSignature, $signature)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        return $next($request);
    }
}

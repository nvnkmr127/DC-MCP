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

        $secretConfig = env('MCP_WEBHOOK_SECRETS', env('MCP_WEBHOOK_SECRET', ''));
        $secrets = array_filter(array_map('trim', explode(',', $secretConfig)));
        
        if (empty($secrets)) {
            // If no secret is configured, fail closed to prevent unsecured webhooks
            return response()->json(['message' => 'Webhook secret not configured.'], 500);
        }

        // Replay Attack Prevention: Validate Timestamp if provided
        $timestamp = $request->header('X-Webhook-Timestamp');
        if ($timestamp) {
            // 5 minutes tolerance window
            if (abs(time() - (int) $timestamp) > 300) {
                return response()->json(['message' => 'Webhook timestamp out of acceptable window. Replay attack prevented.'], 401);
            }
        }

        // Replay Attack Prevention: Cache the signature to prevent reuse within the window
        $cacheKey = 'webhook_signature_replay_' . hash('sha256', $signature);
        if (\Illuminate\Support\Facades\Cache::has($cacheKey)) {
            return response()->json(['message' => 'Replay attack detected. Signature already processed.'], 401);
        }

        $payload = $request->getContent();
        // If a timestamp is provided, include it in the signed payload to prevent timestamp forgery
        // Assuming the sender signs "$timestamp.$payload"
        $signedPayload = $timestamp ? "{$timestamp}.{$payload}" : $payload;

        $matched = false;
        foreach ($secrets as $secret) {
            $expectedSignature = 'sha256=' . hash_hmac('sha256', $signedPayload, $secret);
            if (hash_equals($expectedSignature, $signature)) {
                $matched = true;
                break;
            }
        }

        if (!$matched) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        // Store the signature in cache for 5 minutes (300 seconds)
        \Illuminate\Support\Facades\Cache::put($cacheKey, true, 300);

        return $next($request);
    }
}

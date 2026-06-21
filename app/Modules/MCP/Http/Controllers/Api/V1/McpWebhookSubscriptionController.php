<?php

namespace App\Modules\MCP\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class McpWebhookSubscriptionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orgId = $request->user()?->organization_id ?? 'default-org';

        $subscriptions = DB::table('mcp_webhook_subscriptions')
            ->where('organization_id', $orgId)
            ->get()
            ->map(function ($sub) {
                $sub->events = json_decode($sub->events, true);
                return $sub;
            });

        return response()->json(['data' => $subscriptions]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'url' => 'required|url',
            'events' => 'required|array',
        ]);

        $orgId = $request->user()?->organization_id ?? 'default-org';
        $id = (string) Str::uuid();
        $secret = 'whsec_' . Str::random(32);

        DB::table('mcp_webhook_subscriptions')->insert([
            'id' => $id,
            'organization_id' => $orgId,
            'url' => $request->input('url'),
            'secret' => $secret,
            'events' => json_encode($request->input('events')),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $subscription = DB::table('mcp_webhook_subscriptions')->where('id', $id)->first();
        $subscription->events = json_decode($subscription->events, true);

        return response()->json([
            'message' => 'Subscription created.',
            'data' => $subscription
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $orgId = $request->user()?->organization_id ?? 'default-org';
        
        $subscription = DB::table('mcp_webhook_subscriptions')
            ->where('id', $id)
            ->where('organization_id', $orgId)
            ->first();

        if (!$subscription) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $subscription->events = json_decode($subscription->events, true);
        return response()->json(['data' => $subscription]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'url' => 'sometimes|url',
            'events' => 'sometimes|array',
            'is_active' => 'sometimes|boolean',
        ]);

        $orgId = $request->user()?->organization_id ?? 'default-org';

        $updated = DB::table('mcp_webhook_subscriptions')
            ->where('id', $id)
            ->where('organization_id', $orgId)
            ->update(array_merge(
                $request->only(['url', 'is_active']),
                $request->has('events') ? ['events' => json_encode($request->input('events'))] : [],
                ['updated_at' => now()]
            ));

        if (!$updated) {
            return response()->json(['message' => 'Not found or no changes made.'], 404);
        }

        $subscription = DB::table('mcp_webhook_subscriptions')->where('id', $id)->first();
        $subscription->events = json_decode($subscription->events, true);

        return response()->json([
            'message' => 'Subscription updated.',
            'data' => $subscription
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $orgId = $request->user()?->organization_id ?? 'default-org';

        $deleted = DB::table('mcp_webhook_subscriptions')
            ->where('id', $id)
            ->where('organization_id', $orgId)
            ->delete();

        if (!$deleted) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return response()->json(['message' => 'Subscription deleted.']);
    }

    public function logs(Request $request, string $subscriptionId): JsonResponse
    {
        $orgId = $request->user()?->organization_id ?? 'default-org';

        // Verify the subscription exists and belongs to the org
        $subscription = DB::table('mcp_webhook_subscriptions')
            ->where('id', $subscriptionId)
            ->where('organization_id', $orgId)
            ->first();

        if (!$subscription) {
            return response()->json(['message' => 'Subscription not found.'], 404);
        }

        $limit = $request->input('limit', 50);
        $offset = $request->input('offset', 0);

        $logs = DB::table('mcp_webhook_delivery_logs')
            ->where('subscription_id', $subscriptionId)
            ->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(function ($log) {
                $log->request_payload = json_decode($log->request_payload, true);
                // Attempt to decode response if JSON
                $decodedResp = json_decode($log->response_body, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $log->response_body = $decodedResp;
                }
                return $log;
            });

        return response()->json(['data' => $logs]);
    }
}

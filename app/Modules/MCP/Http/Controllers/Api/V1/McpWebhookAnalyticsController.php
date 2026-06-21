<?php

namespace App\Modules\MCP\Http\Controllers\Api\V1;

use App\Modules\MCP\Models\McpConnection;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class McpWebhookAnalyticsController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $orgId = $request->user()?->organization_id ?? 'default-org';

        // Retrieve connection IDs belonging to the organization
        $connectionIds = McpConnection::where('organization_id', $orgId)->pluck('id')->toArray();

        if (empty($connectionIds)) {
            return response()->json([
                'failure_rate' => 0,
                'total_events' => 0,
                'recent_events' => []
            ]);
        }

        $totalEvents = DB::table('mcp_webhook_events')
            ->whereIn('mcp_connection_id', $connectionIds)
            ->count();

        $failedEvents = DB::table('mcp_webhook_events')
            ->whereIn('mcp_connection_id', $connectionIds)
            ->where('status', 'failed')
            ->count();

        $failureRate = $totalEvents > 0 ? round(($failedEvents / $totalEvents) * 100, 2) : 0;

        $recentEvents = DB::table('mcp_webhook_events')
            ->whereIn('mcp_connection_id', $connectionIds)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($event) {
                $event->payload = json_decode($event->payload, true);
                return $event;
            });

        return response()->json([
            'failure_rate' => $failureRate,
            'total_events' => $totalEvents,
            'failed_events' => $failedEvents,
            'recent_events' => $recentEvents,
        ]);
    }

    public function analytics(Request $request): JsonResponse
    {
        $orgId = $request->user()?->organization_id ?? 'default-org';
        $connectionIds = McpConnection::where('organization_id', $orgId)->pluck('id')->toArray();

        if (empty($connectionIds)) {
            return response()->json(['data' => []]);
        }

        $days = (int) $request->input('days', 30);
        $since = now()->subDays($days);

        // SQLite uses strftime, MySQL uses DATE(), Postgres uses DATE(). 
        // We will assume SQLite for local dev or use a generic approach
        // A universally compatible approach for simple charts without relying on specific DB functions
        $analytics = DB::table('mcp_webhook_events')
            ->selectRaw('DATE(created_at) as date, COUNT(*) as volume, SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as errors, AVG(duration_ms) as avg_latency_ms')
            ->whereIn('mcp_connection_id', $connectionIds)
            ->where('created_at', '>=', $since)
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date', 'asc')
            ->get();

        return response()->json(['data' => $analytics]);
    }
}

<?php

namespace App\Modules\MCP\Listeners;

use App\Modules\MCP\Events\McpSyncCompleted;
use App\Modules\MCP\Events\McpSyncFailed;
use App\Modules\MCP\Events\McpConnectionRequiresUpdate;
use App\Modules\MCP\Jobs\DispatchOutboundWebhook;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

class OutboundWebhookEventSubscriber
{
    public function handleSyncCompleted(McpSyncCompleted $event): void
    {
        $this->dispatchWebhooks('mcp.sync.completed', $event->connection->organization_id, [
            'connection_id' => $event->connection->id,
            'provider' => $event->connection->provider,
            'sync_result' => [
                'status' => $event->result->status,
                'processed' => $event->result->processed,
                'has_anomaly' => $event->result->hasAnomaly,
            ]
        ]);
    }

    public function handleSyncFailed(McpSyncFailed $event): void
    {
        $this->dispatchWebhooks('mcp.sync.failed', $event->connection->organization_id, [
            'connection_id' => $event->connection->id,
            'provider' => $event->connection->provider,
            'error' => $event->error
        ]);
    }

    public function handleConnectionRequiresUpdate(McpConnectionRequiresUpdate $event): void
    {
        $this->dispatchWebhooks('mcp.connection.requires_update', $event->connection->organization_id, [
            'connection_id' => $event->connection->id,
            'provider' => $event->connection->provider,
            'reason' => $event->reason
        ]);
    }

    private function dispatchWebhooks(string $eventType, string $orgId, array $payload): void
    {
        $subscriptions = DB::table('mcp_webhook_subscriptions')
            ->where('organization_id', $orgId)
            ->where('is_active', true)
            ->get()
            ->filter(function ($sub) use ($eventType) {
                $events = json_decode($sub->events, true) ?? [];
                return in_array('*', $events) || in_array($eventType, $events);
            });

        foreach ($subscriptions as $subscription) {
            DispatchOutboundWebhook::dispatch($subscription, $eventType, $payload);
        }
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            McpSyncCompleted::class => 'handleSyncCompleted',
            McpSyncFailed::class => 'handleSyncFailed',
            McpConnectionRequiresUpdate::class => 'handleConnectionRequiresUpdate',
        ];
    }
}

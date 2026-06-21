<?php

namespace App\Modules\MCP\Listeners;
use App\Modules\MCP\Events\McpSyncCompleted;
use App\Modules\Auth\Models\User;
use App\Modules\Notifications\Services\NotificationService;

class HandleMcpSyncCompleted
{
    public function handle(McpSyncCompleted $event): void
    {
        $event->connection->markSynced();

        \App\Modules\MCP\Models\McpSyncLog::create([
            'mcp_connection_id' => $event->connection->id,
            'status'            => 'success',
            'duration_ms'       => $event->result->durationMs,
            'records_processed' => $event->result->processedCount ?? 0,
            'bytes_transferred' => $event->result->bytesTransferred ?? 0,
            'metadata'          => [
                'field_mappings' => $event->connection->settings['field_mappings'] ?? []
            ],
        ]);

        $user = User::where('organization_id', $event->connection->organization_id)->first();
        if ($user) {
            $providerLabel = ucwords(str_replace('_', ' ', $event->connection->provider));
            $count = $event->result->processedCount ?? 0;
            
            app(NotificationService::class)->sendNotification(
                $user,
                'system_alert',
                'in_app',
                "Sync Successful",
                "{$providerLabel} sync completed: {$count} events imported."
            );
        }
    }
}

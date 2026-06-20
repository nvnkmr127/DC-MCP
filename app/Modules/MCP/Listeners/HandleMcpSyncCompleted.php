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

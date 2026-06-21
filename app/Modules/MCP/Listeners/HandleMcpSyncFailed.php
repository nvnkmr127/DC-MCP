<?php

namespace App\Modules\MCP\Listeners;

use App\Modules\MCP\Events\McpSyncFailed;
use App\Models\User;

class HandleMcpSyncFailed
{
    public function handle(McpSyncFailed $event): void
    {
        $event->connection->markError($event->errorMessage);

        \App\Modules\MCP\Models\McpSyncLog::create([
            'mcp_connection_id' => $event->connection->id,
            'status'            => 'failed',
            'error_message'     => $event->errorMessage,
            'metadata'          => [
                'field_mappings' => $event->connection->settings['field_mappings'] ?? []
            ],
        ]);

        $user = User::where('organization_id', $event->connection->organization_id)->first();
    }
}

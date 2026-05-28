<?php

namespace App\Modules\MCP\Listeners;

use App\Modules\MCP\Events\McpSyncCompleted;

class HandleMcpSyncCompleted
{
    public function handle(McpSyncCompleted $event): void
    {
        $event->connection->markSynced();
    }
}

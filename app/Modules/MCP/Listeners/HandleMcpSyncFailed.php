<?php

namespace App\Modules\MCP\Listeners;

use App\Modules\MCP\Events\McpSyncFailed;

class HandleMcpSyncFailed
{
    public function handle(McpSyncFailed $event): void
    {
        $event->connection->markError($event->errorMessage);
    }
}

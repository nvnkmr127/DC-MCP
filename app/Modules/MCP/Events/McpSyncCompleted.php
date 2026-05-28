<?php

namespace App\Modules\MCP\Events;

use App\Modules\MCP\Models\McpConnection;
use App\Modules\MCP\DataObjects\SyncResult;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class McpSyncCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly McpConnection $connection,
        public readonly SyncResult $result
    ) {}
}

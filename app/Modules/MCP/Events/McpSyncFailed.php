<?php

namespace App\Modules\MCP\Events;

use App\Modules\MCP\Models\McpConnection;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class McpSyncFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly McpConnection $connection,
        public readonly string $errorMessage,
        public readonly string $provider
    ) {}
}

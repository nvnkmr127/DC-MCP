<?php

namespace App\Modules\MCP\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Modules\MCP\Models\McpConnection;

class McpConnectionRequiresUpdate
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public McpConnection $connection;
    public array $changes;

    /**
     * Create a new event instance.
     *
     * @param McpConnection $connection
     * @param array $changes Map of what changed e.g., ['scopes' => ['missing' => ['new_scope']], 'api_version' => ['old' => 'v1', 'new' => 'v2']]
     */
    public function __construct(McpConnection $connection, array $changes)
    {
        $this->connection = $connection;
        $this->changes = $changes;
    }
}

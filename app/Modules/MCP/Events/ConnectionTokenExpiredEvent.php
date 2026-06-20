<?php

namespace App\Modules\MCP\Events;

use App\Modules\MCP\Models\McpConnection;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConnectionTokenExpiredEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public McpConnection $connection;

    /**
     * Create a new event instance.
     */
    public function __construct(McpConnection $connection)
    {
        $this->connection = $connection;
    }
}

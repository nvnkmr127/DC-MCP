<?php

namespace App\Modules\MCP\Events;

use App\Modules\MCP\Models\McpConnection;
use App\Modules\MCP\DataObjects\WebhookResult;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class McpWebhookReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly McpConnection $connection,
        public readonly WebhookResult $result,
        public readonly array $payload = []
    ) {}
}

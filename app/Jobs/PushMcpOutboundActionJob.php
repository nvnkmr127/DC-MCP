<?php

namespace App\Jobs;

use App\Modules\MCP\Models\McpConnection;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use App\Shared\Traits\RateLimitsTenantJobs;
use Illuminate\Support\Facades\Log;

class PushMcpOutboundActionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, RateLimitsTenantJobs;

    public $queue = 'high';
    public $tries = 3;
    public $timeout = 60;

    public function __construct(
        public string $connectionId,
        public array $payload,
        public array $options = []
    ) {}

    public function handle(): void
    {
        $connection = McpConnection::find($this->connectionId);
        if (!$connection) {
            return;
        }

        $adapter = $this->resolveAdapter($connection->provider);
        $adapter->push($this->connectionId, $this->payload, $this->options);
    }

    private function resolveAdapter(string $provider): mixed
    {
        return match ($provider) {
            'gmail'           => app(\App\Modules\MCP\Adapters\GmailAdapter::class),
            'google_calendar' => app(\App\Modules\MCP\Adapters\GoogleCalendarAdapter::class),
            'notion'          => app(\App\Modules\MCP\Adapters\NotionAdapter::class),
            'zoho_cliq'       => app(\App\Modules\MCP\Adapters\ZohoCliqAdapter::class),
            'meta_ads'        => app(\App\Modules\MCP\Adapters\MetaAdsAdapter::class),
            'make'            => app(\App\Modules\MCP\Adapters\MakeAdapter::class),
            // Any custom provider falls back to the generic HTTP adapter
            default           => app(\App\Modules\MCP\Adapters\CustomMcpAdapter::class),
        };
    }
}

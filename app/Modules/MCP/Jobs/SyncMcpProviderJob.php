<?php

namespace App\Modules\MCP\Jobs;

use App\Modules\MCP\Models\McpConnection;
use App\Modules\MCP\Adapters\CustomMcpAdapter;
use App\Modules\MCP\Adapters\GmailAdapter;
use App\Modules\MCP\Adapters\GoogleCalendarAdapter;
use App\Modules\MCP\Adapters\NotionAdapter;
use App\Modules\MCP\Adapters\ZohoCliqAdapter;
use App\Modules\MCP\Adapters\MetaAdsAdapter;
use App\Modules\MCP\Adapters\MakeAdapter;
use App\Modules\MCP\Events\McpSyncCompleted;
use App\Modules\MCP\Events\McpSyncFailed;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncMcpProviderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        public readonly McpConnection $connection
    ) {}

    public function handle(): void
    {
        $adapter = $this->resolveAdapter($this->connection->provider);
        $result  = $adapter->sync($this->connection->id);

        if ($result->isSuccess) {
            event(new McpSyncCompleted($this->connection, $result));
        } else {
            event(new McpSyncFailed($this->connection, $result->errorMessage ?? 'Unknown error', $this->connection->provider));
        }
    }

    private function resolveAdapter(string $provider): mixed
    {
        return match ($provider) {
            'gmail'           => app(GmailAdapter::class),
            'google_calendar' => app(GoogleCalendarAdapter::class),
            'notion'          => app(NotionAdapter::class),
            'zoho_cliq'       => app(ZohoCliqAdapter::class),
            'meta_ads'        => app(MetaAdsAdapter::class),
            'make'            => app(MakeAdapter::class),
            // Any custom provider falls back to the generic HTTP adapter
            default           => app(CustomMcpAdapter::class),
        };
    }
}

<?php

namespace App\Modules\MCP\Jobs;

use App\Modules\MCP\Models\McpConnection;
use App\Modules\MCP\Events\McpSyncCompleted;
use App\Modules\MCP\Events\McpSyncFailed;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncMcpProviderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    /** Exponential backoff: 30s, 2m, 5m — respects rate-limit windows */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function __construct(
        public readonly McpConnection $connection
    ) {}

    public function handle(): void
    {
        Log::info('MCP sync started', [
            'connection_id'  => $this->connection->id,
            'provider'       => $this->connection->provider,
            'organization_id'=> $this->connection->organization_id,
        ]);

        $adapter = $this->resolveAdapter($this->connection->provider);
        $result  = $adapter->sync($this->connection->id);

        if ($result->isSuccess) {
            Log::info('MCP sync completed', [
                'connection_id' => $this->connection->id,
                'provider'      => $this->connection->provider,
            ]);
            event(new McpSyncCompleted($this->connection, $result));
        } else {
            Log::error('MCP sync failed', [
                'connection_id'  => $this->connection->id,
                'provider'       => $this->connection->provider,
                'organization_id'=> $this->connection->organization_id,
                'error'          => $result->errorMessage ?? 'Unknown error',
            ]);
            event(new McpSyncFailed($this->connection, $result->errorMessage ?? 'Unknown error', $this->connection->provider));
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('MCP sync job permanently failed', [
            'connection_id'  => $this->connection->id,
            'provider'       => $this->connection->provider,
            'organization_id'=> $this->connection->organization_id,
            'exception'      => $exception->getMessage(),
        ]);
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

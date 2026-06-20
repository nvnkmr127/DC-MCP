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
        public readonly McpConnection $mcpConnection
    ) {}

    public function handle(): void
    {
        Log::info('MCP sync started', [
            'connection_id'  => $this->mcpConnection->id,
            'provider'       => $this->mcpConnection->provider,
            'organization_id'=> $this->mcpConnection->organization_id,
        ]);

        $adapter = $this->resolveAdapter($this->mcpConnection->provider);
        try {
            $startTime = microtime(true);
            $result  = $adapter->sync($this->mcpConnection->id);
            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

            if ($result->isSuccess) {
                Log::info('MCP sync completed', [
                    'connection_id' => $this->mcpConnection->id,
                    'provider'      => $this->mcpConnection->provider,
                    'latency_ms'    => $latencyMs,
                ]);
                $this->mcpConnection->markSuccess($latencyMs);
                event(new McpSyncCompleted($this->mcpConnection, $result));
            } else {
                Log::error('MCP sync failed', [
                    'connection_id'  => $this->mcpConnection->id,
                    'provider'       => $this->mcpConnection->provider,
                    'organization_id'=> $this->mcpConnection->organization_id,
                    'error'          => $result->errorMessage ?? 'Unknown error',
                ]);
                $this->mcpConnection->markError($result->errorMessage ?? 'Unknown error');
                event(new McpSyncFailed($this->mcpConnection, $result->errorMessage ?? 'Unknown error', $this->mcpConnection->provider));
            }
        } catch (\Throwable $e) {
            $this->mcpConnection->handleException($e);
            throw $e; // Rethrow to let the queue manager know it failed and handle backoff
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->mcpConnection->handleException($exception);

        Log::error('MCP sync job permanently failed', [
            'connection_id'  => $this->mcpConnection->id,
            'provider'       => $this->mcpConnection->provider,
            'organization_id'=> $this->mcpConnection->organization_id,
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

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
use Illuminate\Queue\Middleware\WithoutOverlapping;

class SyncMcpProviderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, \App\Shared\Traits\RateLimitsTenantJobs;

    public int $tries = 3;
    public int $timeout = 120;

    /** Exponential backoff: 30s, 2m, 5m — respects rate-limit windows */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function middleware(): array
    {
        return [new WithoutOverlapping((string) $this->mcpConnection->id)];
    }

    public function __construct(
        public readonly McpConnection $mcpConnection,
        public readonly array $options = []
    ) {}

    public function handle(): void
    {
        $options = $this->options;
        if (!empty($options['full_resync']) || !empty($options['reconcile'])) {
            $options['sync_session_id'] = (string) \Illuminate\Support\Str::uuid();
        }

        Log::info('MCP sync started', [
            'connection_id'  => $this->mcpConnection->id,
            'provider'       => $this->mcpConnection->provider,
            'organization_id'=> $this->mcpConnection->organization_id,
            'options'        => $options,
        ]);

        $adapter = $this->resolveAdapter($this->mcpConnection->provider);
        try {
            $startTime = microtime(true);
            $result  = $adapter->sync($this->mcpConnection->id, $options);
            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

            if (!empty($options['dry_run'])) {
                Log::info('MCP sync completed (DRY RUN)', [
                    'connection_id' => $this->mcpConnection->id,
                    'provider'      => $this->mcpConnection->provider,
                    'processed'     => $result->processedCount,
                    'latency_ms'    => $latencyMs,
                ]);
                return;
            }

            if ($result->isSuccess) {
                Log::info('MCP sync completed', [
                    'connection_id' => $this->mcpConnection->id,
                    'provider'      => $this->mcpConnection->provider,
                    'latency_ms'    => $latencyMs,
                ]);

                // Anomaly Detection: Flag if actual processed count is off by > 10% from expected estimate
                $hasAnomaly = false;
                if ($result->expectedCount > 0) {
                    $deviation = abs($result->expectedCount - $result->processedCount) / $result->expectedCount;
                    if ($deviation > 0.1) {
                        $hasAnomaly = true;
                        Log::warning('MCP sync anomaly detected', [
                            'connection_id' => $this->mcpConnection->id,
                            'expected'      => $result->expectedCount,
                            'actual'        => $result->processedCount,
                        ]);
                    }
                }
                
                $settings = $this->mcpConnection->settings ?? [];
                $settings['has_anomaly'] = $hasAnomaly;
                $this->mcpConnection->update(['settings' => $settings]);

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
        } finally {
            $this->mcpConnection->clearSyncProgress();
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

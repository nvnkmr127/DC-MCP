<?php

namespace App\Modules\MCP\Jobs;

use App\Modules\MCP\Models\McpConnection;
use App\Modules\MCP\Events\McpSyncCompleted;
use App\Modules\MCP\Events\McpSyncFailed;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class SyncMcpProviderJob implements ShouldQueue, ShouldBeUnique
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

    /**
     * The unique ID of the job.
     */
    public function uniqueId(): string
    {
        return (string) $this->mcpConnection->id;
    }

    /**
     * The number of seconds after which the job's unique lock will be released.
     */
    public function uniqueFor(): int
    {
        return 3600; // 1 hour max lock
    }

    public function __construct(
        public readonly McpConnection $mcpConnection,
        public readonly array $options = []
    ) {
        $source = $this->options['source'] ?? 'user';
        $this->onQueue($source === 'scheduled' ? 'default' : 'high');
    }

    public function handle(): void
    {
        $lock = \Illuminate\Support\Facades\Cache::lock('mcp_sync_connection_' . $this->mcpConnection->id, 300);

        if (! $lock->get()) {
            Log::warning('MCP sync skipped or delayed - already running for this connection', [
                'connection_id' => $this->mcpConnection->id,
            ]);
            $this->release(30);
            return;
        }

        try {
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
                $providerName = ucfirst(str_replace('_', ' ', $this->mcpConnection->provider));
                $failedText = $result->failedCount > 0 ? ", {$result->failedCount} failed" : "";
                $settings['last_sync_summary'] = sprintf("Synced %d items from %s%s", $result->processedCount, $providerName, $failedText);
                $this->mcpConnection->update(['settings' => $settings]);

                $this->mcpConnection->markSuccess($latencyMs);
                event(new McpSyncCompleted($this->mcpConnection, $result));

                // If the adapter yielded execution before finishing, requeue immediately
                if (!empty($result->metadata['has_more'])) {
                    Log::info('MCP sync yielded execution; requeuing continuation job', [
                        'connection_id' => $this->mcpConnection->id,
                        'provider'      => $this->mcpConnection->provider,
                    ]);
                    static::dispatch($this->mcpConnection, $this->options)->delay(now()->addSeconds(5));
                }
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
            if ($e instanceof \App\Modules\MCP\Exceptions\SyncCancelledException) {
                Log::info('MCP sync cancelled by user', [
                    'connection_id' => $this->mcpConnection->id,
                    'provider'      => $this->mcpConnection->provider,
                ]);
                $this->mcpConnection->resetSyncCancellation();
                $this->mcpConnection->markError('Sync was cancelled by the user.');
                return; // Do not rethrow, so it doesn't trigger a retry
            }
            
            $this->mcpConnection->handleException($e);
            throw $e; // Rethrow to let the queue manager know it failed and handle backoff
        } finally {
            $lock->release();
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

        \App\Modules\MCP\Models\McpDeadLetterQueue::create([
            'mcp_connection_id' => $this->mcpConnection->id,
            'provider'          => $this->mcpConnection->provider,
            'error_message'     => $exception->getMessage(),
            'exception_trace'   => $exception->getTraceAsString(),
            'payload'           => $this->options,
        ]);
    }

    private function resolveAdapter(string $provider): mixed
    {
        $providerModel = \App\Modules\MCP\Models\McpProvider::where('slug', $provider)->first();

        if ($providerModel && $providerModel->adapter_class) {
            return app($providerModel->adapter_class);
        }

        return app(\App\Modules\MCP\Adapters\CustomMcpAdapter::class);
    }
}

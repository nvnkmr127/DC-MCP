<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\MCP\Models\McpConnection;
use Illuminate\Support\Facades\Crypt;

class UpdateMcpRateLimitsCommand extends Command
{
    protected $signature = 'mcp:update-rate-limits';
    protected $description = 'Fetch and cache rate limits for all active MCP connections';

    public function handle()
    {
        $this->info('Starting Rate Limit Checks...');

        $connections = McpConnection::where('status', 'active')->get();
        $checked = 0;

        foreach ($connections as $connection) {
            try {
                $adapter = $this->resolveAdapter($connection->provider);
                if (!$adapter) continue;

                $credentials = [];
                if ($connection->credentials) {
                    $credentials = json_decode(Crypt::decryptString($connection->credentials), true) ?? [];
                }

                $rateLimits = method_exists($adapter, 'getRateLimitStatus') ? $adapter->getRateLimitStatus($credentials) : null;

                if ($rateLimits) {
                    $settings = $connection->settings ?? [];
                    $settings['rate_limits'] = [
                        'limit' => $rateLimits['limit'] ?? null,
                        'remaining' => $rateLimits['remaining'] ?? null,
                        'reset' => $rateLimits['reset'] ?? null,
                        'updated_at' => now()->toIso8601String(),
                    ];
                    $connection->update(['settings' => $settings]);
                    $checked++;
                }

            } catch (\Exception $e) {
                $this->error("Failed to check rate limit for connection ID: {$connection->id} - " . $e->getMessage());
            }
        }

        $this->info("Completed. Updated rate limits for {$checked} connection(s).");
        return 0;
    }

    private function resolveAdapter(string $provider)
    {
        $providerModel = \App\Modules\MCP\Models\McpProvider::where('slug', $provider)->first();

        if ($providerModel && $providerModel->adapter_class) {
            return app($providerModel->adapter_class);
        }

        return app(\App\Modules\MCP\Adapters\CustomMcpAdapter::class);
    }
}

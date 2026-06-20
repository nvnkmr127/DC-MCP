<?php

namespace App\Modules\MCP\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\MCP\Models\McpConnection;
use App\Modules\MCP\Events\McpConnectionRequiresUpdate;
use App\Modules\MCP\Enums\ConnectionStatus;

class CheckProviderUpdatesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mcp:check-provider-updates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks all active MCP connections to see if their provider adapter has updated its API version or required scopes.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting MCP Provider Updates Check...');

        // Only check active connections
        $connections = McpConnection::where('status', ConnectionStatus::ACTIVE->value)->get();
        $updatesFound = 0;

        foreach ($connections as $connection) {
            try {
                $adapter = $this->resolveAdapter($connection->provider);
                if (!$adapter) {
                    continue; // Skip custom or unknown providers that don't map to a built-in adapter directly
                }

                $changes = [];

                // 1. Check API Version
                $currentApiVersion = $adapter->getApiVersion();
                $connectionApiVersion = $connection->settings['api_version'] ?? null;
                
                if ($connectionApiVersion && $connectionApiVersion !== $currentApiVersion) {
                    $changes['api_version'] = [
                        'old' => $connectionApiVersion,
                        'new' => $currentApiVersion,
                    ];
                }

                // 2. Check Scopes
                $requiredScopes = method_exists($adapter, 'getAvailableScopes') ? $adapter->getAvailableScopes() : [];
                $connectionScopes = $connection->scopes ?? [];
                
                if (!empty($requiredScopes)) {
                    $missingScopes = array_diff($requiredScopes, $connectionScopes);
                    if (!empty($missingScopes)) {
                        $changes['scopes'] = [
                            'missing' => array_values($missingScopes),
                        ];
                    }
                }

                if (!empty($changes)) {
                    // Update the connection status
                    $connection->update([
                        'status' => ConnectionStatus::PENDING_REAUTH->value
                    ]);

                    // Dispatch event for notifications
                    event(new McpConnectionRequiresUpdate($connection, $changes));
                    
                    $this->warn("Update required for connection ID: {$connection->id} ({$connection->provider})");
                    $updatesFound++;
                }

            } catch (\Exception $e) {
                $this->error("Failed to check connection ID: {$connection->id} - " . $e->getMessage());
            }
        }

        $this->info("Completed. Found updates for {$updatesFound} connection(s).");
        return 0;
    }

    private function resolveAdapter(string $provider)
    {
        $builtin = [
            'gmail'           => \App\Modules\MCP\Adapters\GmailAdapter::class,
            'google_calendar' => \App\Modules\MCP\Adapters\GoogleCalendarAdapter::class,
            'notion'          => \App\Modules\MCP\Adapters\NotionAdapter::class,
            'zoho_cliq'       => \App\Modules\MCP\Adapters\ZohoCliqAdapter::class,
            'meta_ads'        => \App\Modules\MCP\Adapters\MetaAdsAdapter::class,
            'make'            => \App\Modules\MCP\Adapters\MakeAdapter::class,
        ];

        if (!isset($builtin[$provider])) {
            return null;
        }

        return app($builtin[$provider]);
    }
}

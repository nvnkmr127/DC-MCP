<?php

namespace App\Modules\MCP\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\MCP\Models\McpConnection;
use App\Modules\MCP\Jobs\SyncMcpProviderJob;
use App\Modules\MCP\Enums\ConnectionStatus;

class RunScheduledMcpSyncs extends Command
{
    protected $signature = 'mcp:sync-scheduled';
    protected $description = 'Dispatch sync jobs for eligible MCP connections based on their configured interval';

    public function handle(): int
    {
        $this->info('Starting scheduled MCP sync dispatcher...');

        $connections = McpConnection::whereIn('status', [
            ConnectionStatus::ACTIVE->value,
            ConnectionStatus::DEGRADED->value,
            ConnectionStatus::RATE_LIMITED->value
        ])->where('is_active', true)->get();

        $dispatchedCount = 0;

        foreach ($connections as $connection) {
            $lastSynced = $connection->last_synced_at;
            $intervalMinutes = $connection->getSyncIntervalMinutes();

            if (!$lastSynced || now()->diffInMinutes($lastSynced) >= $intervalMinutes) {
                SyncMcpProviderJob::dispatch($connection, ['source' => 'scheduled']);
                $dispatchedCount++;
                $this->line("Dispatched sync for connection ID: {$connection->id}");
            }
        }

        $this->info("Dispatched {$dispatchedCount} sync jobs.");

        return Command::SUCCESS;
    }
}

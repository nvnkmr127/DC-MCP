<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckSyncFailureThreshold extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mcp:check-failure-threshold';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks the MCP sync failure rate and logs a critical alert if it exceeds a threshold.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $thresholdPercent = 10;
        $minSyncs = 10; // Need at least 10 syncs in the period to calculate a meaningful rate
        
        $total = DB::table('mcp_sync_logs')
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($total < $minSyncs) {
            $this->info("Not enough syncs in the last hour to evaluate failure threshold (Found {$total}, need {$minSyncs}).");
            return;
        }

        $failed = DB::table('mcp_sync_logs')
            ->where('created_at', '>=', now()->subHour())
            ->where('status', 'failed')
            ->count();

        $rate = ($failed / $total) * 100;

        if ($rate > $thresholdPercent) {
            $message = sprintf(
                "High sync failure rate detected: %.2f%% (%d/%d syncs failed in the last hour).", 
                $rate, 
                $failed, 
                $total
            );
            
            Log::critical($message);
            $this->error($message);
            
            // Note: Since Sentry is configured, Log::critical automatically pushes an alert to Sentry.
        } else {
            $this->info(sprintf("Failure rate is within normal limits: %.2f%%.", $rate));
        }
    }
}

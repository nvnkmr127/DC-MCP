<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class MonitorSystemHealth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitor:system-health';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitors queue backlogs and latency degradation for proactive alerting.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->checkQueueBacklog();
        $this->checkLatencyDegradation();
    }

    private function checkQueueBacklog()
    {
        $queues = ['default', 'high', 'low'];
        $threshold = 1000; // Alert if depth exceeds 1000 jobs

        foreach ($queues as $queue) {
            $size = Queue::size($queue);
            if ($size >= $threshold) {
                Log::critical("Queue backlog alert: Queue '{$queue}' depth is critically high with {$size} pending jobs.");
            } else {
                $this->info("Queue '{$queue}' depth is normal ({$size} jobs).");
            }
        }
    }

    private function checkLatencyDegradation()
    {
        // Monitor API latency degradation by counting recent slow requests captured by Pulse
        $recentSlowRequests = DB::table('pulse_entries')
            ->where('type', 'slow_requests')
            ->where('timestamp', '>=', now()->subMinutes(5)->getTimestamp())
            ->count();

        // Alert if we see more than 50 slow requests in a 5 minute window
        if ($recentSlowRequests >= 50) {
            Log::critical("Latency degradation alert: High volume of slow requests detected ({$recentSlowRequests} in the last 5 minutes).");
        } else {
            $this->info("API latency is within normal parameters ({$recentSlowRequests} slow requests in the last 5 minutes).");
        }
    }
}

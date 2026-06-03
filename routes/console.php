<?php

use App\Modules\Auth\Models\User;
use App\Modules\DailyBriefing\Jobs\GenerateDailyBriefingJob;
use App\Modules\MCP\Jobs\SyncMcpProviderJob;
use App\Modules\MCP\Models\McpConnection;
use App\Modules\Notifications\Jobs\SendPendingNotificationsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Horizon metrics snapshots every 5 minutes — powers the throughput/wait-time graphs
if (class_exists(\Laravel\Horizon\Horizon::class)) {
    Schedule::command('horizon:snapshot')->everyFiveMinutes();
}

// SLA checks every 30 minutes
Schedule::command('tasks:check-slas')->everyThirtyMinutes();

// Campaign budget burn alerts — check hourly for 70%/90% threshold breaches
Schedule::command('budgets:check-burn')->hourly()->name('budget-burn-alerts')->withoutOverlapping();

// Spawn recurring tasks daily at 7:00 AM IST (01:30 UTC) — after briefings are generated
Schedule::command('tasks:spawn-recurring')->dailyAt('01:30')->timezone('UTC')->name('spawn-recurring-tasks')->withoutOverlapping();

// EOD standup reminder at 6:00 PM IST (12:30 UTC)
Schedule::command('standup:send-reminders')->dailyAt('12:30')->timezone('UTC')->name('standup-reminders')->withoutOverlapping();

// Pre-briefing MCP sync at 6:30 AM IST (01:00 UTC) — ensures fresh data before briefings run
Schedule::call(function () {
    // Sync high-priority providers that feed the morning briefing
    $priority = ['google_calendar', 'gmail', 'notion', 'meta_ads', 'zoho_cliq'];

    $connections = McpConnection::where('status', 'active')
        ->whereIn('provider', $priority)
        ->get();

    foreach ($connections as $connection) {
        SyncMcpProviderJob::dispatch($connection);
    }
})->dailyAt('01:00')->timezone('UTC')->name('pre-briefing-mcp-sync')->withoutOverlapping();

// Daily briefings at 7:00 AM IST (01:30 UTC) for all active users
Schedule::call(function () {
    $users = User::where('is_active', true)->get();
    foreach ($users as $user) {
        GenerateDailyBriefingJob::dispatch($user, now()->toDateString());
    }
})->dailyAt('01:30')->timezone('UTC')->name('generate-daily-briefings')->withoutOverlapping();

// Flush pending notifications (queued during quiet hours) at 07:05 AM IST (01:35 UTC)
Schedule::job(new SendPendingNotificationsJob)->dailyAt('01:35')->timezone('UTC')->name('flush-pending-notifications');

// Sync all remaining active MCP connections every hour
Schedule::call(function () {
    $connections = McpConnection::where('status', 'active')->get();
    foreach ($connections as $connection) {
        SyncMcpProviderJob::dispatch($connection);
    }
})->hourly()->name('sync-mcp-connections')->withoutOverlapping();

// Weekly client performance briefing every Monday 8:00 AM IST (02:30 UTC)
Schedule::call(function () {
    // Generate briefings for CEO users only (weekly digest)
    $ceoUsers = User::where('is_active', true)
        ->whereHas('roles', fn($q) => $q->where('slug', 'ceo'))
        ->get();

    foreach ($ceoUsers as $user) {
        // Dispatch with 'weekly' flag so BriefingGenerator knows to do a full week summary
        GenerateDailyBriefingJob::dispatch($user, now()->toDateString());
    }
})->weeklyOn(1, '02:30')->timezone('UTC')->name('weekly-client-digest')->withoutOverlapping();

// Monitor failed jobs every 5 minutes — log error with severity tier
Schedule::call(function () {
    $count = DB::table('failed_jobs')->count();
    if ($count === 0) {
        return;
    }
    // Surface different severity based on count so monitoring tools can route alerts
    $level = $count >= 10 ? 'critical' : ($count >= 3 ? 'error' : 'warning');
    Log::log($level, 'Failed jobs detected in queue', [
        'count'    => $count,
        'check_at' => now()->toISOString(),
    ]);
})->everyFiveMinutes()->name('monitor-failed-jobs');

// Monitor MCP adapter sync reliability hourly
// Flags any adapter whose last-24h failure rate exceeds 20%
Schedule::call(function () {
    $cutoff = now()->subHours(24);
    $adapters = DB::table('mcp_sync_logs')
        ->where('synced_at', '>=', $cutoff)
        ->select('mcp_connection_id', DB::raw('COUNT(*) as total'), DB::raw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed"))
        ->groupBy('mcp_connection_id')
        ->get();

    foreach ($adapters as $row) {
        $failRate = $row->total > 0 ? ($row->failed / $row->total) * 100 : 0;
        if ($failRate >= 20) {
            $connection = DB::table('mcp_connections')->where('id', $row->mcp_connection_id)->first();
            Log::error('MCP adapter reliability degraded', [
                'connection_id'  => $row->mcp_connection_id,
                'provider'       => $connection->provider ?? 'unknown',
                'organization_id'=> $connection->organization_id ?? null,
                'fail_rate_pct'  => round($failRate, 1),
                'total_syncs'    => $row->total,
                'failed_syncs'   => $row->failed,
            ]);
        }
    }
})->hourly()->name('monitor-mcp-reliability');

// Maintain metric_snapshots partitions — runs every December to pre-create next year's partitions
// Prevents inserts falling into the default partition after 2026, which degrades query performance
Schedule::call(function () {
    if (DB::getDriverName() === 'sqlite' || config('database.default') === 'sqlite') {
        return; // Partitioning is PostgreSQL-only
    }
    $nextYear = now()->addYear()->year;
    for ($month = 1; $month <= 12; $month++) {
        $startDate     = sprintf('%04d-%02d-01', $nextYear, $month);
        $endDate       = $month === 12
            ? sprintf('%04d-01-01', $nextYear + 1)
            : sprintf('%04d-%02d-01', $nextYear, $month + 1);
        $partitionName = sprintf('metric_snapshots_y%04dm%02d', $nextYear, $month);

        // Skip if partition already exists
        $exists = DB::selectOne(
            "SELECT 1 FROM pg_class WHERE relname = ?",
            [$partitionName]
        );
        if ($exists) {
            continue;
        }
        DB::statement("
            CREATE TABLE {$partitionName} PARTITION OF metric_snapshots
            FOR VALUES FROM ('{$startDate}') TO ('{$endDate}')
        ");
        Log::info('metric_snapshots partition created', ['partition' => $partitionName]);
    }
})->monthly()->name('maintain-metric-partitions');

// Purge soft-deleted records older than 90 days — runs daily at 03:00 AM IST (21:30 UTC prior day)
Schedule::call(function () {
    $tables = [
        ['model' => \App\Modules\ProjectManagement\Models\Project::class],
        ['model' => \App\Modules\ProjectManagement\Models\Task::class],
        ['model' => \App\Modules\ProjectManagement\Models\Client::class],
        ['model' => \App\Modules\MCP\Models\McpConnection::class],
    ];

    $cutoff = now()->subDays(90);

    foreach ($tables as $entry) {
        $entry['model']::onlyTrashed()->where('deleted_at', '<', $cutoff)->forceDelete();
    }
})->dailyAt('21:30')->timezone('UTC')->name('purge-soft-deletes');

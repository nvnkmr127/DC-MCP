<?php

use App\Modules\Auth\Models\User;
use App\Modules\DailyBriefing\Jobs\GenerateDailyBriefingJob;
use App\Modules\MCP\Jobs\SyncMcpProviderJob;
use App\Modules\MCP\Models\McpConnection;
use App\Modules\Notifications\Jobs\SendPendingNotificationsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// SLA checks every 30 minutes
Schedule::command('tasks:check-slas')->everyThirtyMinutes();

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

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

// Daily briefings at 7:00 AM IST (01:30 UTC) for all active users
Schedule::call(function () {
    $users = User::where('is_active', true)->get();
    foreach ($users as $user) {
        GenerateDailyBriefingJob::dispatch($user, now()->toDateString());
    }
})->dailyAt('01:30')->timezone('UTC')->name('generate-daily-briefings')->withoutOverlapping();

// Flush pending notifications (queued during quiet hours) at 07:05 AM IST (01:35 UTC)
Schedule::job(new SendPendingNotificationsJob)->dailyAt('01:35')->timezone('UTC')->name('flush-pending-notifications');

// Sync all active MCP connections every hour
Schedule::call(function () {
    $connections = McpConnection::where('status', 'active')->get();
    foreach ($connections as $connection) {
        SyncMcpProviderJob::dispatch($connection);
    }
})->hourly()->name('sync-mcp-connections')->withoutOverlapping();

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

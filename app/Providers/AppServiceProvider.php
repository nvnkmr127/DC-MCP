<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Queue;
use Illuminate\Queue\Events\JobFailed;
use App\Modules\Notifications\Services\NotificationService;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        \Illuminate\Support\Facades\Gate::define('viewPulse', function ($user) {
            // Only allow users with the 'super_admin' role to view the Pulse dashboard
            return $user->hasRole('super_admin');
        });

        $this->configureRateLimiting();
        $this->configureSlowQueryLogging();
        $this->configurePostgresRLS();

        Queue::failing(function (JobFailed $event) {
            try {
                $payload = $event->job->payload();
                if (isset($payload['data']['command'])) {
                    $jobInstance = unserialize($payload['data']['command']);
                    
                    $user = null;
                    if (property_exists($jobInstance, 'user') && $jobInstance->user instanceof \App\Modules\Auth\Models\User) {
                        $user = $jobInstance->user;
                    }
                    
                    if ($user) {
                        $jobName = class_basename($jobInstance);
                        $jobNameStr = trim(preg_replace('/(?<!^)[A-Z]/', ' $0', str_replace('Job', '', $jobName)));
                        
                        app(NotificationService::class)->sendNotification(
                            $user,
                            'system_alert',
                            'in_app',
                            "Background Task Failed",
                            "The process '{$jobNameStr}' could not be completed. Please try again or contact support."
                        );
                    }
                }
            } catch (\Exception $e) {
                Log::error('Failed to parse Job for failure notification: ' . $e->getMessage());
            }
        });
    }

    private function configurePostgresRLS(): void
    {
        // On any new database connection, we default to bypassing RLS so that system 
        // tasks, authentication, and migrations work out of the box.
        Event::listen(ConnectionEstablished::class, function (ConnectionEstablished $event) {
            if ($event->connection->getDriverName() === 'pgsql') {
                $event->connection->getPdo()->exec("SET app.bypass_rls = 'on'");
            }
        });

        // Ensure that any job pulled from the queue resets the connection to bypass RLS.
        Event::listen(JobProcessing::class, function (JobProcessing $event) {
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::statement("SET app.bypass_rls = 'on'");
            }
        });
    }

    private function configureSlowQueryLogging(): void
    {
        // Log queries that take longer than 1 second in non-testing environments
        if (!app()->runningUnitTests()) {
            DB::listen(function ($query) {
                if ($query->time > 1000) {
                    Log::warning('Slow database query detected', [
                        'duration_ms' => $query->time,
                        'sql'         => $query->sql,
                        'bindings'    => $query->bindings,
                    ]);
                }
            });
        }
    }

    private function configureRateLimiting(): void
    {
        // General API: 120 requests per minute per user (or 30 per IP for guests)
        RateLimiter::for('api', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(120)->by($request->user()->id)
                : Limit::perMinute(30)->by($request->ip());
        });

        // Auth endpoints: 10 attempts per minute per IP to limit brute-force
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // MCP sync triggers: 20 per minute per org
        RateLimiter::for('mcp-sync', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(20)->by($request->user()->organization_id)
                : Limit::perMinute(5)->by($request->ip());
        });

        // Webhooks: 30 requests per minute per IP
        RateLimiter::for('webhooks', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        // Background jobs: 100 per minute per organization
        RateLimiter::for('tenant-jobs', function ($job) {
            $orgId = 'global';

            if (property_exists($job, 'organizationId')) {
                $orgId = $job->organizationId;
            } elseif (property_exists($job, 'organization')) {
                $orgId = $job->organization->id ?? 'global';
            } elseif (property_exists($job, 'report') && property_exists($job->report, 'organization_id')) {
                $orgId = $job->report->organization_id ?? 'global';
            } elseif (property_exists($job, 'connection') && property_exists($job->connection, 'organization_id')) {
                $orgId = $job->connection->organization_id ?? 'global';
            } elseif (property_exists($job, 'action') && property_exists($job->action, 'organization_id')) {
                $orgId = $job->action->organization_id ?? 'global';
            } elseif (property_exists($job, 'webhook') && property_exists($job->webhook, 'organization_id')) {
                $orgId = $job->webhook->organization_id ?? 'global';
            } elseif (property_exists($job, 'user') && property_exists($job->user, 'organization_id')) {
                $orgId = $job->user->organization_id ?? 'global';
            }

            return Limit::perMinute(100)->by($orgId);
        });
    }
}

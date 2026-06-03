<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    /**
     * Detailed health check — returns status of every subsystem.
     * Route is IP-restricted in routes/web.php.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $checks = [];
        $overall = 'ok';

        // 1. Database
        try {
            DB::connection()->getPdo();
            $checks['database'] = ['status' => 'ok'];
        } catch (\Throwable $e) {
            $checks['database'] = ['status' => 'fail', 'error' => $e->getMessage()];
            $overall = 'degraded';
        }

        // 2. Cache
        try {
            $key = 'health:' . uniqid();
            Cache::put($key, 1, 5);
            Cache::forget($key);
            $checks['cache'] = ['status' => 'ok'];
        } catch (\Throwable $e) {
            $checks['cache'] = ['status' => 'fail', 'error' => $e->getMessage()];
            $overall = 'degraded';
        }

        // 3. Queue — check that a job was processed recently (within last 10 minutes)
        try {
            $lastProcessed = DB::table('jobs')->orderByDesc('created_at')->value('created_at');
            $staleMinutes  = $lastProcessed ? now()->diffInMinutes($lastProcessed) : null;
            // Only flag as stale during business hours to avoid false alarms overnight
            $isBusinessHours = now()->isWeekday() && now()->hour >= 6 && now()->hour < 22;
            $isStale = $isBusinessHours && ($staleMinutes === null || $staleMinutes > 10);

            $checks['queue'] = [
                'status'          => $isStale ? 'warn' : 'ok',
                'last_job_age_m'  => $staleMinutes,
            ];
            if ($isStale) {
                $overall = $overall === 'ok' ? 'degraded' : $overall;
            }
        } catch (\Throwable $e) {
            $checks['queue'] = ['status' => 'fail', 'error' => $e->getMessage()];
            $overall = 'degraded';
        }

        // 4. Failed jobs
        try {
            $failedCount = DB::table('failed_jobs')->count();
            $checks['failed_jobs'] = [
                'status' => $failedCount > 0 ? 'warn' : 'ok',
                'count'  => $failedCount,
            ];
            if ($failedCount > 0) {
                $overall = $overall === 'ok' ? 'degraded' : $overall;
            }
        } catch (\Throwable $e) {
            $checks['failed_jobs'] = ['status' => 'fail', 'error' => $e->getMessage()];
            $overall = 'degraded';
        }

        // 5. Disk space
        try {
            $free  = disk_free_space(storage_path());
            $total = disk_total_space(storage_path());
            $usedPct = $total > 0 ? round((1 - $free / $total) * 100, 1) : 0;
            $checks['disk'] = [
                'status'    => $usedPct >= 90 ? 'warn' : 'ok',
                'used_pct'  => $usedPct,
                'free_gb'   => round($free / 1073741824, 2),
            ];
            if ($usedPct >= 90) {
                $overall = $overall === 'ok' ? 'degraded' : $overall;
            }
        } catch (\Throwable $e) {
            $checks['disk'] = ['status' => 'fail', 'error' => $e->getMessage()];
        }

        $statusCode = $overall === 'ok' ? 200 : 503;

        return response()->json([
            'status'     => $overall,
            'timestamp'  => now()->toISOString(),
            'checks'     => $checks,
        ], $statusCode);
    }
}

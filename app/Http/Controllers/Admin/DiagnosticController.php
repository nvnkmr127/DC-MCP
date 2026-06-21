<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\MCP\Models\McpSyncLog;
use Illuminate\Support\Facades\Queue;
use Inertia\Inertia;

class DiagnosticController extends Controller
{
    public function index()
    {
        // 1. Live Queue Status
        $queues = [
            'default' => Queue::size('default'),
            'high'    => Queue::size('high'),
            'low'     => Queue::size('low'),
        ];

        // 2. Active Syncs
        // Usually, a running sync is marked as 'running' or 'syncing'. We assume 'running'
        $activeSyncs = McpSyncLog::where('status', 'running')->count();

        // 3. Recent Errors
        $recentErrors = McpSyncLog::with('connection')
            ->where('status', 'failed')
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'provider' => $log->connection->provider ?? 'Unknown',
                    'connection_name' => $log->connection->name ?? 'Unknown',
                    'error_message' => $log->error_message,
                    'created_at' => $log->created_at->diffForHumans(),
                ];
            });

        return Inertia::render('Admin/Diagnostics', [
            'queues' => $queues,
            'activeSyncs' => $activeSyncs,
            'recentErrors' => $recentErrors,
        ]);
    }
}

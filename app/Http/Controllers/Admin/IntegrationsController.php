<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Modules\MCP\Models\McpConnection;
use App\Modules\MCP\Models\McpProvider;

class IntegrationsController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');

        $query = McpConnection::with(['organization', 'user']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('provider', 'like', '%' . $search . '%')
                  ->orWhereHas('organization', function ($q) use ($search) {
                      $q->where('name', 'like', '%' . $search . '%');
                  })
                  ->orWhereHas('user', function ($q) use ($search) {
                      $q->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%');
                  });
            });
        }

        $global_connections = $query->latest()->paginate(20)->withQueryString();

        $metrics = [
            'total' => McpConnection::count(),
            'active' => McpConnection::where('status', 'active')->count(),
            'error' => McpConnection::where('status', 'error')->count(),
            'degraded' => McpConnection::whereIn('status', ['degraded', 'rate_limited'])->count(),
        ];

        $organizations = \App\Modules\Auth\Models\Organization::orderBy('name')->get(['id', 'name']);

        // Diagnostics
        $diagnostics = [
            'queues' => [
                'default' => \Illuminate\Support\Facades\Queue::size('default'),
                'high'    => \Illuminate\Support\Facades\Queue::size('high'),
                'low'     => \Illuminate\Support\Facades\Queue::size('low'),
            ],
            'activeSyncs' => \App\Modules\MCP\Models\McpSyncLog::where('status', 'running')->count(),
            'recentErrors' => \App\Modules\MCP\Models\McpSyncLog::with('connection')
                ->where('status', 'failed')
                ->latest()
                ->take(10)
                ->get()
                ->map(fn($log) => [
                    'id' => $log->id,
                    'provider' => $log->connection->provider ?? 'Unknown',
                    'connection_name' => $log->connection->name ?? 'Unknown',
                    'error_message' => $log->error_message,
                    'created_at' => $log->created_at->diffForHumans(),
                ])
        ];

        return Inertia::render('Admin/Integrations', [
            'global_connections' => $global_connections,
            'metrics'            => $metrics,
            'filters'            => $request->only('search'),
            'organizations'      => $organizations,
            'diagnostics'        => $diagnostics,
            'providers_list'     => McpProvider::orderBy('name')->get(),
        ]);
    }
}

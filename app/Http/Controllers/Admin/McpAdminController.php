<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\MCP\Models\McpConnection;
use Illuminate\Http\Request;
use Inertia\Inertia;

class McpAdminController extends Controller
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

        $connections = $query->latest()->paginate(20)->withQueryString();

        // Calculate global metrics
        $metrics = [
            'total' => McpConnection::count(),
            'active' => McpConnection::where('status', 'active')->count(),
            'error' => McpConnection::where('status', 'error')->count(),
            'degraded' => McpConnection::whereIn('status', ['degraded', 'rate_limited'])->count(),
        ];

        $organizations = \App\Models\Organization::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/McpConnections', [
            'connections' => $connections,
            'metrics' => $metrics,
            'filters' => $request->only('search'),
            'organizations' => $organizations,
        ]);
    }

    public function impersonate(Request $request, \App\Modules\Auth\Models\User $user)
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Cannot impersonate yourself.');
        }

        $request->session()->put('impersonated_by', $request->user()->id);
        
        \App\Models\Activity::create([
            'user_id' => $request->user()->id,
            'subject_type' => get_class($user),
            'subject_id' => $user->id,
            'description' => 'Super Admin impersonation started',
            'changes' => json_encode([
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ])
        ]);

        \Illuminate\Support\Facades\Auth::login($user);

        return redirect('/dashboard')->with('success', "You are now impersonating {$user->name}.");
    }

    public function history(Request $request, McpConnection $connection)
    {
        $logs = \App\Modules\MCP\Models\McpSyncLog::where('mcp_connection_id', $connection->id)
            ->latest()
            ->paginate(20);

        return Inertia::render('Admin/McpHistory', [
            'connection' => $connection->load('organization', 'user'),
            'logs' => $logs,
        ]);
    }

    public function providers()
    {
        $providers = \App\Modules\MCP\Models\McpProvider::orderBy('name')->get();
        return Inertia::render('Admin/McpProviders', [
            'providers' => $providers
        ]);
    }

    public function storeProvider(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:mcp_providers,slug',
            'description' => 'nullable|string',
            'adapter_class' => 'nullable|string',
            'is_active' => 'boolean',
        ]);
        
        if (!isset($data['is_active'])) {
            $data['is_active'] = true;
        }

        \App\Modules\MCP\Models\McpProvider::create($data);
        return back()->with('success', 'Provider created successfully.');
    }

    public function updateProvider(Request $request, \App\Modules\MCP\Models\McpProvider $provider)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:mcp_providers,slug,' . $provider->id,
            'description' => 'nullable|string',
            'adapter_class' => 'nullable|string',
            'is_active' => 'boolean',
        ]);
        
        if (!isset($data['is_active'])) {
            $data['is_active'] = false;
        }

        $provider->update($data);
        return back()->with('success', 'Provider updated successfully.');
    }

    public function migrate(Request $request, McpConnection $connection)
    {
        $data = $request->validate([
            'organization_id' => 'required|exists:organizations,id',
        ]);

        $oldOrgId = $connection->organization_id;
        $connection->update([
            'organization_id' => $data['organization_id']
        ]);

        \App\Models\Activity::create([
            'user_id' => $request->user()->id,
            'subject_type' => get_class($connection),
            'subject_id' => $connection->id,
            'description' => "Migrated MCP connection to new organization",
            'changes' => [
                'old_organization_id' => $oldOrgId,
                'new_organization_id' => $data['organization_id']
            ]
        ]);

        return back()->with('success', 'Connection migrated successfully.');
    }
}

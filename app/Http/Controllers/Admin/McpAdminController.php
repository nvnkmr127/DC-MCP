<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\MCP\Models\McpConnection;
use Illuminate\Http\Request;
use Inertia\Inertia;

class McpAdminController extends Controller
{
    // Admin hub is now consolidated in McpWebController

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

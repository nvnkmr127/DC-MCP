<?php

namespace App\Modules\MCP\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;
use App\Modules\MCP\Models\McpConnection;
use App\Modules\MCP\Jobs\SyncMcpProviderJob;

class McpWebController extends Controller
{
    /** Get active built-in providers from DB */
    protected function getBuiltinProviders(): array
    {
        return \App\Modules\MCP\Models\McpProvider::where('is_active', true)->pluck('slug')->toArray();
    }

    public function index(Request $request)
    {
        $connections = McpConnection::orderBy('provider')
            ->get()
            ->map(fn($c) => [
                'id'           => $c->id,
                'provider'     => $c->provider,
                'label'        => $c->label ?? $c->name ?? ucfirst(str_replace('_', ' ', $c->provider)),
                'status'       => $c->status,
                'is_active'    => $c->is_active,
                'is_builtin'   => in_array($c->provider, $this->getBuiltinProviders()),
                'last_synced_at' => $c->last_synced_at?->toISOString(),
                'updated_at'   => $c->updated_at?->toISOString(),
                'sync_progress' => $c->sync_progress,
                'sync_error'    => $c->sync_error,
                'troubleshooting_guide' => $c->troubleshooting_guide,
                'last_sync_summary' => $c->settings['last_sync_summary'] ?? null,
                'settings'     => $c->settings ? array_filter($c->settings, fn($k) => !in_array($k, ['api_key', 'password', 'access_token']), ARRAY_FILTER_USE_KEY) : [],
            ]);

        return Inertia::render('Settings/MCP', [
            'connections'      => $connections,
            'builtin_providers' => $this->getBuiltinProviders(),
        ]);
    }

    public function store(Request $request)
    {
        $isBuiltin = in_array($request->provider, $this->getBuiltinProviders());

        $data = $request->validate([
            'provider'          => 'required|string|max:60',
            'label'             => 'nullable|string|max:120',
            'settings'          => 'nullable|array',
            'settings.base_url' => $isBuiltin ? 'nullable' : 'required|url',
            'access_token'      => 'nullable|string',
            'api_key'           => 'nullable|string',
            'username'          => 'nullable|string',
            'password'          => 'nullable|string',
        ]);

        $credentials = array_filter([
            'access_token'  => $data['access_token'] ?? null,
            'api_key'       => $data['api_key'] ?? null,
            'username'      => $data['username'] ?? null,
            'password'      => $data['password'] ?? null,
        ]);

        $label = $data['label'] ?? ucfirst(str_replace('_', ' ', $data['provider']));
        McpConnection::create([
            'organization_id' => $request->user()->organization_id,
            'provider'        => $data['provider'],
            'name'            => $label,
            'label'           => $label,
            'settings'        => $data['settings'] ?? [],
            'credentials'     => empty($credentials) ? null : Crypt::encryptString(json_encode($credentials)),
            'status'          => 'active',
            'is_active'       => true,
        ]);

        return back()->with('success', 'Connection created.');
    }

    public function show(McpConnection $connection)
    {
        $adapter = $this->resolveAdapter($connection->provider);
        $outboundActions = method_exists($adapter, 'getOutboundActions') ? $adapter->getOutboundActions() : [];

        return Inertia::render('Settings/MCPDetail', [
            'connection' => [
                'id'          => $connection->id,
                'provider'    => $connection->provider,
                'label'       => $connection->label,
                'status'      => $connection->status,
                'is_active'   => $connection->is_active,
                'settings'    => $connection->settings ?? [],
                'last_synced_at' => $connection->last_synced_at?->toISOString(),
            ],
            'outboundActions' => $outboundActions,
        ]);
    }

    public function update(Request $request, McpConnection $connection)
    {
        $data = $request->validate([
            'label'        => 'nullable|string|max:120',
            'settings'     => 'nullable|array',
            'is_active'    => 'nullable|boolean',
            'access_token' => 'nullable|string',
            'api_key'      => 'nullable|string',
            'username'     => 'nullable|string',
            'password'     => 'nullable|string',
        ]);

        $update = array_filter([
            'label'     => $data['label'] ?? null,
            'settings'  => $data['settings'] ?? null,
            'is_active' => $data['is_active'] ?? null,
        ], fn($v) => $v !== null);

        if (!empty($data['access_token']) || !empty($data['api_key']) || !empty($data['username']) || !empty($data['password'])) {
            $existing = $connection->credentials
                ? json_decode(Crypt::decryptString($connection->credentials), true)
                : [];
            if (!empty($data['access_token'])) $existing['access_token'] = $data['access_token'];
            if (!empty($data['api_key']))       $existing['api_key'] = $data['api_key'];
            if (!empty($data['username']))      $existing['username'] = $data['username'];
            if (!empty($data['password']))      $existing['password'] = $data['password'];
            $update['credentials'] = Crypt::encryptString(json_encode($existing));
            $update['status'] = 'active';
            $update['sync_error'] = null;
        }

        $connection->update($update);
        return back()->with('success', 'Connection updated.');
    }

    public function destroy(McpConnection $connection)
    {
        $connection->delete();
        return back()->with('success', 'Connection removed.');
    }

    public function test(McpConnection $connection)
    {
        SyncMcpProviderJob::dispatch($connection);
        return back()->with('success', 'Test/sync job dispatched.');
    }

    public function sync(McpConnection $connection)
    {
        SyncMcpProviderJob::dispatch($connection);
        return back()->with('success', 'Sync started.');
    }

    private function resolveAdapter(string $provider): mixed
    {
        $providerModel = \App\Modules\MCP\Models\McpProvider::where('slug', $provider)->first();

        if ($providerModel && $providerModel->adapter_class) {
            return app($providerModel->adapter_class);
        }

        return app(\App\Modules\MCP\Adapters\CustomMcpAdapter::class);
    }
}


<?php

namespace App\Modules\MCP\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MCP\Adapters\CustomMcpAdapter;
use App\Modules\MCP\Models\McpConnection;
use App\Modules\MCP\Jobs\SyncMcpProviderJob;
use App\Shared\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class McpConnectionController extends Controller
{
    /** Built-in providers that have dedicated adapters. */
    private const BUILTIN_PROVIDERS = [
        'gmail', 'google_calendar', 'notion', 'zoho_cliq', 'meta_ads', 'make',
    ];

    public function index(Request $request): JsonResponse
    {
        $connections = McpConnection::all()->map(function ($c) {
            return collect($c->toArray())
                ->except(['credentials'])
                ->put('is_expired', $c->isExpired())
                ->put('is_custom', !in_array($c->provider, self::BUILTIN_PROVIDERS));
        });

        return ApiResponse::success($connections);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'string', 'max:100'],
            'name'     => ['required', 'string', 'max:255'],
            'scopes'   => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],

            // Credentials — all optional at the top level; specific providers
            // may require certain keys, validated via the test-connection flow.
            'credentials'               => ['nullable', 'array'],
            'credentials.access_token'  => ['nullable', 'string'],
            'credentials.refresh_token' => ['nullable', 'string'],
            'credentials.api_key'       => ['nullable', 'string'],
            'credentials.username'      => ['nullable', 'string'],
            'credentials.password'      => ['nullable', 'string'],
            'credentials.expires_in'    => ['nullable', 'integer'],
            'credentials.created'       => ['nullable', 'integer'],
        ]);

        // For custom providers the settings.base_url is mandatory
        $isCustom = !in_array($data['provider'], self::BUILTIN_PROVIDERS);
        if ($isCustom && empty($data['settings']['base_url'])) {
            return ApiResponse::error(
                'settings.base_url is required for custom providers.',
                ['settings.base_url' => ['This field is required for custom providers.']],
                422
            );
        }

        $creds = $this->encryptCredentials($data['credentials'] ?? []);

        $connection = McpConnection::create([
            'organization_id' => $request->user()->organization_id,
            'user_id'         => $request->user()->id,
            'provider'        => $data['provider'],
            'name'            => $data['name'],
            'status'          => 'pending',
            'credentials'     => $creds,
            'scopes'          => $data['scopes'] ?? [],
            'settings'        => $data['settings'] ?? [],
        ]);

        return ApiResponse::success(
            collect($connection->toArray())->except(['credentials'])
                ->put('is_custom', $isCustom),
            'Connection created successfully.',
            [],
            201
        );
    }

    public function show(McpConnection $mcpConnection): JsonResponse
    {
        return ApiResponse::success(
            collect($mcpConnection->toArray())
                ->except(['credentials'])
                ->put('is_expired', $mcpConnection->isExpired())
                ->put('is_custom', !in_array($mcpConnection->provider, self::BUILTIN_PROVIDERS))
        );
    }

    public function update(Request $request, McpConnection $mcpConnection): JsonResponse
    {
        $data = $request->validate([
            'name'     => ['sometimes', 'string', 'max:255'],
            'scopes'   => ['sometimes', 'array'],
            'settings' => ['sometimes', 'array'],
            'status'   => ['sometimes', 'string', 'in:active,inactive,error,pending'],
        ]);

        $mcpConnection->update($data);

        return ApiResponse::success(
            collect($mcpConnection->fresh()->toArray())->except(['credentials']),
            'Connection updated.'
        );
    }

    public function destroy(McpConnection $mcpConnection): JsonResponse
    {
        $mcpConnection->delete();
        return ApiResponse::success(null, 'Connection deleted.');
    }

    public function sync(McpConnection $mcpConnection): JsonResponse
    {
        SyncMcpProviderJob::dispatch($mcpConnection);
        return ApiResponse::success(null, 'Sync queued.', [], 202);
    }

    public function test(McpConnection $mcpConnection): JsonResponse
    {
        try {
            $adapter = $this->resolveAdapter($mcpConnection->provider);

            // Pass decrypted credentials + settings so adapters can connect
            $credentials = $this->decryptConnectionCredentials($mcpConnection);

            // Custom adapters need settings injected alongside credentials
            if (!in_array($mcpConnection->provider, self::BUILTIN_PROVIDERS)) {
                $credentials['_settings'] = $mcpConnection->settings ?? [];
            }

            $result = $adapter->testConnection($credentials);

            return ApiResponse::success([
                'success'     => $result->isConnected,
                'message'     => $result->isConnected
                    ? 'Connection is working.'
                    : ($result->errorMessage ?? 'Connection test failed.'),
                'diagnostics' => $result->diagnostics,
            ]);
        } catch (\Exception $e) {
            return ApiResponse::error('Connection test failed: ' . $e->getMessage(), [], 422);
        }
    }

    public function webhook(Request $request, string $provider, string $connectionId): JsonResponse
    {
        $connection = McpConnection::where('provider', $provider)
            ->where('id', $connectionId)
            ->first();

        if (!$connection) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        try {
            $adapter = $this->resolveAdapter($provider);
            $result  = $adapter->handleWebhook($request);

            event(new \App\Modules\MCP\Events\McpWebhookReceived($connection, $result, $request->all()));

            return response()->json([
                'message' => 'Webhook processed.',
                'status'  => $result->status,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Webhook processing failed.'], 500);
        }
    }

    /** Lists both built-in and any registered custom providers for the org. */
    public function providers(Request $request): JsonResponse
    {
        $usedProviders = McpConnection::where('organization_id', $request->user()->organization_id)
            ->select('provider')
            ->distinct()
            ->pluck('provider')
            ->toArray();

        return ApiResponse::success([
            'builtin' => self::BUILTIN_PROVIDERS,
            'custom'  => array_values(array_filter($usedProviders, fn ($p) => !in_array($p, self::BUILTIN_PROVIDERS))),
        ]);
    }

    private function resolveAdapter(string $provider): \App\Modules\MCP\Contracts\MCPAdapter
    {
        return match ($provider) {
            'gmail'           => app(\App\Modules\MCP\Adapters\GmailAdapter::class),
            'google_calendar' => app(\App\Modules\MCP\Adapters\GoogleCalendarAdapter::class),
            'notion'          => app(\App\Modules\MCP\Adapters\NotionAdapter::class),
            'zoho_cliq'       => app(\App\Modules\MCP\Adapters\ZohoCliqAdapter::class),
            'meta_ads'        => app(\App\Modules\MCP\Adapters\MetaAdsAdapter::class),
            'make'            => app(\App\Modules\MCP\Adapters\MakeAdapter::class),
            default           => app(CustomMcpAdapter::class),
        };
    }

    private function decryptConnectionCredentials(McpConnection $connection): array
    {
        $raw = $connection->credentials ?? [];
        $out = [];

        foreach ($raw as $key => $value) {
            if (is_string($value)) {
                try {
                    $out[$key] = Crypt::decryptString($value);
                } catch (\Exception $e) {
                    $out[$key] = $value;
                }
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    private function encryptCredentials(array $credentials): array
    {
        $out = [];
        $encryptable = ['access_token', 'refresh_token', 'api_key', 'username', 'password'];

        foreach ($credentials as $key => $value) {
            if (in_array($key, $encryptable) && is_string($value) && $value !== '') {
                $out[$key] = Crypt::encryptString($value);
            } else {
                $out[$key] = $value;
            }
        }

        // Stamp creation time if missing
        $out['created'] = $out['created'] ?? time();

        return $out;
    }
}

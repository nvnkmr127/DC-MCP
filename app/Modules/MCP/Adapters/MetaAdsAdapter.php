<?php

namespace App\Modules\MCP\Adapters;

use App\Modules\MCP\Models\McpConnection;
use App\Modules\MCP\DataObjects\SyncResult;
use App\Modules\MCP\DataObjects\WebhookResult;
use App\Modules\MCP\DataObjects\ConnectionTestResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MetaAdsAdapter extends BaseAdapter
{
    /**
     * Get the provider identifier.
     *
     * @return string
     */
    protected function getProviderName(): string
    {
        return 'meta_ads';
    }

    /**
     * Pre-save credential format validation.
     *
     * @param array $credentials
     * @return void
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validateCredentialsFormat(#[SensitiveParameter] array $credentials): void
    {
        $token = $credentials['access_token'] ?? '';
        if (!empty($token) && !str_starts_with($token, 'EA')) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'credentials.access_token' => 'Meta Ads token must start with EA'
            ]);
        }
    }

    /**
     * Setup Guzzle client config for Meta Marketing API.
     *
     * @param array $credentials
     * @return void
     */
    protected function setupMetaClient(#[SensitiveParameter] array $credentials): void
    {
        $accessToken = $credentials['access_token'] ?? '';
        $settings = $credentials['_settings'] ?? [];
        $isSandbox = $settings['is_sandbox'] ?? false;
        
        $baseUrl = $isSandbox ? 'https://graph.sandbox.facebook.com/v20.0/' : 'https://graph.facebook.com/v20.0/';

        $this->setupClient($baseUrl, [
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * Authenticate or prepare credentials for the external API.
     *
     * @param array $credentials
     * @return bool
     */
    public function authenticate(#[SensitiveParameter] array $credentials): bool
    {
        try {
            $this->setupMetaClient($credentials);
            $response = $this->client->get('me?fields=id');
            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Pull campaign metrics from Meta Ads to local database.
     *
     * @param string $connectionId
     * @return SyncResult
     */
    public function sync(string $connectionId, array $options = []): SyncResult
    {
        $startTime = microtime(true);
        $connection = McpConnection::findOrFail($connectionId);
        $credentials = $this->decryptCredentials($connection->credentials ?? []);
        
        try {
            $this->setupMetaClient($credentials);

            $settings = $connection->settings ?? [];
            $adAccountIds = $settings['ad_account_ids'] ?? [];
            $clientId = $settings['client_id'] ?? null;
            $projectId = $settings['project_id'] ?? null;
            $metricsToPull = $settings['metrics_to_pull'] ?? ['impressions', 'clicks', 'spend'];
            $dateRange = $settings['date_range'] ?? 'yesterday';

            // Resolve date ranges
            $since = null;
            $until = null;
            if ($dateRange === 'yesterday') {
                $since = $until = date('Y-m-d', strtotime('yesterday'));
            } elseif ($dateRange === 'last_7d') {
                $since = date('Y-m-d', strtotime('-7 days'));
                $until = date('Y-m-d', strtotime('yesterday'));
            } elseif ($dateRange === 'last_30d') {
                $since = date('Y-m-d', strtotime('-30 days'));
                $until = date('Y-m-d', strtotime('yesterday'));
            } else {
                $since = $until = date('Y-m-d', strtotime('yesterday'));
            }

            $timeRange = json_encode(['since' => $since, 'until' => $until]);
            $processedCount = 0;
            $failedCount = 0;

            // Gather standard fields
            $fields = array_unique(array_merge([
                'campaign_name',
                'campaign_id',
                'date_start',
                'date_stop'
            ], $metricsToPull));

            foreach ($adAccountIds as $adAccountId) {
                // Formatting adAccountId (ensure it has act_ prefix if omitted)
                $formattedId = str_starts_with($adAccountId, 'act_') ? $adAccountId : 'act_' . $adAccountId;

                try {
                    $response = $this->client->get("{$formattedId}/insights", [
                        'query' => [
                            'level' => 'campaign',
                            'fields' => implode(',', $fields),
                            'time_range' => $timeRange
                        ]
                    ]);

                    $body = json_decode($response->getBody()->getContents(), true);
                    $insights = $body['data'] ?? [];

                    foreach ($insights as $insight) {
                        $campaignId = $insight['campaign_id'] ?? 'unknown';
                        $campaignName = $insight['campaign_name'] ?? '';
                        $dateKey = $insight['date_start'] ?? $since;
                        $recordedAt = date('Y-m-d H:i:s', strtotime($dateKey));

                        // Log sync attempt
                        $this->logSync(
                            $connectionId,
                            'inbound',
                            'meta_campaign_insights',
                            $campaignId,
                            'success',
                            1,
                            0,
                            ['id' => $campaignId, 'campaign_name' => $campaignName, 'ad_account_id' => $formattedId]
                        );

                        foreach ($metricsToPull as $metric) {
                            if (!isset($insight[$metric])) {
                                continue;
                            }

                            $value = (float) $insight[$metric];
                            $kpiId = $this->getOrCreateKpiDefinition($connection, $metric);

                            // Update or insert into metric_snapshots
                            $snapshot = DB::table('metric_snapshots')
                                ->where('organization_id', $connection->organization_id)
                                ->where('kpi_definition_id', $kpiId)
                                ->where('source_external_id', $campaignId)
                                ->where('date_key', $dateKey)
                                ->first();

                            if ($snapshot) {
                                DB::table('metric_snapshots')
                                    ->where('id', $snapshot->id)
                                    ->update([
                                        'value' => $value,
                                        'dimension_1' => $campaignName,
                                        'metadata' => json_encode($insight),
                                        'synced_at' => now(),
                                    ]);
                            } else {
                                DB::table('metric_snapshots')->insert([
                                    'id' => (string) Str::uuid(),
                                    'organization_id' => $connection->organization_id,
                                    'kpi_definition_id' => $kpiId,
                                    'project_id' => $projectId,
                                    'client_id' => $clientId,
                                    'mcp_connection_id' => $connection->id,
                                    'value' => $value,
                                    'dimension_1' => $campaignName,
                                    'dimension_2' => '',
                                    'metadata' => json_encode($insight),
                                    'source_external_id' => $campaignId,
                                    'recorded_at' => $recordedAt,
                                    'synced_at' => now(),
                                    'date_key' => $dateKey,
                                ]);
                            }
                            $processedCount++;
                            $this->reportSyncProgress($connection, $processedCount);
                        }
                    }
                } catch (\Exception $e) {
                    $failedCount++;
                    $this->logSync(
                        $connectionId,
                        'inbound',
                        'meta_insights_account',
                        $formattedId,
                        'failed',
                        0,
                        1,
                        null,
                        $e->getMessage()
                    );
                }
            }

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $connection->markSynced();

            return SyncResult::success($processedCount, [
                'duration_ms' => $durationMs,
                'failed_count' => $failedCount
            ]);

        } catch (\Exception $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $connection->handleException($e);
            return SyncResult::failure($e->getMessage(), 0, 1, ['duration_ms' => $durationMs]);
        }
    }

    /**
     * Push local changes (Not supported on Meta Ads which is read-only).
     *
     * @param string $connectionId
     * @param array $data
     * @return SyncResult
     */
    protected function performPush(string $connectionId, array $data, array $options = []): SyncResult
    {
        return SyncResult::success(0, ['reason' => 'Meta Ads adapter does not support push operations']);
    }

    /**
     * Handle incoming webhooks.
     *
     * @param Request $request
     * @return WebhookResult
     */
    public function handleWebhook(Request $request): WebhookResult
    {
        // Meta webhooks verification flow
        if ($request->query('hub_mode') === 'subscribe') {
            $verifyToken = config('services.meta.webhook_verify_token');
            if (!$verifyToken) {
                return WebhookResult::failed('META_WEBHOOK_VERIFY_TOKEN is not configured.');
            }
            if ($request->query('hub_verify_token') === $verifyToken) {
                // User would need to output $request->query('hub_challenge') directly
                return WebhookResult::processed(['challenge' => $request->query('hub_challenge')]);
            }
            return WebhookResult::failed('Verification token mismatch');
        }

        // Handle regular webhook data
        $payload = json_decode($request->getContent(), true);
        if (!$payload) {
            return WebhookResult::failed('Empty or invalid webhook payload');
        }

        // Just trigger sync or run logic depending on event
        $connection = McpConnection::where('provider', 'meta_ads')
            ->where('status', 'active')
            ->first();

        if ($connection) {
            if (class_exists('App\Modules\MCP\Jobs\SyncMcpProviderJob')) {
                \App\Modules\MCP\Jobs\SyncMcpProviderJob::dispatch($connection);
            } else {
                $this->sync($connection->id);
            }
        }

        return WebhookResult::processed(['message' => 'Meta webhook processed and sync triggered']);
    }

    /**
     * Test connection to Meta Ads API.
     *
     * @param array $credentials
     * @return ConnectionTestResult
     */
    public function testConnection(#[SensitiveParameter] array $credentials): ConnectionTestResult
    {
        try {
            $this->setupMetaClient($credentials);
            $response = $this->client->get('me?fields=id,name');
            $data = json_decode($response->getBody()->getContents(), true);

            return ConnectionTestResult::success([
                'provider' => 'meta_ads',
                'account_info' => $data,
                'connected_at' => now()->toIso8601String()
            ]);
        } catch (\Exception $e) {
            return ConnectionTestResult::failure($e->getMessage());
        }
    }

    /**
     * Get available scopes.
     *
     * @return array
     */
    public function getAvailableScopes(): array
    {
        return ['ads_read', 'ads_management'];
    }

    /**
     * Get or create a KPI definition for organization.
     */
    protected function getOrCreateKpiDefinition(McpConnection $connection, string $metric): string
    {
        $slug = 'meta_' . strtolower($metric);
        $kpi = DB::table('kpi_definitions')
            ->where('organization_id', $connection->organization_id)
            ->where('slug', $slug)
            ->first();

        if ($kpi) {
            return $kpi->id;
        }

        $id = (string) Str::uuid();
        DB::table('kpi_definitions')->insert([
            'id' => $id,
            'organization_id' => $connection->organization_id,
            'name' => 'Meta ' . ucfirst($metric),
            'slug' => $slug,
            'description' => 'Meta Ads ' . $metric . ' metric synced from Marketing API.',
            'category' => 'marketing',
            'source' => 'meta_ads',
            'mcp_connection_id' => $connection->id,
            'aggregation' => in_array($metric, ['cpm', 'cpc', 'ctr', 'roas']) ? 'average' : 'sum',
            'unit' => in_array($metric, ['spend']) ? 'INR' : (in_array($metric, ['ctr']) ? '%' : 'count'),
            'target_value' => null,
            'target_direction' => 'higher_better',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    public function getCapabilities(): array
    {
        return [
            'read_insights',
            'read_campaigns',
            'webhook_support'
        ];
    }

    public function getApiVersion(): string
    {
        return 'v20.0';
    }

    public function getCatalogueMetadata(): array
    {
        $metadata = parent::getCatalogueMetadata();
        $metadata['display_name'] = 'Meta Ads';
        $metadata['description'] = 'Sync campaign performance and metrics.';
        $metadata['logo_url'] = 'https://upload.wikimedia.org/wikipedia/commons/7/7b/Meta_Platforms_Inc._logo.svg';
        $metadata['setup_guide_url'] = 'https://developers.facebook.com/docs/marketing-api/';
        $metadata['supports_sandbox'] = true;
        return $metadata;
    }

    public function getDataPermissions(): array
    {
        return [
            'read' => ['ad accounts', 'campaigns', 'insights', 'business details'],
            'write' => ['campaigns', 'ad sets', 'ads', 'custom audiences']
        ];
    }

    /**
     * Preview what data will be synced without actually syncing it.
     */
    public function syncPreview(#[SensitiveParameter] array $credentials, array $options = []): array
    {
        try {
            $this->setupMetaClient($credentials);
            $settings = $credentials['_settings'] ?? [];
            $adAccountIds = $settings['ad_account_ids'] ?? [];

            $totalEstimatedRecords = 0;
            $accountsCount = count($adAccountIds);

            if ($accountsCount === 0) {
                return [
                    'supported' => true,
                    'records_to_process' => 0,
                    'summary' => 'No ad accounts configured for sync.'
                ];
            }

            foreach ($adAccountIds as $adAccountId) {
                $formattedId = str_starts_with($adAccountId, 'act_') ? $adAccountId : 'act_' . $adAccountId;
                // Just fetch 1 campaign to check connectivity
                $response = $this->client->get("{$formattedId}/campaigns", [
                    'query' => ['limit' => 1]
                ]);
                
                $data = json_decode($response->getBody()->getContents(), true);
                if (!empty($data['data'])) {
                    $totalEstimatedRecords += 1;
                }
            }

            return [
                'supported' => true,
                'records_to_process' => $totalEstimatedRecords,
                'summary' => "Ready to sync from {$accountsCount} ad account(s). The preview successfully connected and verified data access.",
            ];

        } catch (\Exception $e) {
            return [
                'supported' => true,
                'records_to_process' => 0,
                'summary' => "Preview failed: " . $e->getMessage()
            ];
        }
    }
}

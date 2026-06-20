<?php

namespace App\Modules\MCP\Adapters;

use App\Modules\MCP\Models\McpConnection;
use App\Modules\MCP\DataObjects\SyncResult;
use App\Modules\MCP\DataObjects\WebhookResult;
use App\Modules\MCP\DataObjects\ConnectionTestResult;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\ProjectManagement\Models\Task;
use App\Modules\Auth\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MakeAdapter extends BaseAdapter
{
    /**
     * Get the provider identifier.
     *
     * @return string
     */
    protected function getProviderName(): string
    {
        return 'make';
    }

    /**
     * Pre-save credential format validation.
     *
     * @param array $credentials
     * @return void
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validateCredentialsFormat(array $credentials): void
    {
        $token = $credentials['api_key'] ?? '';
        if (!empty($token) && strlen($token) < 10) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'credentials.api_key' => 'Make API key must be at least 10 characters long'
            ]);
        }
    }

    /**
     * Set up Guzzle client for Make.com REST API.
     *
     * @param array $credentials
     * @return void
     */
    protected function setupMakeClient(array $credentials): void
    {
        $apiKey = $credentials['api_key'] ?? '';
        $this->setupClient('https://api.make.com/v2/', [
            'Authorization' => 'Token ' . $apiKey,
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * Authenticate or prepare credentials for the external API.
     *
     * @param array $credentials
     * @return bool
     */
    public function authenticate(array $credentials): bool
    {
        try {
            $this->setupMakeClient($credentials);
            $response = $this->client->get('users/me');
            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Pull Make execution results and log failures to sync logs.
     *
     * @param string $connectionId
     * @return SyncResult
     */
    public function sync(string $connectionId, array $options = []): SyncResult
    {
        $startTime = microtime(true);
        $connection = McpConnection::findOrFail($connectionId);

        try {
            $executions = $this->getRecentExecutions(50, $connectionId);
            $processedCount = count($executions);
            $failedCount = 0;

            foreach ($executions as $exec) {
                $isFailed = in_array(strtolower($exec['status']), ['failed', 'error', 'incomplete']);

                if ($isFailed) {
                    $failedCount++;
                    $this->logSync(
                        $connectionId,
                        'inbound',
                        'make_scenario_execution',
                        $exec['execution_id'],
                        'failed',
                        0,
                        1,
                        $exec['raw'],
                        $exec['error'] ?? 'Execution failed',
                        (int) $exec['duration']
                    );
                } else {
                    $this->logSync(
                        $connectionId,
                        'inbound',
                        'make_scenario_execution',
                        $exec['execution_id'],
                        'success',
                        1,
                        0,
                        $exec['raw'],
                        null,
                        (int) $exec['duration']
                    );
                }
            }

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $connection->markSynced();

            return new SyncResult(true, $processedCount, $failedCount, null, [
                'duration_ms' => $durationMs,
            ]);

        } catch (\Exception $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $connection->handleException($e);
            return SyncResult::failure($e->getMessage(), 0, 1, ['duration_ms' => $durationMs]);
        }
    }

    /**
     * Push local changes (Trigger a Make scenario webhook).
     *
     * @param string $connectionId
     * @param array $data
     * @return SyncResult
     */
    public function push(string $connectionId, array $data, array $options = []): SyncResult
    {
        $eventType = $data['event_type'] ?? null;
        $payload = $data['payload'] ?? $data;

        if (!$eventType) {
            return SyncResult::failure('Missing event_type in push data', 0, 1);
        }

        $success = $this->triggerScenario($eventType, $payload, $connectionId);

        if ($success) {
            return SyncResult::success(1);
        }

        return SyncResult::failure("Failed to trigger Make scenario for event: {$eventType}", 0, 1);
    }

    /**
     * Handle incoming webhooks from Make.
     *
     * @param Request $request
     * @return WebhookResult
     */
    public function handleWebhook(Request $request): WebhookResult
    {
        return $this->handleInboundWebhook($request);
    }

    /**
     * Detailed implementation of Inbound webhooks.
     *
     * @param Request $request
     * @return WebhookResult
     */
    public function handleInboundWebhook(Request $request): WebhookResult
    {
        $payload = json_decode($request->getContent(), true) ?? $request->all();

        if (empty($payload)) {
            return WebhookResult::failed('Empty or invalid webhook payload');
        }

        // 1. Identify which connection this belongs to
        $connectionId = $request->input('connection_id') ?? $request->query('connection_id');
        $connection = null;

        if ($connectionId) {
            $connection = McpConnection::find($connectionId);
        } else {
            // Find by matching shared secret
            $secret = $request->header('X-Make-Signature') ?? $request->input('secret') ?? $request->query('secret');
            if ($secret) {
                $connection = McpConnection::where('provider', 'make')
                    ->where('settings->shared_secret', $secret)
                    ->first();
            }
        }

        if (!$connection) {
            // Fallback to first active Make connection
            $connection = McpConnection::where('provider', 'make')
                ->where('status', 'active')
                ->first();
        }

        if (!$connection) {
            return WebhookResult::failed('No Make.com connection found.');
        }

        // 2. Validate the request using the shared secret in settings
        $settings = $connection->settings ?? [];
        $sharedSecret = $settings['shared_secret'] ?? null;

        if ($sharedSecret) {
            $requestSecret = $request->header('X-Make-Signature') 
                ?? $request->input('secret') 
                ?? $request->query('secret');

            if ($requestSecret !== $sharedSecret) {
                return WebhookResult::failed('Unauthorized. Secret verification failed.');
            }
        }

        $action = $payload['action'] ?? null;

        // Auto-detect action if not explicitly sent
        if (!$action) {
            if (isset($payload['project_id']) && isset($payload['title'])) {
                $action = 'create_task';
            } elseif (isset($payload['kpi_slug']) && isset($payload['value'])) {
                $action = 'update_metric';
            } elseif (isset($payload['user_id']) && (isset($payload['message']) || isset($payload['body']))) {
                $action = 'send_notification';
            }
        }

        switch ($action) {
            case 'create_task':
                $projectId = $payload['project_id'] ?? null;
                $title = $payload['title'] ?? null;

                if (!$projectId || !$title) {
                    return WebhookResult::failed('Missing project_id or title for task creation.');
                }

                $project = Project::find($projectId);
                if (!$project) {
                    return WebhookResult::failed("Project not found: {$projectId}");
                }

                $assigneeId = null;
                if (!empty($payload['assigned_to'])) {
                    $assigneeId = $payload['assigned_to'];
                } elseif (!empty($payload['assignee_email'])) {
                    $user = User::where('email', $payload['assignee_email'])->first();
                    if ($user) {
                        $assigneeId = $user->id;
                    }
                }

                $task = Task::create([
                    'organization_id' => $connection->organization_id,
                    'project_id' => $projectId,
                    'title' => $title,
                    'description' => $payload['description'] ?? 'Created via Make.com webhook',
                    'type' => $payload['type'] ?? 'other',
                    'status' => $payload['status'] ?? 'todo',
                    'priority' => $payload['priority'] ?? 'medium',
                    'assigned_to' => $assigneeId,
                    'role_required' => $payload['assigned_role'] ?? $payload['role_required'] ?? 'developer',
                    'due_date' => $payload['due_date'] ?? null,
                    'created_by' => $connection->user_id,
                ]);

                return WebhookResult::processed([
                    'message' => 'Task created successfully',
                    'task_id' => $task->id
                ]);

            case 'update_metric':
                $kpiSlug = $payload['kpi_slug'] ?? null;
                $value = $payload['value'] ?? null;

                if (!$kpiSlug || $value === null) {
                    return WebhookResult::failed('Missing kpi_slug or value for metric update.');
                }

                $recordedAt = $payload['recorded_at'] ?? now()->toDateTimeString();
                $projectId = $payload['project_id'] ?? null;
                $clientId = $payload['client_id'] ?? null;

                $kpiId = $this->getOrCreateKpiDefinitionBySlug($connection, $kpiSlug);
                $dateKey = date('Y-m-d', strtotime($recordedAt));
                $recordedAtTimestamp = date('Y-m-d H:i:s', strtotime($recordedAt));

                $snapshot = DB::table('metric_snapshots')
                    ->where('organization_id', $connection->organization_id)
                    ->where('kpi_definition_id', $kpiId)
                    ->where('date_key', $dateKey)
                    ->when($projectId, function($q) use ($projectId) {
                        return $q->where('project_id', $projectId);
                    })
                    ->first();

                if ($snapshot) {
                    DB::table('metric_snapshots')
                        ->where('id', $snapshot->id)
                        ->update([
                            'value' => (float) $value,
                            'metadata' => json_encode($payload),
                            'synced_at' => now(),
                        ]);
                    $snapshotId = $snapshot->id;
                } else {
                    $snapshotId = (string) Str::uuid();
                    DB::table('metric_snapshots')->insert([
                        'id' => $snapshotId,
                        'organization_id' => $connection->organization_id,
                        'kpi_definition_id' => $kpiId,
                        'project_id' => $projectId,
                        'client_id' => $clientId,
                        'mcp_connection_id' => $connection->id,
                        'value' => (float) $value,
                        'dimension_1' => 'Make webhook',
                        'dimension_2' => '',
                        'metadata' => json_encode($payload),
                        'source_external_id' => 'make_' . time() . '_' . rand(1000, 9999),
                        'recorded_at' => $recordedAtTimestamp,
                        'synced_at' => now(),
                        'date_key' => $dateKey,
                    ]);
                }

                return WebhookResult::processed([
                    'message' => 'Metric snapshot updated successfully',
                    'snapshot_id' => $snapshotId
                ]);

            case 'send_notification':
                $userId = $payload['user_id'] ?? null;
                $message = $payload['message'] ?? $payload['body'] ?? '';
                $channel = $payload['channel'] ?? 'in_app';
                $title = $payload['title'] ?? 'Make.com Notification';

                if (!$userId || !$message) {
                    return WebhookResult::failed('Missing user_id or message for notification.');
                }

                $user = User::find($userId);
                if (!$user) {
                    return WebhookResult::failed("User not found: {$userId}");
                }

                $notificationId = (string) Str::uuid();
                DB::table('notifications_log')->insert([
                    'id' => $notificationId,
                    'organization_id' => $connection->organization_id,
                    'user_id' => $userId,
                    'type' => 'system',
                    'channel' => $channel,
                    'title' => $title,
                    'body' => $message,
                    'data' => json_encode($payload),
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return WebhookResult::processed([
                    'message' => 'Notification logged successfully',
                    'notification_id' => $notificationId
                ]);

            default:
                return WebhookResult::skipped('Action unrecognized or skipped');
        }
    }

    /**
     * Test connection to Make.com API.
     *
     * @param array $credentials
     * @return ConnectionTestResult
     */
    public function testConnection(array $credentials): ConnectionTestResult
    {
        try {
            $this->setupMakeClient($credentials);
            $response = $this->client->get('users/me');
            $data = json_decode($response->getBody()->getContents(), true);

            return ConnectionTestResult::success([
                'provider' => 'make',
                'user_info' => $data,
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
        return ['scenarios:read', 'scenarios:write', 'teams:read', 'organizations:read'];
    }

    /**
     * Get available outbound triggers.
     *
     * @return array
     */
    public function getAvailableTriggers(): array
    {
        return [
            'task_created',
            'task_completed',
            'task_sla_breached',
            'project_created',
            'project_completed',
            'report_generated',
            'report_sent',
            'new_client_added',
            'campaign_alert',
            'daily_briefing_generated',
        ];
    }

    /**
     * Outbound scenario trigger helper.
     *
     * @param string $eventType
     * @param array $data
     * @param string|null $connectionId
     * @return bool
     */
    public function triggerScenario(string $eventType, array $data, ?string $connectionId = null): bool
    {
        $startTime = microtime(true);

        if (!$connectionId) {
            $connection = McpConnection::where('provider', 'make')
                ->where('status', 'active')
                ->first();
            if (!$connection) {
                return false;
            }
            $connectionId = $connection->id;
        } else {
            $connection = McpConnection::find($connectionId);
            if (!$connection) {
                return false;
            }
        }

        $settings = $connection->settings ?? [];
        $webhookScenarios = $settings['webhook_scenarios'] ?? [];
        $webhookUrl = $webhookScenarios[$eventType] ?? null;

        if (!$webhookUrl) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->logSync(
                $connectionId,
                'outbound',
                'make_event_trigger',
                $eventType,
                'skipped',
                0,
                0,
                ['event_type' => $eventType, 'data' => $data],
                "No webhook configured for event type: {$eventType}",
                $durationMs
            );
            return false;
        }

        try {
            $client = $this->getWebhookClient();
            $response = $client->post($webhookUrl, [
                'json' => $data,
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'timeout' => 10.0,
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            $success = $statusCode >= 200 && $statusCode < 300;

            $this->logSync(
                $connectionId,
                'outbound',
                'make_event_trigger',
                $eventType,
                $success ? 'success' : 'failed',
                $success ? 1 : 0,
                $success ? 0 : 1,
                ['event_type' => $eventType, 'data' => $data, 'webhook_url' => $webhookUrl, 'response' => $responseBody],
                $success ? null : "Webhook returned status code: {$statusCode}",
                $durationMs
            );

            return $success;
        } catch (\Exception $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->logSync(
                $connectionId,
                'outbound',
                'make_event_trigger',
                $eventType,
                'failed',
                0,
                1,
                ['event_type' => $eventType, 'data' => $data, 'webhook_url' => $webhookUrl],
                $e->getMessage(),
                $durationMs
            );
            return false;
        }
    }

    /**
     * Get recent executions from Make API.
     *
     * @param int $limit
     * @param string|null $connectionId
     * @return array
     */
    public function getRecentExecutions(int $limit = 50, ?string $connectionId = null): array
    {
        if (!$connectionId) {
            $connection = McpConnection::where('provider', 'make')
                ->where('status', 'active')
                ->first();
            if (!$connection) {
                return [];
            }
        } else {
            $connection = McpConnection::find($connectionId);
            if (!$connection) {
                return [];
            }
        }

        $credentials = $this->decryptCredentials($connection->credentials ?? []);
        $this->setupMakeClient($credentials);

        $settings = $connection->settings ?? [];
        $scenarioIds = $settings['scenario_ids'] ?? [];

        if (empty($scenarioIds)) {
            try {
                $teamId = $settings['team_id'] ?? null;
                $query = $teamId ? ['teamId' => $teamId] : [];
                $response = $this->client->get('scenarios', ['query' => $query]);
                $body = json_decode($response->getBody()->getContents(), true);
                $scenarios = $body['scenarios'] ?? $body['data'] ?? [];
                foreach ($scenarios as $s) {
                    if (isset($s['id'])) {
                        $scenarioIds[] = $s['id'];
                    }
                }
            } catch (\Exception $e) {
                // Return empty if fetching scenarios fails
            }
        }

        $allExecutions = [];

        foreach ($scenarioIds as $scenarioId) {
            try {
                $response = $this->client->get("scenarios/{$scenarioId}/executions", [
                    'query' => ['limit' => $limit]
                ]);
                $body = json_decode($response->getBody()->getContents(), true);
                $executions = $body['executions'] ?? $body['data'] ?? $body ?? [];

                // Normalize executions format
                if (!is_array($executions)) {
                    continue;
                }

                foreach ($executions as $exec) {
                    if (!is_array($exec)) {
                        continue;
                    }
                    $execId = $exec['id'] ?? $exec['executionId'] ?? 'unknown';
                    $status = $exec['status'] ?? 'unknown';
                    $duration = $exec['duration'] ?? $exec['durationMs'] ?? 0;
                    $errorMessage = $exec['error'] ?? $exec['errorMessage'] ?? null;

                    $allExecutions[] = [
                        'scenario_id' => $scenarioId,
                        'execution_id' => $execId,
                        'status' => $status,
                        'duration' => $duration,
                        'error' => $errorMessage,
                        'raw' => $exec
                    ];
                }
            } catch (\Exception $e) {
                // Skip failed scenario executions pulls
            }
        }

        return $allExecutions;
    }

    /**
     * Get or create a KPI definition for organization.
     *
     * @param McpConnection $connection
     * @param string $slug
     * @return string
     */
    protected function getOrCreateKpiDefinitionBySlug(McpConnection $connection, string $slug): string
    {
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
            'name' => 'Make ' . ucwords(str_replace('_', ' ', $slug)),
            'slug' => $slug,
            'description' => 'Metric synced from Make.com.',
            'category' => 'marketing',
            'source' => 'internal',
            'mcp_connection_id' => $connection->id,
            'aggregation' => 'sum',
            'unit' => 'count',
            'target_value' => null,
            'target_direction' => 'higher_better',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    /**
     * Trigger a Make scenario but require CEO approval first.
     * Creates a pending task suggestion of type 'automation' instead of firing directly.
     * The CEO approves from the AI Suggestions page, which then calls triggerScenario().
     *
     * @param string $eventType
     * @param array $data  payload to send when approved
     * @param string $label human-readable label shown to CEO
     * @param string|null $connectionId
     */
    public function queueTriggerForApproval(string $eventType, array $data, string $label, ?string $connectionId = null): void
    {
        $connection = $connectionId
            ? McpConnection::find($connectionId)
            : McpConnection::where('provider', 'make')->where('status', 'active')->first();

        if (!$connection) {
            return;
        }

        \App\Modules\TaskEngine\Models\TaskSuggestion::create([
            'organization_id' => $connection->organization_id,
            'title'           => "Make.com: {$label}",
            'description'     => "Approve to trigger the Make.com scenario for event: {$eventType}.",
            'role_required'   => 'ceo',
            'priority'        => 'medium',
            'suggested_by'    => 'make_automation',
            'status'          => 'pending',
            'meta'            => [
                'automation'       => true,
                'event_type'       => $eventType,
                'payload'          => $data,
                'connection_id'    => $connection->id,
            ],
        ]);
    }

    /**
     * Fire a queued automation trigger after CEO approval.
     * Called by TaskSuggestionService when approving an automation suggestion.
     */
    public function fireApprovedTrigger(\App\Modules\TaskEngine\Models\TaskSuggestion $suggestion): bool
    {
        $meta         = $suggestion->meta ?? [];
        $eventType    = $meta['event_type']   ?? null;
        $payload      = $meta['payload']      ?? [];
        $connectionId = $meta['connection_id'] ?? null;

        if (!$eventType) {
            return false;
        }

        return $this->triggerScenario($eventType, $payload, $connectionId);
    }

    /**
     * Get a Guzzle client instance for webhook push.
     *
     * @return \GuzzleHttp\Client
     */
    protected function getWebhookClient(): \GuzzleHttp\Client
    {
        return $this->client ?? new \GuzzleHttp\Client();
    }

    /**
     * Get external provider status.
     *
     * @return array|null
     */
    public function getExternalStatus(): ?array
    {
        try {
            $client = new \GuzzleHttp\Client(['timeout' => 5]);
            $response = $client->get('https://status.make.com/api/v2/status.json');
            $data = json_decode((string)$response->getBody(), true);
            
            return [
                'status' => $data['status']['indicator'] === 'none' ? 'operational' : 'degraded',
                'description' => $data['status']['description'] ?? 'All systems operational',
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getCapabilities(): array
    {
        return [
            'trigger_scenario',
            'webhook_support'
        ];
    }

    public function getApiVersion(): string
    {
        return 'v1';
    }

    public function getCatalogueMetadata(): array
    {
        $metadata = parent::getCatalogueMetadata();
        $metadata['display_name'] = 'Make (Integromat)';
        $metadata['description'] = 'Trigger visual automation scenarios via webhooks.';
        $metadata['logo_url'] = 'https://www.make.com/en/favicon.ico';
        $metadata['setup_guide_url'] = 'https://www.make.com/en/api-documentation';
        return $metadata;
    }

    public function getDataPermissions(): array
    {
        return [
            'read' => ['scenarios', 'organizations', 'teams', 'connections'],
            'write' => ['scenarios', 'webhooks']
        ];
    }
}

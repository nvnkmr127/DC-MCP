<?php

namespace App\Modules\MCP\Adapters;

use App\Modules\MCP\Models\McpConnection;
use App\Modules\MCP\DataObjects\SyncResult;
use App\Modules\MCP\DataObjects\WebhookResult;
use App\Modules\MCP\DataObjects\ConnectionTestResult;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\ProjectManagement\Models\Task;
use App\Modules\Auth\Models\User;
use App\Modules\DailyBriefing\Models\DailyBriefing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ZohoCliqAdapter extends BaseAdapter
{
    /**
     * Get the provider identifier.
     *
     * @return string
     */
    protected function getProviderName(): string
    {
        return 'zoho_cliq';
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
        $token = $credentials['access_token'] ?? '';
        if (!empty($token) && !str_starts_with($token, '1000.')) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'credentials.access_token' => 'Zoho Cliq token must start with 1000.'
            ]);
        }
    }

    /**
     * Set up the Guzzle client for Zoho Cliq API.
     *
     * @param array $credentials
     * @param McpConnection|null $connection
     * @return void
     */
    protected function setupCliqClient(array $credentials, ?McpConnection $connection = null): void
    {
        $accessToken = $credentials['access_token'] ?? '';
        
        // Refresh token if expired or close to expiring
        if ($connection && $this->isTokenExpired($credentials)) {
            $refreshed = $this->refreshOAuthToken($credentials, $connection);
            if ($refreshed) {
                $credentials = $this->decryptCredentials($connection->credentials ?? []);
                $accessToken = $credentials['access_token'] ?? '';
            }
        }

        $this->setupClient('https://cliq.zoho.com/api/v1/', [
            'Authorization' => 'Zoho-oauthtoken ' . $accessToken,
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * Check if the token is expired.
     */
    protected function isTokenExpired(array $credentials): bool
    {
        if (!isset($credentials['expires_in']) || !isset($credentials['created'])) {
            return true;
        }
        $expiryTime = $credentials['created'] + $credentials['expires_in'];
        return (time() + 60) >= $expiryTime; // Refresh 1 minute before actual expiry
    }

    /**
     * Refresh the OAuth2 access token.
     */
    protected function refreshOAuthToken(array $credentials, McpConnection $connection): bool
    {
        $refreshToken = $credentials['refresh_token'] ?? null;
        if (!$refreshToken) {
            return false;
        }

        try {
            $clientId     = config('services.zoho.client_id');
            $clientSecret = config('services.zoho.client_secret');

            if (!$clientId || !$clientSecret) {
                throw new \RuntimeException('Zoho OAuth credentials are not configured. Set ZOHO_CLIENT_ID and ZOHO_CLIENT_SECRET.');
            }

            $client = new \GuzzleHttp\Client();
            $response = $client->post('https://accounts.zoho.com/oauth/v2/token', [
                'form_params' => [
                    'refresh_token' => $refreshToken,
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'grant_type' => 'refresh_token',
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            if (isset($data['access_token'])) {
                $credentials['access_token'] = $data['access_token'];
                $credentials['created'] = time();
                $credentials['expires_in'] = $data['expires_in'] ?? 3600;

                $connection->credentials = $this->encryptCredentials($credentials);
                $connection->save();

                return true;
            }
        } catch (\Exception $e) {
            // Log error
        }

        return false;
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
            $this->setupCliqClient($credentials);
            $response = $this->client->get('users/me');
            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Pull data from Zoho Cliq channels to local database.
     *
     * @param string $connectionId
     * @return SyncResult
     */
    public function sync(string $connectionId): SyncResult
    {
        $startTime = microtime(true);
        $connection = McpConnection::findOrFail($connectionId);
        $credentials = $this->decryptCredentials($connection->credentials ?? []);
        
        try {
            $this->setupCliqClient($credentials, $connection);

            $settings = $connection->settings ?? [];
            $channelsToWatch = $settings['channels_to_watch'] ?? [];
            $defaultProjectId = $settings['default_project_id'] ?? null;
            $projectMapping = $settings['project_mapping'] ?? [];

            $processedCount = 0;
            $failedCount = 0;

            foreach ($channelsToWatch as $channel) {
                try {
                    // API Call: GET channels/{channelName}/messages
                    $response = $this->client->get("channels/{$channel}/messages", [
                        'query' => [
                            'limit' => 20
                        ]
                    ]);

                    $body = json_decode($response->getBody()->getContents(), true);
                    $messages = $body['data'] ?? [];

                    foreach ($messages as $message) {
                        $messageId = $message['id'] ?? null;
                        $content = $message['content'] ?? '';
                        $sender = $message['sender'] ?? [];
                        $senderEmail = $sender['email'] ?? null;

                        if (!$messageId) {
                            continue;
                        }

                        // Check if message mentions @digicloudify or contains [TASK]
                        $hasTaskTrigger = str_contains($content, '[TASK]') || str_contains($content, '@digicloudify');
                        
                        if ($hasTaskTrigger) {
                            // Determine project mapping
                            $projectId = $projectMapping[$channel] ?? $defaultProjectId;

                            if ($projectId) {
                                // Extract task text: remove [TASK] and @digicloudify tags
                                $cleanContent = trim(str_replace(['[TASK]', '@digicloudify'], '', $content));
                                $firstLine = explode("\n", $cleanContent)[0];
                                $title = Str::limit($firstLine, 80, '...');

                                // Find user matching sender email
                                $assignee = null;
                                if ($senderEmail) {
                                    $assignee = User::where('email', $senderEmail)->first();
                                }

                                // Prevent duplicates by checking external message ID in task meta
                                $existingTask = Task::where('project_id', $projectId)
                                    ->where('meta->zoho_message_id', $messageId)
                                    ->first();

                                if (!$existingTask) {
                                    $task = new Task();
                                    $task->organization_id = $connection->organization_id;
                                    $task->project_id = $projectId;
                                    $task->title = $title ?: 'Zoho Task';
                                    $task->description = "Created from Zoho Cliq channel message:\n\n" . $content;
                                    $task->type = 'other';
                                    $task->status = 'todo';
                                    $task->priority = 'medium';
                                    $task->assigned_to = $assignee?->id;
                                    $task->role_required = $assignee ? ($assignee->role?->slug ?? 'developer') : 'developer';
                                    $task->due_date = now()->addDays(2)->toDateString();
                                    $task->meta = ['zoho_message_id' => $messageId];
                                    $task->save();

                                    $processedCount++;
                                }
                            }
                        }

                        // Log inbound sync log
                        $this->logSync(
                            $connectionId,
                            'inbound',
                            'zoho_message',
                            $messageId,
                            'success',
                            1,
                            0,
                            $message
                        );
                    }
                } catch (\Exception $e) {
                    $failedCount++;
                    $this->logSync(
                        $connectionId,
                        'inbound',
                        'zoho_channel_messages',
                        $channel,
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
     * Push local changes to Zoho Cliq.
     *
     * @param string $connectionId
     * @param array $data
     * @return SyncResult
     */
    public function push(string $connectionId, array $data): SyncResult
    {
        $connection = McpConnection::findOrFail($connectionId);
        $entityType = $data['entity_type'] ?? null;
        $entityId = $data['entity_id'] ?? null;
        $alertType = $data['alert_type'] ?? '';
        $messageText = $data['message'] ?? '';
        $channelName = $data['channel'] ?? '';

        $credentials = $this->decryptCredentials($connection->credentials ?? []);
        $this->setupCliqClient($credentials, $connection);

        try {
            $success = false;

            if ($entityType === 'briefing') {
                $briefing = DailyBriefing::findOrFail($entityId);
                $user = User::findOrFail($data['user_id']);
                $success = $this->sendDailyBriefingWithConnection($connection, $user, $briefing);
            } elseif ($entityType === 'task') {
                $task = Task::findOrFail($entityId);
                $success = $this->sendTaskAlertWithConnection($connection, $task, $alertType);
            } elseif ($entityType === 'project') {
                $project = Project::findOrFail($entityId);
                $success = $this->sendProjectAlertWithConnection($connection, $project, $messageText);
            } elseif ($entityType === 'channel_message') {
                $success = $this->sendChannelMessageWithConnection($connection, $channelName, $messageText, $data['card'] ?? []);
            }

            return $success ? SyncResult::success(1) : SyncResult::failure('Zoho Cliq post failed');

        } catch (\Exception $e) {
            return SyncResult::failure($e->getMessage());
        }
    }

    /**
     * Send daily briefing via Zoho Cliq channel card.
     */
    public function sendDailyBriefing(User $user, DailyBriefing $briefing): bool
    {
        $connection = McpConnection::where('organization_id', $user->organization_id)
            ->where('provider', 'zoho_cliq')
            ->where('status', 'active')
            ->first();

        if (!$connection) {
            return false;
        }

        return $this->sendDailyBriefingWithConnection($connection, $user, $briefing);
    }

    protected function sendDailyBriefingWithConnection(McpConnection $connection, User $user, DailyBriefing $briefing): bool
    {
        $settings = $connection->settings ?? [];
        $briefingChannel = $settings['briefing_channel'] ?? null;

        if (!$briefingChannel) {
            return false;
        }

        $dateStr = $briefing->date ? $briefing->date->format('Y-m-d') : now()->format('Y-m-d');
        $rawText = $briefing->digest_text ?? 'Daily Briefing digest';

        // Extract key sections or list summaries from briefing digest
        $briefingCard = [
            'text' => "📊 Daily Briefing — {$dateStr}",
            'card' => [
                'title' => "📊 Daily Briefing — {$dateStr}",
                'theme' => 'modern-light',
                'sections' => [
                    [
                        'title' => 'Summary',
                        'description' => Str::limit($rawText, 500, '...')
                    ]
                ],
                'actions' => [
                    [
                        'label' => 'View Full Briefing',
                        'type' => 'open_url',
                        'url' => url("/briefings/{$briefing->id}")
                    ],
                    [
                        'label' => 'Open Dashboard',
                        'type' => 'open_url',
                        'url' => url('/dashboard')
                    ]
                ]
            ]
        ];

        try {
            $this->setupCliqClient($this->decryptCredentials($connection->credentials ?? []), $connection);
            
            $response = $this->client->post("channels/{$briefingChannel}/messages", [
                'json' => $briefingCard
            ]);

            // Log outbound sync
            $this->logSync(
                $connection->id,
                'outbound',
                'daily_briefing',
                $briefing->id,
                'success',
                1,
                0,
                $briefingCard
            );

            return $response->getStatusCode() === 200 || $response->getStatusCode() === 201;
        } catch (\Exception $e) {
            $this->logSync(
                $connection->id,
                'outbound',
                'daily_briefing',
                $briefing->id,
                'failed',
                0,
                1,
                $briefingCard,
                $e->getMessage()
            );
            return false;
        }
    }

    /**
     * Send Task Alert to assigned user via Cliq DM.
     */
    public function sendTaskAlert(Task $task, string $alertType): bool
    {
        $connection = McpConnection::where('organization_id', $task->organization_id)
            ->where('provider', 'zoho_cliq')
            ->where('status', 'active')
            ->first();

        if (!$connection) {
            return false;
        }

        return $this->sendTaskAlertWithConnection($connection, $task, $alertType);
    }

    protected function sendTaskAlertWithConnection(McpConnection $connection, Task $task, string $alertType): bool
    {
        $assignee = $task->assignee;
        if (!$assignee || !$assignee->email) {
            return false;
        }

        $url = url("/tasks/{$task->id}");
        $messageText = "";
        
        switch ($alertType) {
            case 'assigned':
                $messageText = "📋 New task assigned: {$task->title}\nProject: {$task->project?->name}\nDue: {$task->due_date?->format('Y-m-d')}\n[View Task]({$url})";
                break;
            case 'sla_warning':
                $messageText = "⚠️ SLA Warning: Task '{$task->title}' is approaching its SLA deadline!\n[View Task]({$url})";
                break;
            case 'sla_breached':
                $messageText = "🚨 SLA BREACHED: Task '{$task->title}' has breached its SLA!\n[View Task]({$url})";
                break;
            case 'completed':
                $messageText = "✅ Task Completed: '{$task->title}'\n[View Task]({$url})";
                break;
            default:
                $messageText = "Task Update: '{$task->title}' [status: {$task->status}]\n[View Task]({$url})";
        }

        $payload = [
            'text' => $messageText
        ];

        try {
            $this->setupCliqClient($this->decryptCredentials($connection->credentials ?? []), $connection);
            
            // Send direct message to user using their email: POST users/{email}/messages
            $response = $this->client->post("users/{$assignee->email}/messages", [
                'json' => $payload
            ]);

            $this->logSync(
                $connection->id,
                'outbound',
                'task_alert',
                $task->id,
                'success',
                1,
                0,
                $payload
            );

            return $response->getStatusCode() === 200 || $response->getStatusCode() === 201;
        } catch (\Exception $e) {
            $this->logSync(
                $connection->id,
                'outbound',
                'task_alert',
                $task->id,
                'failed',
                0,
                1,
                $payload,
                $e->getMessage()
            );
            return false;
        }
    }

    /**
     * Send Project Alert to alert channel.
     */
    public function sendProjectAlert(Project $project, string $message): bool
    {
        $connection = McpConnection::where('organization_id', $project->organization_id)
            ->where('provider', 'zoho_cliq')
            ->where('status', 'active')
            ->first();

        if (!$connection) {
            return false;
        }

        return $this->sendProjectAlertWithConnection($connection, $project, $message);
    }

    protected function sendProjectAlertWithConnection(McpConnection $connection, Project $project, string $message): bool
    {
        $settings = $connection->settings ?? [];
        $alertChannel = $settings['alert_channel'] ?? null;

        if (!$alertChannel) {
            return false;
        }

        $payload = [
            'text' => "[Project: {$project->name}] {$message}"
        ];

        return $this->sendChannelMessageWithConnection($connection, $alertChannel, $payload['text']);
    }

    /**
     * Send a channel message.
     */
    public function sendChannelMessage(string $channel, string $message, array $card = []): bool
    {
        $connection = McpConnection::where('provider', 'zoho_cliq')
            ->where('status', 'active')
            ->first();

        if (!$connection) {
            return false;
        }

        return $this->sendChannelMessageWithConnection($connection, $channel, $message, $card);
    }

    protected function sendChannelMessageWithConnection(McpConnection $connection, string $channel, string $message, array $card = []): bool
    {
        $payload = [
            'text' => $message
        ];

        if (!empty($card)) {
            $payload['card'] = $card;
        }

        try {
            $this->setupCliqClient($this->decryptCredentials($connection->credentials ?? []), $connection);
            
            $response = $this->client->post("channels/{$channel}/messages", [
                'json' => $payload
            ]);

            return $response->getStatusCode() === 200 || $response->getStatusCode() === 201;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Inbound webhooks.
     *
     * @param Request $request
     * @return WebhookResult
     */
    public function handleWebhook(Request $request): WebhookResult
    {
        $payload = json_decode($request->getContent(), true);
        if (!$payload) {
            return WebhookResult::failed('Empty or invalid webhook payload');
        }

        // Handle Zoho Cliq incoming payload. Typically trigger a sync.
        $connection = McpConnection::where('provider', 'zoho_cliq')
            ->where('status', 'active')
            ->first();

        if ($connection) {
            if (class_exists('App\Modules\MCP\Jobs\SyncMcpProviderJob')) {
                \App\Modules\MCP\Jobs\SyncMcpProviderJob::dispatch($connection);
            } else {
                $this->sync($connection->id);
            }
        }

        return WebhookResult::processed(['message' => 'Zoho Cliq webhook received and sync triggered']);
    }

    /**
     * Test the connection.
     *
     * @param array $credentials
     * @return ConnectionTestResult
     */
    public function testConnection(array $credentials): ConnectionTestResult
    {
        try {
            $this->setupCliqClient($credentials);
            $response = $this->client->get('users/me');
            $data = json_decode($response->getBody()->getContents(), true);

            return ConnectionTestResult::success([
                'provider' => 'zoho_cliq',
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
        return [
            'ZohoCliq.Webhooks.CREATE',
            'ZohoCliq.Channels.READ',
            'ZohoCliq.Channels.CREATE',
            'ZohoCliq.Chats.READ',
            'ZohoCliq.Chats.CREATE',
            'ZohoCliq.Messages.CREATE',
            'ZohoCliq.Messages.READ'
        ];
    }

    public function getCapabilities(): array
    {
        return [
            'read_messages',
            'send_messages',
            'read_channels',
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
        $metadata['display_name'] = 'Zoho Cliq';
        $metadata['description'] = 'Integrate chat, send alerts, and listen to channels.';
        $metadata['logo_url'] = 'https://www.zoho.com/cliq/favicon.ico';
        $metadata['setup_guide_url'] = 'https://www.zoho.com/cliq/help/restapi/v2/';
        return $metadata;
    }

    public function getDataPermissions(): array
    {
        return [
            'read' => ['channels', 'messages', 'users'],
            'write' => ['messages', 'channels']
        ];
    }
}

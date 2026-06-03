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
use Google_Client;
use Google_Service_Gmail;
use Google_Service_Gmail_Message;
use Google_Service_Gmail_ModifyMessageRequest;

class GmailAdapter extends BaseAdapter
{
    /**
     * Get the provider identifier.
     *
     * @return string
     */
    protected function getProviderName(): string
    {
        return 'gmail';
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
            $client = $this->getGoogleClient($credentials);
            return !$client->isAccessTokenExpired();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get Google API Client instance.
     *
     * @param array $credentials
     * @param McpConnection|null $connection
     * @return Google_Client
     */
    protected function getGoogleClient(array $credentials, ?McpConnection $connection = null): Google_Client
    {
        $client = new Google_Client();
        $clientId     = config('services.google.client_id');
        $clientSecret = config('services.google.client_secret');

        if (!$clientId || !$clientSecret) {
            throw new \RuntimeException('Google OAuth credentials are not configured. Set GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET.');
        }

        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);

        $token = [
            'access_token' => $credentials['access_token'] ?? null,
            'refresh_token' => $credentials['refresh_token'] ?? null,
            'expires_in' => $credentials['expires_in'] ?? 3600,
            'created' => $credentials['created'] ?? time(),
        ];

        $client->setAccessToken($token);

        if ($client->isAccessTokenExpired()) {
            $refreshToken = $client->getRefreshToken();
            if ($refreshToken) {
                $newAccessToken = $client->fetchAccessTokenWithRefreshToken($refreshToken);
                if (isset($newAccessToken['access_token'])) {
                    $credentials['access_token'] = $newAccessToken['access_token'];
                    $credentials['created'] = time();
                    $credentials['expires_in'] = $newAccessToken['expires_in'] ?? 3600;

                    if ($connection) {
                        $connection->credentials = $this->encryptCredentials($credentials);
                        $connection->save();
                    }

                    $client->setAccessToken($newAccessToken);
                }
            }
        }

        return $client;
    }

    /**
     * Pull data from Gmail to local database.
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
            $client = $this->getGoogleClient($credentials, $connection);
            $service = $this->getGmailService($client);

            $settings = $connection->settings ?? [];
            $labelsToWatch = $settings['labels_to_watch'] ?? [];
            $autoCreateTasks = $settings['auto_create_tasks'] ?? false;
            $projectId = $settings['linked_project_id'] ?? null;

            $processedCount = 0;
            $failedCount = 0;

            // Search query for unread messages from watched labels in the last 24h
            $afterTimestamp = time() - 86400;
            $q = 'is:unread after:' . $afterTimestamp;

            if (!empty($labelsToWatch)) {
                $labelQueries = array_map(fn($label) => 'label:"' . $label . '"', $labelsToWatch);
                $q .= ' (' . implode(' OR ', $labelQueries) . ')';
            }

            $messagesResponse = $service->users_messages->listUsersMessages('me', ['q' => $q]);
            $messages = $messagesResponse->getMessages();

            if ($messages) {
                foreach ($messages as $msgSummary) {
                    try {
                        $messageId = $msgSummary->getId();
                        $message = $service->users_messages->get('me', $messageId, ['format' => 'full']);
                        
                        $payload = $message->getPayload();
                        $headers = $payload->getHeaders();
                        
                        $subject = '';
                        $sender = '';
                        $dateStr = '';
                        
                        foreach ($headers as $header) {
                            $name = strtolower($header->getName());
                            if ($name === 'subject') {
                                $subject = $header->getValue();
                            } elseif ($name === 'from') {
                                $sender = $header->getValue();
                            } elseif ($name === 'date') {
                                $dateStr = $header->getValue();
                            }
                        }

                        $threadId = $message->getThreadId();
                        $snippet = $message->getSnippet();

                        // Log sync attempt
                        $this->logSync(
                            $connectionId,
                            'inbound',
                            'gmail_message',
                            $messageId,
                            'success',
                            1,
                            0,
                            [
                                'id' => $messageId,
                                'thread_id' => $threadId,
                                'subject' => $subject,
                                'from' => $sender,
                                'snippet' => $snippet,
                                'date' => $dateStr
                            ]
                        );

                        if ($autoCreateTasks && $projectId) {
                            // Handle threading - search for existing task linked to thread_id
                            $task = Task::where('project_id', $projectId)
                                ->where('meta->gmail_thread_id', $threadId)
                                ->first();

                            if (!$task) {
                                $task = new Task();
                                $task->organization_id = $connection->organization_id;
                                $task->project_id = $projectId;
                                $task->title = $subject ?: 'Review Email';
                                $task->description = "Email from: {$sender}\n\nSnippet: {$snippet}";
                                $task->type = 'review';
                                $task->status = 'backlog';
                                $task->priority = 'medium';
                                $task->role_required = 'project_manager';
                                $task->due_date = now()->addDay()->toDateString();
                                $task->meta = ['gmail_thread_id' => $threadId];
                                $task->save();
                            }
                        }

                        // Mark the message as read
                        $mods = new Google_Service_Gmail_ModifyMessageRequest();
                        $mods->setRemoveLabelIds(['UNREAD']);
                        $service->users_messages->modify('me', $messageId, $mods);

                        $processedCount++;
                    } catch (\Exception $e) {
                        $failedCount++;
                        $this->logSync(
                            $connectionId,
                            'inbound',
                            'gmail_message',
                            $msgSummary->getId(),
                            'failed',
                            0,
                            1,
                            null,
                            $e->getMessage()
                        );
                    }
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
            $connection->markError($e->getMessage());
            return SyncResult::failure($e->getMessage(), 0, 1, ['duration_ms' => $durationMs]);
        }
    }

    /**
     * Push local changes to the external system (send email).
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
        $userId = $data['user_id'] ?? null;

        if ($entityType === 'briefing') {
            $user = User::findOrFail($userId);
            $briefing = DailyBriefing::findOrFail($entityId);

            $sent = $this->sendBriefingEmailWithConnection($connection, $user, $briefing);

            return $sent ? SyncResult::success(1) : SyncResult::failure('Failed to send email briefing');
        }

        return SyncResult::success(0, ['reason' => 'Unsupported entity type']);
    }

    /**
     * Send Daily Briefing Email using user's active Gmail connection.
     *
     * @param User $user
     * @param DailyBriefing $briefing
     * @return bool
     */
    public function sendBriefingEmail(User $user, DailyBriefing $briefing): bool
    {
        $connection = McpConnection::where('organization_id', $user->organization_id)
            ->where('provider', 'gmail')
            ->where('status', 'active')
            ->first();

        if (!$connection) {
            return false;
        }

        return $this->sendBriefingEmailWithConnection($connection, $user, $briefing);
    }

    /**
     * Send daily briefing via specific Gmail connection.
     */
    protected function sendBriefingEmailWithConnection(McpConnection $connection, User $user, DailyBriefing $briefing): bool
    {
        $credentials = $this->decryptCredentials($connection->credentials ?? []);
        
        try {
            $client = $this->getGoogleClient($credentials, $connection);
            $service = $this->getGmailService($client);

            $subject = 'Daily Briefing — ' . ($briefing->date ? $briefing->date->format('Y-m-d') : now()->format('Y-m-d'));
            $body = $briefing->digest_html ?? $briefing->digest_text ?? 'No briefing content available.';

            // Send raw email via Gmail API
            $rawMessageString = "To: {$user->email}\r\n" .
                                "Subject: {$subject}\r\n" .
                                "Content-Type: text/html; charset=utf-8\r\n\r\n" .
                                $body;

            $mime = rtrim(strtr(base64_encode($rawMessageString), '+/', '-_'), '=');
            
            $msg = new Google_Service_Gmail_Message();
            $msg->setRaw($mime);
            
            $service->users_messages->send('me', $msg);

            // Log outbound sync log
            $this->logSync(
                $connection->id,
                'outbound',
                'daily_briefing',
                $briefing->id,
                'success',
                1,
                0,
                ['to' => $user->email, 'subject' => $subject]
            );

            // Track send status in notifications_log
            DB::table('notifications_log')->insert([
                'id' => (string) Str::uuid(),
                'organization_id' => $connection->organization_id,
                'user_id' => $user->id,
                'type' => 'briefing_ready',
                'channel' => 'email',
                'title' => $subject,
                'body' => $body,
                'data' => json_encode(['daily_briefing_id' => $briefing->id]),
                'status' => 'sent',
                'sent_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logSync(
                $connection->id,
                'outbound',
                'daily_briefing',
                $briefing->id,
                'failed',
                0,
                1,
                null,
                $e->getMessage()
            );
            return false;
        }
    }

    /**
     * Get Client Emails Summary for Daily Briefing.
     *
     * @param int $hours
     * @return array
     */
    public function getClientEmailsSummary(int $hours = 24): array
    {
        $user = auth()->user();
        if (!$user) {
            return [];
        }

        $connection = McpConnection::where('organization_id', $user->organization_id)
            ->where('provider', 'gmail')
            ->where('status', 'active')
            ->first();

        if (!$connection) {
            return [];
        }

        $credentials = $this->decryptCredentials($connection->credentials ?? []);
        
        try {
            $client = $this->getGoogleClient($credentials, $connection);
            $service = $this->getGmailService($client);

            $settings = $connection->settings ?? [];
            $labelsToWatch = $settings['labels_to_watch'] ?? [];

            $afterTimestamp = time() - ($hours * 3600);
            $q = 'after:' . $afterTimestamp;

            if (!empty($labelsToWatch)) {
                $labelQueries = array_map(fn($label) => 'label:"' . $label . '"', $labelsToWatch);
                $q .= ' (' . implode(' OR ', $labelQueries) . ')';
            }

            $messagesResponse = $service->users_messages->listUsersMessages('me', ['q' => $q]);
            $messages = $messagesResponse->getMessages();

            $summary = [];

            if ($messages) {
                foreach ($messages as $msgSummary) {
                    try {
                        $message = $service->users_messages->get('me', $msgSummary->getId(), ['format' => 'full']);
                        $payload = $message->getPayload();
                        $headers = $payload->getHeaders();
                        
                        $subject = '';
                        $sender = '';
                        $dateStr = '';
                        
                        foreach ($headers as $header) {
                            $name = strtolower($header->getName());
                            if ($name === 'subject') {
                                $subject = $header->getValue();
                            } elseif ($name === 'from') {
                                $sender = $header->getValue();
                            } elseif ($name === 'date') {
                                $dateStr = $header->getValue();
                            }
                        }

                        $summary[] = [
                            'from' => $sender,
                            'subject' => $subject,
                            'snippet' => $message->getSnippet(),
                            'received_at' => $dateStr,
                            'thread_id' => $message->getThreadId(),
                            'labels' => $message->getLabelIds() ?? []
                        ];
                    } catch (\Exception $e) {
                        // Skip failed message retrieval, continue with next
                    }
                }
            }

            return $summary;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Handle incoming webhooks from the external service.
     *
     * @param Request $request
     * @return WebhookResult
     */
    public function handleWebhook(Request $request): WebhookResult
    {
        $data = json_decode($request->getContent(), true);
        if (!$data || !isset($data['message']['data'])) {
            return WebhookResult::failed('Invalid Pub/Sub message payload');
        }

        // Pub/Sub payload data is base64 encoded
        $decodedDataStr = base64_decode($data['message']['data']);
        $decodedData = json_decode($decodedDataStr, true);

        if (!$decodedData || !isset($decodedData['emailAddress'])) {
            return WebhookResult::failed('Invalid decoded Pub/Sub payload');
        }

        $emailAddress = $decodedData['emailAddress'];
        $historyId = $decodedData['historyId'] ?? null;

        // Find Gmail connection for this emailAddress
        $connection = McpConnection::where('provider', 'gmail')
            ->where(function($query) use ($emailAddress) {
                $query->where('settings->gmail_email', $emailAddress)
                      ->orWhere('name', 'like', '%' . $emailAddress . '%');
            })
            ->first();

        if (!$connection) {
            // Fallback to first active gmail connection
            $connection = McpConnection::where('provider', 'gmail')
                ->where('status', 'active')
                ->first();
        }

        if (!$connection) {
            return WebhookResult::failed('Gmail connection not found for webhook');
        }

        // Trigger sync job
        if (class_exists('App\Modules\MCP\Jobs\SyncMcpProviderJob')) {
            \App\Modules\MCP\Jobs\SyncMcpProviderJob::dispatch($connection);
        } else {
            $this->sync($connection->id);
        }

        return WebhookResult::processed([
            'message' => 'Gmail webhook processed and sync triggered',
            'email' => $emailAddress,
            'history_id' => $historyId
        ]);
    }

    /**
     * Test the connection to the external API using the provided credentials.
     *
     * @param array $credentials
     * @return ConnectionTestResult
     */
    public function testConnection(array $credentials): ConnectionTestResult
    {
        try {
            $client = $this->getGoogleClient($credentials);
            $service = $this->getGmailService($client);
            $service->users->getProfile('me');

            return ConnectionTestResult::success([
                'provider' => 'gmail',
                'connected_at' => now()->toIso8601String()
            ]);
        } catch (\Exception $e) {
            return ConnectionTestResult::failure($e->getMessage());
        }
    }

    /**
     * Get Google Service Gmail instance.
     *
     * @param Google_Client $client
     * @return Google_Service_Gmail
     */
    protected function getGmailService(Google_Client $client): Google_Service_Gmail
    {
        return new Google_Service_Gmail($client);
    }

    /**
     * Get the available scopes or permissions needed by this adapter.
     *
     * @return array
     */
    public function getAvailableScopes(): array
    {
        return [
            Google_Service_Gmail::GMAIL_MODIFY,
            Google_Service_Gmail::GMAIL_SEND,
        ];
    }
}

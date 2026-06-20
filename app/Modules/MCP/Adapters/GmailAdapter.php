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
     * Pre-save credential format validation.
     *
     * @param array $credentials
     * @return void
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validateCredentialsFormat(array $credentials): void
    {
        $token = $credentials['access_token'] ?? '';
        if (!empty($token) && !str_starts_with($token, 'ya29.')) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'credentials.access_token' => 'Google access token must start with ya29.'
            ]);
        }
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
     * Get the OAuth authorization URL for the provider.
     */
    public function getOAuthUrl(string $redirectUri, array $scopes = [], string $state = '', string $codeVerifier = ''): string
    {
        $client = new Google_Client();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri($redirectUri);
        $client->addScope($scopes ?: $this->getAvailableScopes());
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        
        if (!empty($state)) {
            $client->setState($state);
        }
        
        $url = $client->createAuthUrl();

        if (!empty($codeVerifier)) {
            $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
            $url .= '&code_challenge=' . $codeChallenge . '&code_challenge_method=S256';
        }
        
        return $url;
    }

    /**
     * Exchange OAuth code for credentials.
     *
     * @param string $code
     * @param string $codeVerifier
     * @param string $redirectUri
     * @return array
     * @throws \Exception
     */
    public function exchangeAuthCode(string $code, string $codeVerifier, string $redirectUri): array
    {
        $client = new Google_Client();
        $response = $client->getHttpClient()->post('https://oauth2.googleapis.com/token', [
            'form_params' => [
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'code' => $code,
                'code_verifier' => $codeVerifier,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $redirectUri,
            ]
        ]);

        $data = json_decode((string)$response->getBody(), true);
        
        if (isset($data['error'])) {
            throw new \Exception('OAuth exchange failed: ' . ($data['error_description'] ?? $data['error']));
        }
        
        return [
            'access_token' => $data['access_token'] ?? null,
            'refresh_token' => $data['refresh_token'] ?? null,
            'expires_in' => $data['expires_in'] ?? 3600,
            'created' => time(),
        ];
    }

    /**
     * Revoke the provider credentials if supported.
     */
    public function revokeCredentials(array $credentials): void
    {
        try {
            $client = $this->getGoogleClient($credentials);
            $client->revokeToken();
        } catch (\Exception $e) {
            // Ignore failure to revoke
        }
    }

    /**
     * Test individual scopes for the connection.
     */
    public function testScopes(array $credentials, array $scopes): array
    {
        $results = [];
        try {
            $client = $this->getGoogleClient($credentials);
            $guzzle = $client->getHttpClient();
            $token = $client->getAccessToken();
            $accessToken = is_array($token) ? ($token['access_token'] ?? '') : '';
            
            if (empty($accessToken)) {
                throw new \Exception('No access token');
            }

            $response = $guzzle->get('https://oauth2.googleapis.com/tokeninfo?access_token=' . $accessToken);
            $info = json_decode((string)$response->getBody(), true);
            $grantedScopes = explode(' ', $info['scope'] ?? '');
            
            foreach ($scopes as $scope) {
                $results[$scope] = in_array($scope, $grantedScopes);
            }
        } catch (\Exception $e) {
            foreach ($scopes as $scope) {
                $results[$scope] = false;
            }
        }
        
        return $results;
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
        
        $settings = $credentials['_settings'] ?? ($connection->settings ?? []);
        $authType = $settings['auth_type'] ?? 'oauth_user';
        
        if ($authType === 'service_account') {
            if (empty($credentials['service_account_json'])) {
                throw new \RuntimeException('Service account JSON is missing.');
            }
            $client->setAuthConfig(json_decode($credentials['service_account_json'], true));
            $client->addScope($this->getAvailableScopes());
            return $client;
        }

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
                        // Avoid overwriting nested environments blindly here.
                        // We'll just encrypt the raw credentials for now as a fallback.
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
    public function sync(string $connectionId, array $options = []): SyncResult
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

            if (!empty($options['full_resync'])) {
                $connection->setCursor('messages_page_token', null);
            }

            // Search query for unread messages from watched labels in the last 24h
            // Search query configuration
            $q = '';
            
            $fromTimestamp = !empty($settings['sync_from_date']) ? strtotime($settings['sync_from_date']) : (time() - 86400);
            $q .= 'after:' . $fromTimestamp;
            
            if (!empty($settings['sync_to_date'])) {
                $toTimestamp = strtotime($settings['sync_to_date']);
                $q .= ' before:' . $toTimestamp;
            } else {
                $q .= ' is:unread'; // Default behavior if no explicit range
            }
            
            if (!empty($settings['sync_filter'])) {
                $q .= ' ' . $settings['sync_filter'];
            }

            if (!empty($labelsToWatch)) {
                $labelQueries = array_map(fn($label) => 'label:"' . $label . '"', $labelsToWatch);
                $q .= ' (' . implode(' OR ', $labelQueries) . ')';
            }
            
            $expectedCount = 0;

            $params = ['q' => $q];
            $pageToken = $connection->getCursor('messages_page_token');
            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }

            do {
                if ($connection->isSyncPaused()) {
                    break;
                }
                $messagesResponse = $service->users_messages->listUsersMessages('me', $params);
                $messages = $messagesResponse->getMessages();
                
                if ($expectedCount === 0) {
                    $expectedCount = $messagesResponse->getResultSizeEstimate() ?? 0;
                }
                
                $lastMessageId = null;

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

                            if (empty($options['dry_run'])) {
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
                                        $task->type = 'review';
                                        $task->status = 'backlog';
                                        $task->priority = 'medium';
                                        $task->role_required = 'project_manager';
                                        $task->due_date = now()->addDay()->toDateString();
                                        
                                        $task->title = $subject ?: 'Review Email';
                                        $task->description = "Email from: {$sender}\n\nSnippet: {$snippet}";
                                        
                                        $meta = ['gmail_thread_id' => $threadId];
                                        if (!empty($options['sync_session_id'])) {
                                            $meta['last_sync_session_id'] = $options['sync_session_id'];
                                        }
                                        $task->meta = $meta;
                                        $task->save();
                                    } else {
                                        // Reconcile and/or update tombstone session
                                        $needsUpdate = false;
                                        if (!empty($options['reconcile'])) {
                                            $task->title = $subject ?: 'Review Email';
                                            $task->description = "Email from: {$sender}\n\nSnippet: {$snippet}";
                                            $needsUpdate = true;
                                        }
                                        
                                        if (!empty($options['sync_session_id'])) {
                                            $meta = $task->meta ?? [];
                                            $meta['last_sync_session_id'] = $options['sync_session_id'];
                                            $task->meta = $meta;
                                            $needsUpdate = true;
                                        }
                                        
                                        if ($needsUpdate) {
                                            $task->save();
                                        }
                                    }
                                }

                                // Mark the message as read
                                $mods = new Google_Service_Gmail_ModifyMessageRequest();
                                $mods->setRemoveLabelIds(['UNREAD']);
                                $service->users_messages->modify('me', $messageId, $mods);
                            }

                            $processedCount++;
                            $lastMessageId = $messageId;
                        } catch (\Exception $e) {
                            $failedCount++;
                            if (empty($options['dry_run'])) {
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
                }

                $nextPageToken = $messagesResponse->getNextPageToken();
                if ($nextPageToken && empty($options['dry_run'])) {
                    $connection->setCursor('messages_page_token', $nextPageToken);
                    $params['pageToken'] = $nextPageToken;
                } else {
                    if (empty($options['dry_run'])) {
                        $connection->setCursor('messages_page_token', null);
                    }
                    $params['pageToken'] = null;
                }

            } while ($params['pageToken']);
            
            if ($lastMessageId && empty($options['dry_run'])) {
                $connection->setLastSyncedRecordReference($lastMessageId);
            }

            // Tombstone cleanup
            if (!empty($options['sync_session_id']) && empty($options['dry_run']) && $projectId && !$connection->isSyncPaused()) {
                Task::where('project_id', $projectId)
                    ->whereNotNull('meta->gmail_thread_id')
                    ->where(function($q) use ($options) {
                        $q->whereNull('meta->last_sync_session_id')
                          ->orWhere('meta->last_sync_session_id', '!=', $options['sync_session_id']);
                    })
                    ->delete();
            }

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            
            if (empty($options['dry_run'])) {
                $connection->markSynced();
            }

            return SyncResult::success($processedCount, [
                'duration_ms' => $durationMs,
                'failed_count' => $failedCount
            ], $durationMs, 0, $expectedCount);

        } catch (\Exception $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $connection->handleException($e);
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
    public function push(string $connectionId, array $data, array $options = []): SyncResult
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
            $settings = $credentials['_settings'] ?? [];
            $authType = $settings['auth_type'] ?? 'oauth_user';

            if ($authType === 'service_account') {
                $service = $this->getGmailService($client);
                $service->users->getProfile('me');

                return ConnectionTestResult::success([
                    'provider' => 'gmail',
                    'connected_at' => now()->toIso8601String(),
                ]);
            }

            $guzzle = $client->getHttpClient();
            $token = $client->getAccessToken();
            $accessToken = is_array($token) ? ($token['access_token'] ?? '') : '';

            if (empty($accessToken)) {
                throw new \Exception('No access token available for introspection.');
            }

            // Introspect the token instead of calling the Gmail API
            $response = $guzzle->get('https://oauth2.googleapis.com/tokeninfo?access_token=' . $accessToken);
            $info = json_decode((string)$response->getBody(), true);

            if (isset($info['error'])) {
                throw new \Exception($info['error_description'] ?? 'Token introspection failed.');
            }

            return ConnectionTestResult::success([
                'provider' => 'gmail',
                'connected_at' => now()->toIso8601String(),
                'expires_in' => $info['expires_in'] ?? null,
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

    public function getCapabilities(): array
    {
        return [
            'read_emails',
            'send_emails',
            'modify_labels',
            'watch_push_notifications'
        ];
    }

    public function getApiVersion(): string
    {
        return 'v1';
    }

    public function getCatalogueMetadata(): array
    {
        $metadata = parent::getCatalogueMetadata();
        $metadata['display_name'] = 'Gmail';
        $metadata['description'] = 'Read, send, and manage your Gmail messages and threads.';
        $metadata['logo_url'] = 'https://upload.wikimedia.org/wikipedia/commons/7/7e/Gmail_icon_%282020%29.svg';
        $metadata['setup_guide_url'] = 'https://developers.google.com/gmail/api/guides';
        return $metadata;
    }

    public function getDataPermissions(): array
    {
        return [
            'read' => ['emails', 'labels', 'threads', 'profile'],
            'write' => ['emails', 'labels', 'drafts']
        ];
    }
}

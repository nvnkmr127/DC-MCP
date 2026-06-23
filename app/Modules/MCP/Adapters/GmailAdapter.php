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
    public function validateCredentialsFormat(#[\SensitiveParameter] array $credentials): void
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
    public function authenticate(#[\SensitiveParameter] array $credentials): bool
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
        $response = $client->getHttpClient()->request('POST', 'https://oauth2.googleapis.com/token', [
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
    public function revokeCredentials(#[\SensitiveParameter] array $credentials): void
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
    public function testScopes(#[\SensitiveParameter] array $credentials, array $scopes): array
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

            $response = $guzzle->request('GET', 'https://oauth2.googleapis.com/tokeninfo?access_token=' . $accessToken);
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
    protected function getGoogleClient(#[\SensitiveParameter] array $credentials, ?McpConnection $connection = null): Google_Client
    {
        $client = new Google_Client();
        
        $guzzleClient = new \GuzzleHttp\Client([
            'handler' => $this->getGuzzleHandlerStack(),
        ]);
        $client->setHttpClient($guzzleClient);
        
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
            $schemaDriftWarnings = [];

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
                    $this->client->setUseBatch(true);
                    $batch = $service->createBatch();
                    foreach ($messages as $msgSummary) {
                        $req = $service->users_messages->get('me', $msgSummary->getId(), ['format' => 'full']);
                        $batch->add($req, $msgSummary->getId());
                    }
                    
                    $batchResults = $batch->execute();
                    $this->client->setUseBatch(false);

                    $threadIds = [];
                    foreach ($batchResults as $messageId => $message) {
                        if ($message instanceof \Google_Service_Exception) continue;
                        /** @var \Google_Service_Gmail_Message $message */
                        if (!empty($message->getThreadId())) {
                            $threadIds[] = $message->getThreadId();
                        }
                    }

                    $existingTasks = collect();
                    if ($autoCreateTasks && $projectId && !empty($threadIds)) {
                        $existingTasks = Task::where('project_id', $projectId)
                            ->whereIn('meta->gmail_thread_id', $threadIds)
                            ->get()
                            ->keyBy(fn($t) => $t->meta['gmail_thread_id'] ?? '');
                    }

                    $tasksToUpsert = [];

                    foreach ($batchResults as $messageId => $message) {
                        if ($message instanceof \Google_Service_Exception) {
                            $failedCount++;
                            continue;
                        }
                        
                        /** @var \Google_Service_Gmail_Message $message */
                        try {
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
                                    $task = clone ($existingTasks->get($threadId) ?? new Task());
                                    $isNewTask = !$task->exists;

                                    if ($isNewTask) {
                                        $task->id = (string) Str::uuid();
                                        $task->organization_id = $connection->organization_id;
                                        $task->project_id = $projectId;
                                        $task->type = 'review';
                                        $task->status = 'backlog';
                                        $task->priority = 'medium';
                                        $task->role_required = 'project_manager';
                                        $task->role_required = 'project_manager';
                                        $task->due_date = now()->addDay()->toDateString();
                                        
                                        $mappings = $settings['field_mappings'] ?? [];
                                        $getMappedValue = function($key, $default) use ($mappings, $subject, $snippet, $sender, $dateStr, &$schemaDriftWarnings, $messageId) {
                                            if (empty($mappings[$key])) {
                                                return $default;
                                            }
                                            $val = $mappings[$key];
                                            if (str_contains($val, '{')) {
                                                $val = str_ireplace('{subject}', $subject, $val);
                                                $val = str_ireplace('{snippet}', $snippet, $val);
                                                $val = str_ireplace('{sender}', $sender, $val);
                                                $val = str_ireplace('{date}', $dateStr, $val);
                                                if (trim($val) === '') {
                                                    $schemaDriftWarnings[] = "Mapped field '$key' resulted in empty string for email $messageId (missing required values)";
                                                }
                                                return $val;
                                            }
                                            // Fallback if they just typed 'subject' instead of '{subject}'
                                            $res = match(strtolower($val)) {
                                                'subject' => $subject,
                                                'snippet' => $snippet,
                                                'sender' => $sender,
                                                'date' => $dateStr,
                                                default => $val
                                            };
                                            if (trim($res) === '') {
                                                $schemaDriftWarnings[] = "Mapped field '$key' resulted in empty string for email $messageId (missing required values)";
                                            }
                                            return $res;
                                        };

                                        $task->title = $getMappedValue('title', $subject ?: 'Review Email');
                                        $task->description = $getMappedValue('description', "Email from: {$sender}\n\nSnippet: {$snippet}");
                                        
                                        $meta = ['gmail_thread_id' => $threadId];
                                        if (!empty($options['sync_session_id'])) {
                                            $meta['last_sync_session_id'] = $options['sync_session_id'];
                                        }
                                        $task->meta = $meta;
                                        $task->updated_at = now();
                                        if ($isNewTask) {
                                            $task->created_at = now();
                                        }
                                        $tasksToUpsert[] = $task->getAttributes();
                                    } else {
                                        // Reconcile and/or update tombstone session
                                        $needsUpdate = false;
                                        if (!empty($options['reconcile'])) {
                                            $task->title = $getMappedValue('title', $subject ?: 'Review Email');
                                            $task->description = $getMappedValue('description', "Email from: {$sender}\n\nSnippet: {$snippet}");
                                            $needsUpdate = true;
                                        }
                                        
                                        if (!empty($options['sync_session_id'])) {
                                            $meta = $task->meta ?? [];
                                            $meta['last_sync_session_id'] = $options['sync_session_id'];
                                            $task->meta = $meta;
                                            $needsUpdate = true;
                                        }
                                        
                                        if ($needsUpdate) {
                                            $task->updated_at = now();
                                            $tasksToUpsert[] = $task->getAttributes();
                                        }
                                    }
                                }

                                // Mark the message as read
                                $mods = new Google_Service_Gmail_ModifyMessageRequest();
                                $mods->setRemoveLabelIds(['UNREAD']);
                                $service->users_messages->modify('me', $messageId, $mods);
                            }

                            $processedCount++;
                            $this->reportSyncProgress($connection, $processedCount, max($expectedCount, $processedCount));
                            $lastMessageId = $messageId;
                        } catch (\Exception $e) {
                            $failedCount++;
                                $this->logSync(
                                    $connectionId,
                                    'inbound',
                                    'gmail_message',
                                    $messageId,
                                    'failed',
                                    0,
                                    1,
                                    null,
                                    $e->getMessage()
                                );
                            }
                        }


                    if (!empty($tasksToUpsert)) {
                        Task::upsert($tasksToUpsert, ['id'], ['title', 'description', 'due_date', 'priority', 'status', 'meta', 'updated_at']);
                    }
                }

                $nextPageToken = $messagesResponse->getNextPageToken();
                if ($nextPageToken && empty($options['dry_run'])) {
                    $connection->setCursor('messages_page_token', $nextPageToken);
                    $params['pageToken'] = $nextPageToken;

                    if ($this->shouldYieldExecution($startTime)) {
                        $hasMore = true;
                        break;
                    }
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

            $schemaDriftWarnings = array_unique($schemaDriftWarnings);

            return SyncResult::success($processedCount, [
                'has_more' => !empty($params['pageToken']),
                'next_cursor' => $params['pageToken'] ?? null,
                'schema_drift_warnings' => array_slice($schemaDriftWarnings, 0, 50)
            ], $durationMs, 0, $expectedCount);

        } catch (\Exception $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $connection->handleException($e);
            return SyncResult::failure($e->getMessage(), 0, 1, ['duration_ms' => $durationMs]);
        }
    }

    public function previewMapping(\App\Modules\MCP\Models\McpConnection $connection, #[\SensitiveParameter] array $credentials, array $proposedMappings): array
    {
        $decrypted = $this->decryptCredentials($credentials);
        $client = $this->getGoogleClient($decrypted, $connection);
        $service = $this->getGmailService($client);

        try {
            $messagesResponse = $service->users_messages->listUsersMessages('me', ['maxResults' => 1]);
            $messages = $messagesResponse->getMessages();

            if (empty($messages)) {
                return ['error' => 'No emails found to preview.'];
            }

            $messageId = $messages[0]->getId();
            $msg = $service->users_messages->get('me', $messageId, ['format' => 'full']);
            $payload = $msg->getPayload();
            $headers = $payload->getHeaders();

            $subject = '';
            $sender = '';
            $dateStr = '';

            foreach ($headers as $header) {
                if ($header->getName() === 'Subject') {
                    $subject = $header->getValue();
                }
                if ($header->getName() === 'From') {
                    $sender = $header->getValue();
                }
                if ($header->getName() === 'Date') {
                    $dateStr = $header->getValue();
                }
            }

            $snippet = $msg->getSnippet();
            $schemaDriftWarnings = [];
            $mappings = $proposedMappings;

            $getMappedValue = function($key, $default) use ($mappings, $subject, $snippet, $sender, $dateStr, &$schemaDriftWarnings, $messageId) {
                if (empty($mappings[$key])) {
                    return $default;
                }
                $val = $mappings[$key];
                if (str_contains($val, '{')) {
                    $val = str_ireplace('{subject}', $subject, $val);
                    $val = str_ireplace('{snippet}', $snippet, $val);
                    $val = str_ireplace('{sender}', $sender, $val);
                    $val = str_ireplace('{date}', $dateStr, $val);
                    if (trim($val) === '') {
                        $schemaDriftWarnings[] = "Mapped field '$key' resulted in empty string for email $messageId (missing required values)";
                    }
                    return $val;
                }
                
                $res = match(strtolower($val)) {
                    'subject' => $subject,
                    'snippet' => $snippet,
                    'sender' => $sender,
                    'date' => $dateStr,
                    default => $val
                };
                if (trim($res) === '') {
                    $schemaDriftWarnings[] = "Mapped field '$key' resulted in empty string for email $messageId (missing required values)";
                }
                return $res;
            };

            $title = $getMappedValue('title', $subject ?: 'Review Email');
            $description = $getMappedValue('description', $snippet);
            
            $mappedData = [
                'title' => $title,
                'description' => $description,
                'type' => 'review',
                'status' => 'backlog',
                'priority' => 'medium',
                'due_date' => now()->addDay()->toDateString(),
            ];

            return [
                'raw' => [
                    'subject' => $subject,
                    'snippet' => $snippet,
                    'sender' => $sender,
                    'date' => $dateStr
                ],
                'mapped' => $mappedData,
                'warnings' => array_unique($schemaDriftWarnings)
            ];

        } catch (\Exception $e) {
            return ['error' => 'Failed to fetch preview from Gmail: ' . $e->getMessage()];
        }
    }

    /**
     * Push local changes to the external system (send email).
     *
     * @param string $connectionId
     * @param array $data
     * @return SyncResult
     */
    protected function performPush(string $connectionId, array $data, array $options = []): SyncResult
    {
        $connection = McpConnection::findOrFail($connectionId);
        $settings = $connection->settings ?? [];
        $enabledActions = $settings['enabled_outbound_actions'] ?? [];

        $entityType = $data['entity_type'] ?? null;
        $entityId = $data['entity_id'] ?? null;
        $userId = $data['user_id'] ?? null;

        try {
            if ($entityType === 'briefing') {
                $this->validateOutboundPayload('send_briefing', $data);

                if (isset($enabledActions['send_briefing']) && $enabledActions['send_briefing'] === false) {
                    return SyncResult::failure('Action send_briefing is disabled for this connection.');
                }

                $user = User::findOrFail($userId);
                $briefing = DailyBriefing::findOrFail($entityId);

                $sent = $this->sendBriefingEmailWithConnection($connection, $user, $briefing);

                return $sent ? SyncResult::success(1) : SyncResult::failure('Failed to send email briefing');
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errors = collect($e->errors())->flatten()->implode(' ');
            return SyncResult::failure("Validation failed: " . $errors);
        } catch (\Exception $e) {
            return SyncResult::failure($e->getMessage());
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
    public function testConnection(#[\SensitiveParameter] array $credentials): ConnectionTestResult
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
            $response = $guzzle->request('GET', 'https://oauth2.googleapis.com/tokeninfo?access_token=' . $accessToken);
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
            'read' => ['emails', 'labels', 'threads'],
            'write' => ['emails', 'drafts', 'labels']
        ];
    }

    public function getOutboundActions(): array
    {
        return [
            [
                'id' => 'send_briefing',
                'name' => 'Send Daily Briefing',
                'description' => 'Send the daily briefing digest to users via email.',
                'entity_type' => 'briefing',
                'schema' => [
                    'rules' => [
                        'entity_id' => 'required|integer',
                        'user_id' => 'required|integer',
                    ]
                ]
            ]
        ];
    }

    public function previewOutboundAction(string $connectionId, string $actionId, array $data): array
    {
        $this->validateOutboundPayload($actionId, $data);

        if ($actionId === 'send_briefing') {
            $user = User::findOrFail($data['user_id']);
            $briefing = DailyBriefing::findOrFail($data['entity_id']);

            $view = view('emails.briefing', ['briefing' => $briefing])->render();
            
            return [
                'to' => $user->email,
                'subject' => "Daily Briefing: {$briefing->title}",
                'html_body' => $view,
            ];
        }

        throw new \Exception("Unsupported action [{$actionId}] for preview.");
    }
}


<?php

namespace App\Modules\MCP\Adapters;

use App\Modules\MCP\Models\McpConnection;
use App\Modules\MCP\DataObjects\SyncResult;
use App\Modules\MCP\DataObjects\WebhookResult;
use App\Modules\MCP\DataObjects\ConnectionTestResult;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\ProjectManagement\Models\Milestone;
use App\Modules\ProjectManagement\Models\Task;
use Illuminate\Http\Request;
use Google_Client;
use Google_Service_Calendar;
use Google_Service_Calendar_Event;

class GoogleCalendarAdapter extends BaseAdapter
{
    /**
     * Get the provider identifier.
     *
     * @return string
     */
    protected function getProviderName(): string
    {
        return 'google_calendar';
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
                        // Note: In a fully nested env setup, token refresh inside adapters needs more care,
                        // but it works fine for single environments or top-level.
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
     * Get today's events from Google Calendar.
     *
     * @param McpConnection $connection
     * @return array
     */
    public function getTodayEvents(McpConnection $connection): array
    {
        $credentials = $this->decryptCredentials($connection->credentials ?? []);
        try {
            $client = $this->getGoogleClient($credentials, $connection);
            $service = $this->getCalendarService($client);
            
            $optParams = [
                'timeMin' => now()->startOfDay()->toRfc3339String(),
                'timeMax' => now()->endOfDay()->toRfc3339String(),
                'singleEvents' => true,
                'orderBy' => 'startTime',
            ];
            
            $events = $service->events->listEvents('primary', $optParams);
            $results = [];
            foreach ($events->getItems() as $event) {
                $results[] = [
                    'id' => $event->getId(),
                    'summary' => $event->getSummary() ?? '',
                    'description' => $event->getDescription() ?? '',
                    'start' => $event->getStart()->getDateTime() ?: $event->getStart()->getDate(),
                    'end' => $event->getEnd()->getDateTime() ?: $event->getEnd()->getDate(),
                ];
            }
            return $results;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Pull data from Google Calendar to local database.
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
            $service = $this->getCalendarService($client);

            $settings = $connection->settings ?? [];
            $calendarIds = $settings['calendar_ids'] ?? ['primary'];
            $projectMapping = $settings['project_mapping'] ?? [];

            $processedCount = 0;
            $failedCount = 0;

            $optParams = [
                'timeMin' => now()->toRfc3339String(),
                'timeMax' => now()->addDays(30)->toRfc3339String(),
                'singleEvents' => true,
                'orderBy' => 'startTime',
            ];

            foreach ($calendarIds as $calendarId) {
                $projectId = $projectMapping[$calendarId] ?? null;
                if (!$projectId) {
                    continue;
                }

                $project = Project::find($projectId);
                if (!$project) {
                    continue;
                }

                try {
                    $events = $service->events->listEvents($calendarId, $optParams);
                    
                    foreach ($events->getItems() as $event) {
                        $summary = $event->getSummary() ?? '';
                        $description = $event->getDescription() ?? '';
                        $start = $event->getStart();
                        $eventDateStr = $start->getDateTime() ?: $start->getDate();
                        $dueDate = $eventDateStr ? date('Y-m-d', strtotime($eventDateStr)) : null;

                        // Log sync attempt
                        $this->logSync(
                            $connectionId,
                            'inbound',
                            'calendar_event',
                            $event->getId(),
                            'success',
                            1,
                            0,
                            [
                                'id' => $event->getId(),
                                'summary' => $summary,
                                'start' => $eventDateStr
                            ]
                        );

                        // Match milestone by name
                        $milestone = Milestone::where('project_id', $projectId)
                            ->where('name', $summary)
                            ->first();

                        if ($milestone && $dueDate) {
                            $milestone->due_date = $dueDate;
                            $milestone->save();
                            $processedCount++;
                            continue;
                        }

                        // Create/update task if title contains [TASK]
                        if (str_contains(strtoupper($summary), '[TASK]')) {
                            $cleanTitle = trim(str_ireplace('[TASK]', '', $summary));
                            
                            $task = Task::where('project_id', $projectId)
                                ->where('meta->google_event_id', $event->getId())
                                ->first();

                            if (!$task) {
                                $task = new Task();
                                $task->organization_id = $connection->organization_id;
                                $task->project_id = $projectId;
                                $task->status = 'backlog';
                                $task->priority = 'medium';
                                $task->meta = ['google_event_id' => $event->getId()];
                            }

                            $task->title = $cleanTitle;
                            $task->description = $description;
                            if ($dueDate) {
                                $task->due_date = $dueDate;
                            }
                            $task->save();
                            $processedCount++;
                        }
                    }
                } catch (\Exception $e) {
                    $failedCount++;
                    $this->logSync(
                        $connectionId,
                        'inbound',
                        'calendar_event',
                        null,
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
     * Push local changes to the external system.
     *
     * @param string $connectionId
     * @param array $data
     * @return SyncResult
     */
    protected function performPush(string $connectionId, array $data, array $options = []): SyncResult
    {
        $startTime = microtime(true);
        $connection = McpConnection::findOrFail($connectionId);
        $credentials = $this->decryptCredentials($connection->credentials ?? []);

        try {
            $client = $this->getGoogleClient($credentials, $connection);
            $service = $this->getCalendarService($client);

            $entityType = $data['entity_type'] ?? null;
            $entityId = $data['entity_id'] ?? null;
            $settings = $connection->settings ?? [];
            $calendarId = $settings['push_calendar_id'] ?? 'primary';

            if ($entityType === 'task') {
                $task = Task::with('project', 'assignee')->findOrFail($entityId);
                if (!$task->due_date) {
                    return SyncResult::success(0, ['reason' => 'Task has no due date']);
                }

                $summary = '[DIGI] ' . $task->title . ' — ' . ($task->project->name ?? '');
                $description = "Task URL: " . config('app.url') . "/tasks/" . $task->id . "\n" .
                               "Assigned to: " . ($task->assignee->name ?? 'Unassigned') . "\n" .
                               "Project: " . ($task->project->name ?? '') . "\n\n" .
                               $task->description;

                $googleEventId = $task->meta['google_event_id'] ?? null;
                $eventDate = $task->due_date->format('Y-m-d');

                $eventData = [
                    'summary' => $summary,
                    'description' => $description,
                    'start' => ['date' => $eventDate],
                    'end' => ['date' => date('Y-m-d', strtotime($eventDate . ' + 1 day'))],
                ];

                $event = new Google_Service_Calendar_Event($eventData);

                if ($googleEventId) {
                    try {
                        $googleEvent = $service->events->update($calendarId, $googleEventId, $event);
                    } catch (\Exception $e) {
                        // If event was deleted in calendar, recreate it
                        $googleEvent = $service->events->insert($calendarId, $event);
                    }
                } else {
                    $googleEvent = $service->events->insert($calendarId, $event);
                }

                $task->meta = array_merge($task->meta ?? [], ['google_event_id' => $googleEvent->getId()]);
                $task->save();

                $this->logSync($connectionId, 'outbound', 'task', $task->id, 'success', 1, 0, $eventData);
                return SyncResult::success(1);

            } elseif ($entityType === 'milestone') {
                $milestone = Milestone::with('project')->findOrFail($entityId);
                if (!$milestone->due_date) {
                    return SyncResult::success(0, ['reason' => 'Milestone has no due date']);
                }

                $summary = '[DIGI] ' . $milestone->name . ' — ' . ($milestone->project->name ?? '');
                $description = "Milestone: " . $milestone->description . "\n" .
                               "Project: " . ($milestone->project->name ?? '');

                // Milestones have no meta column, so google_event_id cannot be persisted.
                // Each push creates a new calendar event; deduplication is not supported for milestones.
                $eventDate = $milestone->due_date->format('Y-m-d');
                $eventData = [
                    'summary' => $summary,
                    'description' => $description,
                    'start' => ['date' => $eventDate],
                    'end' => ['date' => date('Y-m-d', strtotime($eventDate . ' + 1 day'))],
                ];
                $event = new Google_Service_Calendar_Event($eventData);
                $service->events->insert($calendarId, $event);

                $this->logSync($connectionId, 'outbound', 'milestone', $milestone->id, 'success', 1, 0, $eventData);
                return SyncResult::success(1);
            }

            return SyncResult::success(0, ['reason' => 'Unsupported entity type']);

        } catch (\Exception $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            return SyncResult::failure($e->getMessage(), 0, 1, ['duration_ms' => $durationMs]);
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
        $channelId = $request->header('X-Goog-Channel-ID');
        $resourceState = $request->header('X-Goog-Resource-State');

        if (!$channelId) {
            return WebhookResult::failed('Missing X-Goog-Channel-ID header');
        }

        $connection = McpConnection::where('settings->channel_id', $channelId)->first();
        if (!$connection) {
            return WebhookResult::failed("Connection not found for channel: {$channelId}");
        }

        if ($resourceState === 'sync') {
            return WebhookResult::processed(['message' => 'Sync confirmation received']);
        }

        // Trigger sync job
        if (class_exists('App\Modules\MCP\Jobs\SyncMcpProviderJob')) {
            \App\Modules\MCP\Jobs\SyncMcpProviderJob::dispatch($connection);
        } else {
            $this->sync($connection->id);
        }

        return WebhookResult::processed(['message' => 'Sync triggered successfully']);
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
                // Service accounts don't have a static access token to introspect initially.
                // We test it by making a lightweight API call instead.
                $service = $this->getCalendarService($client);
                $service->calendarList->listCalendarList(['maxResults' => 1]);
                
                return ConnectionTestResult::success([
                    'provider' => 'google_calendar',
                    'connected_at' => now()->toIso8601String(),
                ]);
            }

            $guzzle = $client->getHttpClient();
            $token = $client->getAccessToken();
            $accessToken = is_array($token) ? ($token['access_token'] ?? '') : '';

            if (empty($accessToken)) {
                throw new \Exception('No access token available for introspection.');
            }

            // Introspect the token instead of calling the Calendar API
            $response = $guzzle->get('https://oauth2.googleapis.com/tokeninfo?access_token=' . $accessToken);
            $info = json_decode((string)$response->getBody(), true);

            if (isset($info['error'])) {
                throw new \Exception($info['error_description'] ?? 'Token introspection failed.');
            }

            return ConnectionTestResult::success([
                'provider' => 'google_calendar',
                'connected_at' => now()->toIso8601String(),
                'expires_in' => $info['expires_in'] ?? null,
            ]);
        } catch (\Exception $e) {
            return ConnectionTestResult::failure($e->getMessage());
        }
    }

    /**
     * Get Google Service Calendar instance.
     *
     * @param Google_Client $client
     * @return Google_Service_Calendar
     */
    protected function getCalendarService(Google_Client $client): Google_Service_Calendar
    {
        return new Google_Service_Calendar($client);
    }

    /**
     * Get the available scopes or permissions needed by this adapter.
     *
     * @return array
     */
    public function getAvailableScopes(): array
    {
        return [
            Google_Service_Calendar::CALENDAR,
            Google_Service_Calendar::CALENDAR_EVENTS,
        ];
    }

    public function getCapabilities(): array
    {
        return [
            'read_events',
            'create_events',
            'update_events',
            'delete_events',
            'webhook_support'
        ];
    }

    public function getApiVersion(): string
    {
        return 'v3';
    }

    public function getCatalogueMetadata(): array
    {
        $metadata = parent::getCatalogueMetadata();
        $metadata['display_name'] = 'Google Calendar';
        $metadata['description'] = 'Manage events, calendars, and subscriptions.';
        $metadata['logo_url'] = 'https://upload.wikimedia.org/wikipedia/commons/a/a5/Google_Calendar_icon_%282020%29.svg';
        $metadata['setup_guide_url'] = 'https://developers.google.com/calendar/api/guides/overview';
        return $metadata;
    }

    public function getDataPermissions(): array
    {
        return [
            'read' => ['events', 'calendars', 'free/busy schedules'],
            'write' => ['events', 'calendars']
        ];
    }
}

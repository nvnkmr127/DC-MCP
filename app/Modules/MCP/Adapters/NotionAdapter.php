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

class NotionAdapter extends BaseAdapter
{
    /**
     * Get the provider identifier.
     *
     * @return string
     */
    protected function getProviderName(): string
    {
        return 'notion';
    }

    /**
     * Setup Guzzle client config for Notion.
     *
     * @param array $credentials
     * @return void
     */
    protected function setupNotionClient(array $credentials): void
    {
        $token = $credentials['token'] ?? '';
        $this->setupClient('https://api.notion.com/v1/', [
            'Authorization' => 'Bearer ' . $token,
            'Notion-Version' => '2022-06-28',
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
            $this->setupNotionClient($credentials);
            // Verify access by making a request to list users
            $response = $this->client->get('users?page_size=1');
            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Pull data from Notion to local database.
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
            $this->setupNotionClient($credentials);

            $settings = $connection->settings ?? [];
            $databaseIds = $settings['database_ids'] ?? [];

            $processedCount = 0;
            $failedCount = 0;

            foreach ($databaseIds as $databaseId => $entityType) {
                // Determine linked project for tasks if specified
                $projectId = null;
                if ($entityType === 'tasks') {
                    // Try to find a project mapped to this database or default project
                    $projectId = $settings['project_mapping'][$databaseId] ?? null;
                    if (!$projectId) {
                        // Fallback to first project or skip if none
                        $projectId = Project::where('organization_id', $connection->organization_id)->value('id');
                    }
                }

                $hasMore = true;
                $startCursor = null;

                while ($hasMore) {
                    // Respect Notion API rate limit (3 req/sec)
                    usleep(334000);

                    $payload = [];
                    if ($startCursor) {
                        $payload['start_cursor'] = $startCursor;
                    }

                    try {
                        $response = $this->client->post("databases/{$databaseId}/query", [
                            'json' => $payload
                        ]);

                        $data = json_decode($response->getBody()->getContents(), true);
                        $results = $data['results'] ?? [];
                        $hasMore = $data['has_more'] ?? false;
                        $startCursor = $data['next_cursor'] ?? null;

                        foreach ($results as $page) {
                            $pageId = $page['id'];
                            $pageUrl = $page['url'] ?? '';
                            $properties = $page['properties'] ?? [];

                            // Find and parse common properties
                            $titleProp = $this->findProperty($properties, ['Name', 'Title', 'title']);
                            $title = $this->parsePropertyValue($titleProp);

                            $statusProp = $this->findProperty($properties, ['Status', 'status']);
                            $status = $this->parsePropertyValue($statusProp);

                            $priorityProp = $this->findProperty($properties, ['Priority', 'priority']);
                            $priority = $this->parsePropertyValue($priorityProp);

                            $dueProp = $this->findProperty($properties, ['Due', 'due', 'Due Date']);
                            $dueDate = $this->parsePropertyValue($dueProp);

                            $assigneeProp = $this->findProperty($properties, ['Assignee', 'assignee', 'Owner']);
                            $assigneeEmail = $this->parsePropertyValue($assigneeProp);

                            // Log sync attempt
                            $this->logSync(
                                $connectionId,
                                'inbound',
                                'notion_page',
                                $pageId,
                                'success',
                                1,
                                0,
                                ['id' => $pageId, 'title' => $title, 'type' => $entityType]
                            );

                            if ($entityType === 'tasks') {
                                if (!$projectId) {
                                    continue;
                                }

                                $task = Task::where('project_id', $projectId)
                                    ->where('meta->notion_page_id', $pageId)
                                    ->first();

                                if (!$task) {
                                    $task = new Task();
                                    $task->organization_id = $connection->organization_id;
                                    $task->project_id = $projectId;
                                    $task->status = 'backlog';
                                    $task->priority = 'medium';
                                    $task->meta = ['notion_page_id' => $pageId];
                                }

                                $task->title = $title ?: 'Notion Task';
                                $task->description = 'Synced from Notion page: ' . $pageUrl;
                                
                                if ($dueDate) {
                                    $task->due_date = date('Y-m-d', strtotime($dueDate));
                                }

                                if ($priority) {
                                    $mappedPriority = strtolower($priority);
                                    if (in_array($mappedPriority, ['low', 'medium', 'high', 'critical'])) {
                                        $task->priority = $mappedPriority;
                                    }
                                }

                                if ($status) {
                                    $mappedStatus = match (strtolower(str_replace(' ', '_', $status))) {
                                        'backlog' => 'backlog',
                                        'to_do', 'todo' => 'todo',
                                        'in_progress' => 'in_progress',
                                        'in_review' => 'in_review',
                                        'blocked' => 'blocked',
                                        'done', 'completed' => 'done',
                                        'cancelled' => 'cancelled',
                                        default => 'todo',
                                    };
                                    $task->status = $mappedStatus;
                                }

                                if ($assigneeEmail) {
                                    $user = User::where('organization_id', $connection->organization_id)
                                        ->where('email', $assigneeEmail)
                                        ->first();
                                    if ($user) {
                                        $task->assigned_to = $user->id;
                                    }
                                }

                                $task->save();
                                $processedCount++;

                            } elseif ($entityType === 'sops') {
                                $attachment = DB::table('attachments')
                                    ->where('organization_id', $connection->organization_id)
                                    ->where('storage_path', $pageUrl)
                                    ->first();

                                if (!$attachment) {
                                    DB::table('attachments')->insert([
                                        'id' => (string) Str::uuid(),
                                        'attachable_type' => 'App\Modules\Auth\Models\Organization',
                                        'attachable_id' => $connection->organization_id,
                                        'organization_id' => $connection->organization_id,
                                        'filename' => $title ?: 'Notion SOP',
                                        'original_name' => $title ?: 'Notion SOP',
                                        'mime_type' => 'text/html',
                                        'size_bytes' => 0,
                                        'storage_path' => $pageUrl,
                                        'storage_disk' => 'notion',
                                        'created_at' => now(),
                                        'updated_at' => now(),
                                    ]);
                                }
                                $processedCount++;

                            } elseif ($entityType === 'projects') {
                                $project = Project::where('organization_id', $connection->organization_id)
                                    ->where(function($query) use ($pageId) {
                                        $query->where('settings->notion_page_id', $pageId)
                                              ->orWhere('settings->notion_id', $pageId);
                                    })
                                    ->first();

                                if (!$project && $title) {
                                    $project = Project::where('organization_id', $connection->organization_id)
                                        ->where('name', $title)
                                        ->first();
                                }

                                if (!$project) {
                                    $project = new Project();
                                    $project->organization_id = $connection->organization_id;
                                    $project->client_id = $this->getOrCreateClientId($connection->organization_id);
                                    $project->status = 'draft';
                                    $project->priority = 'medium';
                                    $project->type = 'seo';
                                }

                                $project->name = $title ?: 'Notion Project';
                                $project->slug = Str::slug($project->name);
                                
                                $projSettings = $project->settings ?? [];
                                $projSettings['notion_page_id'] = $pageId;
                                $project->settings = $projSettings;

                                if ($status) {
                                    $mappedStatus = match (strtolower(str_replace(' ', '_', $status))) {
                                        'draft' => 'draft',
                                        'active', 'in_progress' => 'active',
                                        'on_hold', 'paused' => 'on_hold',
                                        'completed', 'done' => 'completed',
                                        'cancelled' => 'cancelled',
                                        default => 'draft',
                                    };
                                    $project->status = $mappedStatus;
                                }

                                if ($priority) {
                                    $mappedPriority = strtolower($priority);
                                    if (in_array($mappedPriority, ['low', 'medium', 'high', 'critical'])) {
                                        $project->priority = $mappedPriority;
                                    }
                                }

                                $project->save();
                                $processedCount++;
                            }
                        }
                    } catch (\Exception $e) {
                        $failedCount++;
                        $this->logSync(
                            $connectionId,
                            'inbound',
                            'notion_database',
                            $databaseId,
                            'failed',
                            0,
                            1,
                            null,
                            $e->getMessage()
                        );
                        $hasMore = false; // exit page loop on db error
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
     * Push local changes to Notion.
     *
     * @param string $connectionId
     * @param array $data
     * @return SyncResult
     */
    public function push(string $connectionId, array $data): SyncResult
    {
        $connection = McpConnection::findOrFail($connectionId);
        $settings = $connection->settings ?? [];
        $pushUpdates = $settings['push_updates'] ?? false;

        if (!$pushUpdates) {
            return SyncResult::success(0, ['reason' => 'Push updates disabled']);
        }

        $entityType = $data['entity_type'] ?? null;
        $entityId = $data['entity_id'] ?? null;

        if ($entityType === 'task') {
            $task = Task::findOrFail($entityId);
            $success = $this->pushTaskUpdateWithConnection($connection, $task);
            return $success ? SyncResult::success(1) : SyncResult::failure('Failed to push task update to Notion');
        }

        return SyncResult::success(0, ['reason' => 'Unsupported entity type']);
    }

    /**
     * Push a task status update to Notion.
     *
     * @param Task $task
     * @return bool
     */
    public function pushTaskUpdate(Task $task): bool
    {
        $connection = McpConnection::where('organization_id', $task->organization_id)
            ->where('provider', 'notion')
            ->where('status', 'active')
            ->first();

        if (!$connection) {
            return false;
        }

        return $this->pushTaskUpdateWithConnection($connection, $task);
    }

    /**
     * Push status update via specific Notion connection.
     */
    protected function pushTaskUpdateWithConnection(McpConnection $connection, Task $task): bool
    {
        $pageId = $task->meta['notion_page_id'] ?? null;
        if (!$pageId) {
            return false;
        }

        $credentials = $this->decryptCredentials($connection->credentials ?? []);
        
        try {
            $this->setupNotionClient($credentials);
            $notionStatus = $this->mapLocalStatusToNotion($task->status);

            usleep(334000);

            // Notion statuses can be representable as a status property or select property.
            // We update via status.
            $payload = [
                'properties' => [
                    'Status' => [
                        'status' => [
                            'name' => $notionStatus
                        ]
                    ]
                ]
            ];

            $response = $this->client->patch("pages/{$pageId}", [
                'json' => $payload
            ]);

            $this->logSync($connection->id, 'outbound', 'task', $task->id, 'success', 1, 0, $payload);
            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            // If status type is select, fallback to updating as a select
            try {
                $payload = [
                    'properties' => [
                        'Status' => [
                            'select' => [
                                'name' => $notionStatus ?? ucfirst($task->status)
                            ]
                        ]
                    ]
                ];

                $response = $this->client->patch("pages/{$pageId}", [
                    'json' => $payload
                ]);

                $this->logSync($connection->id, 'outbound', 'task', $task->id, 'success', 1, 0, $payload);
                return $response->getStatusCode() === 200;
            } catch (\Exception $ex) {
                $this->logSync($connection->id, 'outbound', 'task', $task->id, 'failed', 0, 1, null, $ex->getMessage());
                return false;
            }
        }
    }

    /**
     * Get recently modified pages for Daily Briefing.
     *
     * @param int $hours
     * @return array
     */
    public function getRecentUpdates(int $hours = 24): array
    {
        $user = auth()->user();
        if (!$user) {
            return [];
        }

        $connection = McpConnection::where('organization_id', $user->organization_id)
            ->where('provider', 'notion')
            ->where('status', 'active')
            ->first();

        if (!$connection) {
            return [];
        }

        $credentials = $this->decryptCredentials($connection->credentials ?? []);
        
        try {
            $this->setupNotionClient($credentials);

            usleep(334000);

            // Search for pages sorted by last edited time
            $payload = [
                'sort' => [
                    'direction' => 'descending',
                    'timestamp' => 'last_edited_time'
                ]
            ];

            $response = $this->client->post('search', [
                'json' => $payload
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $results = $data['results'] ?? [];

            $recent = [];
            $limitTime = time() - ($hours * 3600);

            foreach ($results as $page) {
                $lastEdited = strtotime($page['last_edited_time'] ?? '');
                if ($lastEdited && $lastEdited >= $limitTime) {
                    $properties = $page['properties'] ?? [];
                    $titleProp = $this->findProperty($properties, ['Name', 'Title', 'title']);
                    $title = $this->parsePropertyValue($titleProp);

                    $recent[] = [
                        'id' => $page['id'],
                        'url' => $page['url'] ?? '',
                        'title' => $title ?: 'Untitled Page',
                        'last_edited_at' => $page['last_edited_time'],
                    ];
                }
            }

            return $recent;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Search across connected Notion workspaces.
     *
     * @param string $query
     * @return array
     */
    public function search(string $query): array
    {
        $user = auth()->user();
        if (!$user) {
            return [];
        }

        $connection = McpConnection::where('organization_id', $user->organization_id)
            ->where('provider', 'notion')
            ->where('status', 'active')
            ->first();

        if (!$connection) {
            return [];
        }

        $credentials = $this->decryptCredentials($connection->credentials ?? []);
        
        try {
            $this->setupNotionClient($credentials);

            usleep(334000);

            $payload = [
                'query' => $query
            ];

            $response = $this->client->post('search', [
                'json' => $payload
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $results = $data['results'] ?? [];

            $matches = [];
            foreach ($results as $page) {
                $properties = $page['properties'] ?? [];
                $titleProp = $this->findProperty($properties, ['Name', 'Title', 'title']);
                $title = $this->parsePropertyValue($titleProp);

                $matches[] = [
                    'id' => $page['id'],
                    'url' => $page['url'] ?? '',
                    'title' => $title ?: 'Untitled Page',
                ];
            }

            return $matches;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Handle webhook (Notion doesn't support official webhooks, stubbed).
     *
     * @param Request $request
     * @return WebhookResult
     */
    public function handleWebhook(Request $request): WebhookResult
    {
        return WebhookResult::skipped('Notion does not support standard webhooks');
    }

    /**
     * Test connection to Notion.
     *
     * @param array $credentials
     * @return ConnectionTestResult
     */
    public function testConnection(array $credentials): ConnectionTestResult
    {
        try {
            $this->setupNotionClient($credentials);
            $this->client->get('users?page_size=1');

            return ConnectionTestResult::success([
                'provider' => 'notion',
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
        return ['read', 'write'];
    }

    /**
     * Helper to retrieve or create default client.
     */
    protected function getOrCreateClientId(string $organizationId): string
    {
        $clientId = DB::table('clients')->where('organization_id', $organizationId)->value('id');
        if ($clientId) {
            return $clientId;
        }

        $newId = (string) Str::uuid();
        DB::table('clients')->insert([
            'id' => $newId,
            'organization_id' => $organizationId,
            'name' => 'Notion Default Client',
            'company' => 'Notion Synced Client',
            'tier' => 'basic',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $newId;
    }

    /**
     * Map local status strings or Enums to status/select names expected in Notion.
     */
    protected function mapLocalStatusToNotion(\App\Shared\Enums\TaskStatus|string $status): string
    {
        if ($status instanceof \App\Shared\Enums\TaskStatus) {
            $status = $status->value;
        }

        return match ($status) {
            'backlog' => 'Backlog',
            'todo' => 'To Do',
            'in_progress' => 'In Progress',
            'in_review' => 'In Review',
            'blocked' => 'Blocked',
            'done' => 'Done',
            'cancelled' => 'Cancelled',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    /**
     * Find property by case-insensitive key name.
     */
    protected function findProperty(array $properties, array $possibleNames)
    {
        foreach ($possibleNames as $name) {
            foreach ($properties as $key => $prop) {
                if (strtolower($key) === strtolower($name)) {
                    return $prop;
                }
            }
        }
        return null;
    }

    /**
     * Extract plain value from a Notion property structure.
     */
    protected function parsePropertyValue(?array $prop)
    {
        if (!$prop) {
            return null;
        }

        $type = $prop['type'] ?? '';

        switch ($type) {
            case 'title':
                return $prop['title'][0]['plain_text'] ?? null;
            case 'rich_text':
                return $prop['rich_text'][0]['plain_text'] ?? null;
            case 'status':
                return $prop['status']['name'] ?? null;
            case 'select':
                return $prop['select']['name'] ?? null;
            case 'date':
                return $prop['date']['start'] ?? null;
            case 'email':
                return $prop['email'] ?? null;
            case 'people':
                return $prop['people'][0]['person']['email'] ?? $prop['people'][0]['email'] ?? null;
            default:
                return null;
        }
    }
}

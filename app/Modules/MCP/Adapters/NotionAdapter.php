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
    protected function setupNotionClient(#[SensitiveParameter] array $credentials): void
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
    public function authenticate(#[SensitiveParameter] array $credentials): bool
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
    public function sync(string $connectionId, array $options = []): SyncResult
    {
        $startTime = microtime(true);
        $connection = McpConnection::findOrFail($connectionId);
        $credentials = $this->decryptCredentials($connection->credentials ?? []);
        
        try {
            $this->setupNotionClient($credentials);

            $settings = $connection->settings ?? [];
            $databaseIds = $settings['database_ids'] ?? [];
            $schemaDriftWarnings = [];
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
                $startCursor = $connection->getCursor("notion_cursor_{$databaseId}");

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
                        
                        if ($startCursor) {
                            $connection->setCursor("notion_cursor_{$databaseId}", $startCursor);
                        } else {
                            $connection->setCursor("notion_cursor_{$databaseId}", null);
                        }

                        $pageIds = array_column($results, 'id');
                        $existingTasks = collect();
                        $existingProjects = collect();
                        $existingUsers = collect();

                        if ($entityType === 'tasks' && $projectId) {
                            $existingTasks = Task::where('project_id', $projectId)
                                ->whereIn('meta->notion_page_id', $pageIds)
                                ->get()
                                ->keyBy(fn($t) => $t->meta['notion_page_id'] ?? '');

                            $existingUsers = User::where('organization_id', $connection->organization_id)
                                ->get()
                                ->keyBy('email');
                        } elseif ($entityType === 'projects') {
                            $existingProjects = Project::where('organization_id', $connection->organization_id)
                                ->where(function($query) use ($pageIds) {
                                    foreach ($pageIds as $id) {
                                        $query->orWhere('settings->notion_page_id', $id)
                                              ->orWhere('settings->notion_id', $id);
                                    }
                                })
                                ->get()
                                ->keyBy(fn($p) => $p->settings['notion_page_id'] ?? $p->settings['notion_id'] ?? '');
                        }

                        $tasksToUpsert = [];
                        $projectsToUpsert = [];

                        foreach ($results as $page) {
                            $pageId = $page['id'];
                            $pageUrl = $page['url'] ?? '';
                            $properties = $page['properties'] ?? [];

                            // Get custom mappings or fall back to defaults
                            $mappings = $settings['field_mappings'] ?? [];
                            
                            $computeValue = function($key, $defaultKeys) use ($mappings, $properties, &$schemaDriftWarnings, $pageId) {
                                if (empty($mappings[$key])) {
                                    $propName = $defaultKeys[0] ?? $key;
                                    $prop = $this->findProperty($properties, $defaultKeys);
                                    if (!$prop && in_array($key, ['title', 'Name'])) {
                                        $schemaDriftWarnings[] = "Missing fallback field '$key' for page $pageId";
                                    }
                                    return $this->parsePropertyValue($prop, $schemaDriftWarnings, $propName);
                                }
                                
                                $pattern = $mappings[$key];
                                
                                // If it uses brackets, it's a computed formula/concat field (e.g. "{First Name} {Last Name}")
                                if (str_contains($pattern, '{')) {
                                    $computed = preg_replace_callback('/\{([^}]+)\}/', function($matches) use ($properties, &$schemaDriftWarnings, $pageId) {
                                        $propName = trim($matches[1]);
                                        $prop = $this->findProperty($properties, [$propName]);
                                        if (!$prop) {
                                            $schemaDriftWarnings[] = "Missing mapped property '$propName' in computed field for page $pageId";
                                        }
                                        $val = $this->parsePropertyValue($prop, $schemaDriftWarnings, $propName);
                                        return is_array($val) ? implode(', ', $val) : ($val ?? '');
                                    }, $pattern);
                                    return trim($computed);
                                }
                                
                                // Otherwise, standard comma-separated fallback list of property names
                                $possibleKeys = array_map('trim', explode(',', $pattern));
                                $prop = $this->findProperty($properties, $possibleKeys);
                                if (!$prop) {
                                    $schemaDriftWarnings[] = "Missing mapped property '$pattern' for key '$key' for page $pageId";
                                }
                                return $this->parsePropertyValue($prop, $schemaDriftWarnings, $possibleKeys[0] ?? $key);
                            };

                            // Parse properties using computed mappings or fallbacks
                            $title = $computeValue('title', ['Name', 'Title', 'title']);
                            $status = $computeValue('status', ['Status', 'status']);
                            $priority = $computeValue('priority', ['Priority', 'priority']);
                            $dueDate = $computeValue('due_date', ['Due', 'due', 'Due Date']);
                            $assigneeEmail = $computeValue('assignee', ['Assignee', 'assignee', 'Owner']);
                            $tags = $computeValue('tags', ['Tags', 'tags', 'Labels']);

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

                                $task = clone ($existingTasks->get($pageId) ?? new Task());
                                $isNewTask = !$task->exists;

                                if ($isNewTask) {
                                    $task->id = (string) Str::uuid();
                                    $task->organization_id = $connection->organization_id;
                                    $task->project_id = $projectId;
                                    $task->status = 'backlog';
                                    $task->priority = 'medium';
                                    $task->meta = ['notion_page_id' => $pageId];
                                }

                                $task->title = $title ?: 'Notion Task';
                                $task->description = 'Synced from Notion page: ' . $pageUrl;
                                
                                if ($dueDate) {
                                    $timestamp = strtotime($dueDate);
                                    if ($timestamp === false) {
                                        $schemaDriftWarnings[] = "Invalid date format '$dueDate' for page $pageId (coercion failed)";
                                    } else {
                                        $task->due_date = date('Y-m-d', $timestamp);
                                    }
                                }

                                if ($priority) {
                                    $mappedPriority = strtolower($priority);
                                    if (in_array($mappedPriority, ['low', 'medium', 'high', 'critical'])) {
                                        $task->priority = $mappedPriority;
                                    } else {
                                        $schemaDriftWarnings[] = "Unrecognized priority '$priority' for page $pageId, defaulting to 'medium'";
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
                                        default => null,
                                    };
                                    
                                    if ($mappedStatus) {
                                        $task->status = $mappedStatus;
                                    } else {
                                        $task->status = 'todo';
                                        $schemaDriftWarnings[] = "Unrecognized status '$status' for page $pageId, coercing to 'todo'";
                                    }
                                    
                                    // Conditional mapping: automatically update completed_at based on status
                                    if ($task->status === 'done') {
                                        if (!$task->completed_at) {
                                            $task->completed_at = now();
                                        }
                                    } else {
                                        $task->completed_at = null;
                                    }
                                }

                                if ($assigneeEmail) {
                                    $email = is_array($assigneeEmail) ? ($assigneeEmail[0] ?? null) : $assigneeEmail;
                                    if ($email) {
                                        $user = $existingUsers->get($email);
                                        if ($user) {
                                            $task->assigned_to = $user->id;
                                        }
                                    }
                                }
                                
                                if ($tags) {
                                    $task->tags = is_array($tags) ? $tags : array_map('trim', explode(',', $tags));
                                }

                                $task->updated_at = now();
                                if ($isNewTask) {
                                    $task->created_at = now();
                                }
                                $tasksToUpsert[] = $task->getAttributes();
                                $processedCount++;
                                $this->reportSyncProgress($connection, $processedCount);

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
                                $this->reportSyncProgress($connection, $processedCount);

                            } elseif ($entityType === 'projects') {
                                $project = clone ($existingProjects->get($pageId) ?? new Project());
                                $isNewProject = !$project->exists;

                                if ($isNewProject && $title) {
                                    // Memory lookup for name fallback to avoid query inside loop if possible
                                    $projectByName = Project::where('organization_id', $connection->organization_id)
                                        ->where('name', $title)
                                        ->first();
                                    if ($projectByName) {
                                        $project = clone $projectByName;
                                        $isNewProject = false;
                                    }
                                }

                                if ($isNewProject) {
                                    $project->id = (string) Str::uuid();
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

                                $project->updated_at = now();
                                if ($isNewProject) {
                                    $project->created_at = now();
                                }
                                $projectsToUpsert[] = $project->getAttributes();
                                $processedCount++;
                                $this->reportSyncProgress($connection, $processedCount);
                            }
                        }

                        // Bulk Upserts for Database Efficiency
                        if (!empty($tasksToUpsert)) {
                            Task::upsert($tasksToUpsert, ['id'], ['title', 'description', 'due_date', 'priority', 'status', 'completed_at', 'assigned_to', 'tags', 'meta', 'updated_at']);
                        }
                        if (!empty($projectsToUpsert)) {
                            Project::upsert($projectsToUpsert, ['id'], ['name', 'slug', 'settings', 'status', 'priority', 'updated_at']);
                        }

                        if ($this->shouldYieldExecution($startTime)) {
                            return SyncResult::success($processedCount, [
                                'has_more' => true,
                                'schema_drift_warnings' => array_slice(array_unique($schemaDriftWarnings), 0, 50)
                            ], (int)((microtime(true) - $startTime) * 1000));
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

            // Deduplicate warnings
            $schemaDriftWarnings = array_unique($schemaDriftWarnings);

            return SyncResult::success($processedCount, [
                'has_more' => $hasMore,
                'next_cursor' => $startCursor,
                'schema_drift_warnings' => array_slice($schemaDriftWarnings, 0, 50)
            ], $durationMs);

        } catch (\Exception $e) {
            $connection->markSyncFailed($e->getMessage());
            return SyncResult::failure('Failed to sync from Notion: ' . $e->getMessage());
        }
    }

    public function previewMapping(\App\Modules\MCP\Models\McpConnection $connection, #[\SensitiveParameter] array $credentials, array $proposedMappings): array
    {
        $this->setupNotionClient($credentials);
        $settings = $credentials['_settings'] ?? [];
        $databaseIds = $settings['database_ids'] ?? [];

        if (empty($databaseIds)) {
            return ['error' => 'No databases configured.'];
        }

        $databaseId = array_key_first($databaseIds);

        try {
            $response = $this->client->post("databases/{$databaseId}/query", [
                'json' => ['page_size' => 1]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $results = $data['results'] ?? [];

            if (empty($results)) {
                return ['error' => 'No pages found in the external database.'];
            }

            $page = $results[0];
            $properties = $page['properties'] ?? [];
            $pageId = $page['id'];
            
            $schemaDriftWarnings = [];
            $mappings = $proposedMappings;

            $computeValue = function($key, $defaultKeys) use ($mappings, $properties, &$schemaDriftWarnings, $pageId) {
                if (empty($mappings[$key])) {
                    $propName = $defaultKeys[0] ?? $key;
                    $prop = $this->findProperty($properties, $defaultKeys);
                    if (!$prop && in_array($key, ['title', 'Name'])) {
                        $schemaDriftWarnings[] = "Missing fallback field '$key' for page $pageId";
                    }
                    return $this->parsePropertyValue($prop, $schemaDriftWarnings, $propName);
                }
                
                $pattern = $mappings[$key];
                
                if (str_contains($pattern, '{')) {
                    $computed = preg_replace_callback('/\{([^}]+)\}/', function($matches) use ($properties, &$schemaDriftWarnings, $pageId) {
                        $propName = trim($matches[1]);
                        $prop = $this->findProperty($properties, [$propName]);
                        if (!$prop) {
                            $schemaDriftWarnings[] = "Missing mapped property '$propName' in computed field for page $pageId";
                        }
                        $val = $this->parsePropertyValue($prop, $schemaDriftWarnings, $propName);
                        return is_array($val) ? implode(', ', $val) : ($val ?? '');
                    }, $pattern);
                    return trim($computed);
                }
                
                $possibleKeys = array_map('trim', explode(',', $pattern));
                $prop = $this->findProperty($properties, $possibleKeys);
                if (!$prop) {
                    $schemaDriftWarnings[] = "Missing mapped property '$pattern' for key '$key' for page $pageId";
                }
                return $this->parsePropertyValue($prop, $schemaDriftWarnings, $possibleKeys[0] ?? $key);
            };

            $title = $computeValue('title', ['Name', 'Title', 'title']);
            $status = $computeValue('status', ['Status', 'status']);
            $priority = $computeValue('priority', ['Priority', 'priority']);
            $dueDate = $computeValue('due_date', ['Due', 'due', 'Due Date']);
            $assigneeEmail = $computeValue('assignee', ['Assignee', 'assignee', 'Owner']);
            $tags = $computeValue('tags', ['Tags', 'tags', 'Labels']);

            $mappedData = [
                'title' => $title ?: 'Notion Task',
                'status' => 'todo',
                'priority' => 'medium',
                'due_date' => null,
                'completed_at' => null,
                'assignee_email' => is_array($assigneeEmail) ? ($assigneeEmail[0] ?? null) : $assigneeEmail,
                'tags' => is_array($tags) ? $tags : ($tags ? array_map('trim', explode(',', $tags)) : null),
            ];

            if ($dueDate) {
                $timestamp = strtotime($dueDate);
                if ($timestamp === false) {
                    $schemaDriftWarnings[] = "Invalid date format '$dueDate' for page $pageId (coercion failed)";
                } else {
                    $mappedData['due_date'] = date('Y-m-d', $timestamp);
                }
            }

            if ($priority) {
                $mappedPriority = strtolower($priority);
                if (in_array($mappedPriority, ['low', 'medium', 'high', 'critical'])) {
                    $mappedData['priority'] = $mappedPriority;
                } else {
                    $schemaDriftWarnings[] = "Unrecognized priority '$priority' for page $pageId, defaulting to 'medium'";
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
                    default => null,
                };
                
                if ($mappedStatus) {
                    $mappedData['status'] = $mappedStatus;
                } else {
                    $schemaDriftWarnings[] = "Unrecognized status '$status' for page $pageId, coercing to 'todo'";
                }
                
                if ($mappedData['status'] === 'done') {
                    $mappedData['completed_at'] = now()->toDateTimeString();
                }
            }

            return [
                'raw' => $properties,
                'mapped' => $mappedData,
                'warnings' => array_unique($schemaDriftWarnings),
            ];
        } catch (\Exception $e) {
            return ['error' => 'Failed to fetch preview record from Notion: ' . $e->getMessage()];
        }
    }

    /**
     * Push local changes to Notion.
     *
     * @param string $connectionId
     * @param array $data
     * @return SyncResult
     */
    protected function performPush(string $connectionId, array $data, array $options = []): SyncResult
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
            \Illuminate\Support\Facades\Log::error('Notion Adapter getRecentUpdates failed: ' . $e->getMessage());
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
            \Illuminate\Support\Facades\Log::error('Notion Adapter search failed: ' . $e->getMessage());
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
    public function testConnection(#[SensitiveParameter] array $credentials): ConnectionTestResult
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
    protected function parsePropertyValue(?array $prop, array &$warnings = [], string $propName = 'unknown')
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
                $emails = [];
                foreach ($prop['people'] ?? [] as $person) {
                    if (isset($person['person']['email'])) {
                        $emails[] = $person['person']['email'];
                    } elseif (isset($person['email'])) {
                        $emails[] = $person['email'];
                    }
                }
                return empty($emails) ? null : $emails;
            case 'number':
                return (string) ($prop['number'] ?? '');
            case 'checkbox':
                return $prop['checkbox'] ? 'true' : 'false';
            case 'url':
                return $prop['url'] ?? null;
            case 'phone_number':
                return $prop['phone_number'] ?? null;
            case 'multi_select':
                return array_column($prop['multi_select'] ?? [], 'name');
            default:
                $warnings[] = "Unsupported data type '$type' encountered for property '$propName' during coercion.";
                return null;
        }
    }

    public function getCapabilities(): array
    {
        return [
            'read_databases',
            'read_pages',
            'create_pages',
            'update_pages',
            'search'
        ];
    }

    public function getApiVersion(): string
    {
        return '2022-06-28';
    }

    public function getCatalogueMetadata(): array
    {
        $metadata = parent::getCatalogueMetadata();
        $metadata['display_name'] = 'Notion';
        $metadata['description'] = 'Connect your workspace to read and write pages and databases.';
        $metadata['logo_url'] = 'https://upload.wikimedia.org/wikipedia/commons/4/45/Notion_app_logo.png';
        $metadata['setup_guide_url'] = 'https://developers.notion.com/docs/getting-started';
        return $metadata;
    }

    public function getRateLimitStatus(#[SensitiveParameter] array $credentials): ?array
    {
        try {
            $this->setupNotionClient($credentials);
            // Make a lightweight call just to get headers
            $response = $this->client->get('users/me');
            
            // Notion doesn't return remaining limit dynamically unless you hit it, 
            // but we can return the standard limits or any headers they do provide.
            return [
                'limit' => 3, // Notion's default is 3 requests per second
                'unit' => 'requests_per_second',
                'remaining' => 'unknown', // Dynamic tracking would require caching
                'reset_at' => time() + 1,
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getExternalStatus(): ?array
    {
        try {
            // Notion status page API
            $client = new \GuzzleHttp\Client(['timeout' => 5]);
            $response = $client->get('https://status.notion.so/api/v2/status.json');
            $data = json_decode((string)$response->getBody(), true);
            
            return [
                'status' => $data['status']['indicator'] === 'none' ? 'operational' : 'degraded',
                'description' => $data['status']['description'] ?? 'All systems operational',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unknown',
                'description' => 'Unable to fetch Notion status. Check https://status.notion.so'
            ];
        }
    }

    public function getDataPermissions(): array
    {
        return [
            'read' => ['pages', 'databases', 'users'],
            'write' => ['pages', 'databases']
        ];
    }

    /**
     * Preview what data will be synced without actually syncing it.
     */
    public function syncPreview(#[SensitiveParameter] array $credentials, array $options = []): array
    {
        try {
            $this->setupNotionClient($credentials);
            $settings = $credentials['_settings'] ?? [];
            $databaseIds = $settings['database_ids'] ?? [];

            $totalEstimatedRecords = 0;
            $databasesCount = count($databaseIds);

            if ($databasesCount === 0) {
                return [
                    'supported' => true,
                    'records_to_process' => 0,
                    'summary' => 'No databases configured for sync.'
                ];
            }

            // For preview, we just fetch 1 page from each database to check connectivity
            // and estimate if there are records. A full count is too expensive.
            foreach ($databaseIds as $databaseId => $entityType) {
                $response = $this->client->post("databases/{$databaseId}/query", [
                    'json' => ['page_size' => 1]
                ]);
                $data = json_decode($response->getBody()->getContents(), true);
                
                // If it has results, we estimate at least 1, maybe more
                if (!empty($data['results'])) {
                    $totalEstimatedRecords += 1; // It's just a lightweight test
                }
            }

            return [
                'supported' => true,
                'records_to_process' => $totalEstimatedRecords,
                'summary' => "Ready to sync from {$databasesCount} configured database(s). The preview successfully connected and verified data access.",
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

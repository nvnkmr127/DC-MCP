<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Modules\MCP\Adapters\NotionAdapter;
use App\Modules\MCP\Models\McpConnection;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\ProjectManagement\Models\Task;
use App\Modules\Auth\Models\Organization;
use App\Modules\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class TestableNotionAdapter extends NotionAdapter
{
    public function setClient(Client $client): void
    {
        $this->client = $client;
    }

    protected function setupClient(string $baseUri, array $headers = [], array $clientConfig = []): void
    {
        // Overridden to preserve the injected mock client
    }
}

class NotionAdapterTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;
    protected User $user;
    protected Project $project;
    protected McpConnection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'name' => 'Test Org',
            'slug' => 'test-org',
            'plan' => 'free',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'organization_id' => $this->organization->id,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $this->actingAs($this->user);

        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'client_id' => \App\Modules\ProjectManagement\Models\Client::create([
                'organization_id' => $this->organization->id,
                'name' => 'Client A',
                'email' => 'clienta@example.com',
                'company' => 'Acme Corp',
                'tier' => 'basic',
                'status' => 'active',
            ])->id,
            'name' => 'Test Project',
            'slug' => 'test-project',
            'type' => 'seo',
            'status' => 'active',
        ]);

        $this->connection = McpConnection::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'provider' => 'notion',
            'name' => 'Notion Conn',
            'status' => 'active',
            'credentials' => [
                'token' => 'secret_1234567890'
            ],
            'settings' => [
                'database_ids' => [
                    'db_tasks_123' => 'tasks',
                    'db_sops_456' => 'sops',
                    'db_projects_789' => 'projects'
                ],
                'project_mapping' => [
                    'db_tasks_123' => $this->project->id
                ],
                'push_updates' => true
            ]
        ]);
    }

    public function test_sync_pulls_tasks_and_maps_properties()
    {
        $clientMock = $this->createMock(Client::class);
        $responseMock = $this->createMock(ResponseInterface::class);
        $streamMock = $this->createMock(StreamInterface::class);

        $notionTaskResponse = [
            'results' => [
                [
                    'id' => 'page_task_999',
                    'url' => 'https://notion.so/page_task_999',
                    'properties' => [
                        'Name' => [
                            'type' => 'title',
                            'title' => [['plain_text' => 'Write Notion Integration']]
                        ],
                        'Status' => [
                            'type' => 'status',
                            'status' => ['name' => 'In Progress']
                        ],
                        'Priority' => [
                            'type' => 'select',
                            'select' => ['name' => 'High']
                        ],
                        'Due' => [
                            'type' => 'date',
                            'date' => ['start' => '2026-06-01']
                        ],
                        'Assignee' => [
                            'type' => 'people',
                            'people' => [['person' => ['email' => 'test@example.com']]]
                        ]
                    ]
                ]
            ],
            'has_more' => false,
            'next_cursor' => null
        ];

        $streamMock->method('getContents')->willReturn(json_encode($notionTaskResponse));
        $responseMock->method('getBody')->willReturn($streamMock);
        $responseMock->method('getStatusCode')->willReturn(200);

        // We have 3 databases configured in settings: tasks, sops, projects.
        // Let's set up the clientMock to return data for each database query.
        $clientMock->method('post')
            ->willReturn($responseMock);

        $adapter = new TestableNotionAdapter();
        $adapter->setClient($clientMock);

        $result = $adapter->sync($this->connection->id);

        $this->assertTrue($result->isSuccess);
        $this->assertGreaterThanOrEqual(1, $result->processedCount);

        $task = Task::where('project_id', $this->project->id)->first();
        $this->assertNotNull($task);
        $this->assertEquals('Write Notion Integration', $task->title);
        $this->assertEquals('in_progress', $task->status);
        $this->assertEquals('high', $task->priority);
        $this->assertEquals('2026-06-01', $task->due_date->format('Y-m-d'));
        $this->assertEquals($this->user->id, $task->assigned_to);
    }

    public function test_sync_pulls_sops_as_attachments()
    {
        $clientMock = $this->createMock(Client::class);
        $responseMock = $this->createMock(ResponseInterface::class);
        $streamMock = $this->createMock(StreamInterface::class);

        $notionSopResponse = [
            'results' => [
                [
                    'id' => 'page_sop_888',
                    'url' => 'https://notion.so/seo_ SOP_documentation',
                    'properties' => [
                        'Name' => [
                            'type' => 'title',
                            'title' => [['plain_text' => 'SEO Checklist SOP']]
                        ]
                    ]
                ]
            ],
            'has_more' => false,
            'next_cursor' => null
        ];

        $streamMock->method('getContents')->willReturn(json_encode($notionSopResponse));
        $responseMock->method('getBody')->willReturn($streamMock);
        $responseMock->method('getStatusCode')->willReturn(200);

        $clientMock->method('post')->willReturn($responseMock);

        $adapter = new TestableNotionAdapter();
        $adapter->setClient($clientMock);

        $result = $adapter->sync($this->connection->id);

        $this->assertTrue($result->isSuccess);

        $attachment = DB::table('attachments')
            ->where('organization_id', $this->organization->id)
            ->where('storage_disk', 'notion')
            ->first();

        $this->assertNotNull($attachment);
        $this->assertEquals('SEO Checklist SOP', $attachment->filename);
        $this->assertEquals('https://notion.so/seo_ SOP_documentation', $attachment->storage_path);
    }

    public function test_push_updates_notion_page_status()
    {
        $task = Task::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'title' => 'Design homepage',
            'status' => 'in_progress',
            'meta' => ['notion_page_id' => 'page_task_123']
        ]);

        $clientMock = $this->createMock(Client::class);
        $responseMock = $this->createMock(ResponseInterface::class);

        $responseMock->method('getStatusCode')->willReturn(200);

        $clientMock->expects($this->once())
            ->method('patch')
            ->with('pages/page_task_123', $this->callback(function($params) {
                return $params['json']['properties']['Status']['status']['name'] === 'In Progress';
            }))
            ->willReturn($responseMock);

        $adapter = new TestableNotionAdapter();
        $adapter->setClient($clientMock);

        $result = $adapter->push($this->connection->id, [
            'entity_type' => 'task',
            'entity_id' => $task->id
        ]);

        $this->assertTrue($result->isSuccess);
        $this->assertEquals(1, $result->processedCount);
    }

    public function test_search_and_get_recent_updates()
    {
        $clientMock = $this->createMock(Client::class);
        $responseMock = $this->createMock(ResponseInterface::class);
        $streamMock = $this->createMock(StreamInterface::class);

        $searchResponse = [
            'results' => [
                [
                    'id' => 'page_recent_1',
                    'url' => 'https://notion.so/page_recent_1',
                    'last_edited_time' => now()->toIso8601String(),
                    'properties' => [
                        'Name' => [
                            'type' => 'title',
                            'title' => [['plain_text' => 'Recent Updated Page']]
                        ]
                    ]
                ]
            ]
        ];

        $streamMock->method('getContents')->willReturn(json_encode($searchResponse));
        $responseMock->method('getBody')->willReturn($streamMock);

        $clientMock->method('post')->willReturn($responseMock);

        $adapter = new TestableNotionAdapter();
        $adapter->setClient($clientMock);

        $recent = $adapter->getRecentUpdates(24);
        $this->assertCount(1, $recent);
        $this->assertEquals('Recent Updated Page', $recent[0]['title']);

        $searchResults = $adapter->search('Updated');
        $this->assertCount(1, $searchResults);
        $this->assertEquals('Recent Updated Page', $searchResults[0]['title']);
    }

    public function test_test_connection_returns_success_on_valid_credentials()
    {
        $clientMock = $this->createMock(Client::class);
        $responseMock = $this->createMock(ResponseInterface::class);

        $responseMock->method('getStatusCode')->willReturn(200);
        $clientMock->expects($this->once())
            ->method('get')
            ->with('users?page_size=1')
            ->willReturn($responseMock);

        $adapter = new TestableNotionAdapter();
        $adapter->setClient($clientMock);

        $result = $adapter->testConnection(['token' => 'valid_token']);

        $this->assertTrue($result->isConnected);
    }
}

<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Modules\MCP\Adapters\ZohoCliqAdapter;
use App\Modules\MCP\Models\McpConnection;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\ProjectManagement\Models\Task;
use App\Modules\Auth\Models\Organization;
use App\Modules\Auth\Models\User;
use App\Modules\DailyBriefing\Models\DailyBriefing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class TestableZohoCliqAdapter extends ZohoCliqAdapter
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

class ZohoCliqAdapterTest extends TestCase
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

        // Generate client UUID and insert manually to avoid SQLite insertGetId returning 1
        $clientId = (string) \Illuminate\Support\Str::uuid();
        DB::table('clients')->insert([
            'id' => $clientId,
            'organization_id' => $this->organization->id,
            'name' => 'Client A',
            'email' => 'clienta@example.com',
            'company' => 'Acme Corp',
            'tier' => 'basic',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'client_id' => $clientId,
            'name' => 'Test Project',
            'slug' => 'test-project',
            'type' => 'seo',
            'status' => 'active',
        ]);

        $this->connection = McpConnection::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'provider' => 'zoho_cliq',
            'name' => 'Zoho Cliq Conn',
            'status' => 'active',
            'credentials' => [
                'access_token' => 'accessToken_123',
                'refresh_token' => 'refreshToken_123',
                'expires_in' => 3600,
                'created' => time()
            ],
            'settings' => [
                'channels_to_watch' => ['general'],
                'briefing_channel' => 'briefings',
                'alert_channel' => 'alerts',
                'default_project_id' => $this->project->id,
                'project_mapping' => [
                    'general' => $this->project->id
                ]
            ]
        ]);
    }

    public function test_sync_pulls_messages_and_creates_tasks()
    {
        $clientMock = $this->createMock(Client::class);
        $responseMock = $this->createMock(ResponseInterface::class);
        $streamMock = $this->createMock(StreamInterface::class);

        $messagesResponse = [
            'data' => [
                [
                    'id' => 'msg_111',
                    'content' => '@digicloudify Please optimize the homepage assets [TASK]',
                    'sender' => [
                        'email' => 'test@example.com',
                        'name' => 'Test User'
                    ]
                ]
            ]
        ];

        $streamMock->method('getContents')->willReturn(json_encode($messagesResponse));
        $responseMock->method('getBody')->willReturn($streamMock);
        $responseMock->method('getStatusCode')->willReturn(200);

        $clientMock->expects($this->once())
            ->method('get')
            ->with('channels/general/messages', $this->callback(function($options) {
                return $options['query']['limit'] === 20;
            }))
            ->willReturn($responseMock);

        $adapter = new TestableZohoCliqAdapter();
        $adapter->setClient($clientMock);

        $result = $adapter->sync($this->connection->id);

        $this->assertTrue($result->isSuccess);
        $this->assertEquals(1, $result->processedCount);

        // Verify task was created
        $task = Task::where('project_id', $this->project->id)->first();
        $this->assertNotNull($task);
        $this->assertEquals("Please optimize the homepage assets", $task->title);
        $this->assertEquals($this->user->id, $task->assigned_to);
        $this->assertEquals('msg_111', $task->meta['zoho_message_id']);
    }

    public function test_push_briefing_sends_card_to_briefing_channel()
    {
        $briefing = DailyBriefing::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'date' => now(),
            'status' => 'ready',
            'digest_text' => 'Good morning. Here is the daily summary.',
            'digest_html' => '<p>Good morning</p>'
        ]);

        $clientMock = $this->createMock(Client::class);
        $responseMock = $this->createMock(ResponseInterface::class);

        $responseMock->method('getStatusCode')->willReturn(200);

        $clientMock->expects($this->once())
            ->method('post')
            ->with('channels/briefings/messages', $this->callback(function($params) {
                return str_contains($params['json']['card']['title'], 'Daily Briefing') && 
                       str_contains($params['json']['card']['sections'][0]['description'], 'Good morning');
            }))
            ->willReturn($responseMock);

        $adapter = new TestableZohoCliqAdapter();
        $adapter->setClient($clientMock);

        $result = $adapter->push($this->connection->id, [
            'entity_type' => 'briefing',
            'entity_id' => $briefing->id,
            'user_id' => $this->user->id
        ]);

        $this->assertTrue($result->isSuccess);
    }

    public function test_push_task_alert_sends_direct_message_to_assignee()
    {
        $task = Task::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'title' => 'Design wireframe',
            'status' => 'todo',
            'assigned_to' => $this->user->id,
            'due_date' => now()->toDateString()
        ]);

        $clientMock = $this->createMock(Client::class);
        $responseMock = $this->createMock(ResponseInterface::class);

        $responseMock->method('getStatusCode')->willReturn(200);

        $clientMock->expects($this->once())
            ->method('post')
            ->with('users/test@example.com/messages', $this->callback(function($params) {
                return str_contains($params['json']['text'], 'New task assigned: Design wireframe');
            }))
            ->willReturn($responseMock);

        $adapter = new TestableZohoCliqAdapter();
        $adapter->setClient($clientMock);

        $result = $adapter->push($this->connection->id, [
            'entity_type' => 'task',
            'entity_id' => $task->id,
            'alert_type' => 'assigned'
        ]);

        $this->assertTrue($result->isSuccess);
    }

    public function test_test_connection_returns_success()
    {
        $clientMock = $this->createMock(Client::class);
        $responseMock = $this->createMock(ResponseInterface::class);
        $streamMock = $this->createMock(StreamInterface::class);

        $userData = ['email' => 'test@example.com', 'name' => 'Test User'];
        $streamMock->method('getContents')->willReturn(json_encode($userData));
        $responseMock->method('getBody')->willReturn($streamMock);
        $responseMock->method('getStatusCode')->willReturn(200);

        $clientMock->expects($this->once())
            ->method('get')
            ->with('users/me')
            ->willReturn($responseMock);

        $adapter = new TestableZohoCliqAdapter();
        $adapter->setClient($clientMock);

        $result = $adapter->testConnection(['access_token' => 'valid_token']);

        $this->assertTrue($result->isConnected);
    }
}

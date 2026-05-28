<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Modules\MCP\Adapters\MakeAdapter;
use App\Modules\MCP\Models\McpConnection;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\ProjectManagement\Models\Task;
use App\Modules\Auth\Models\Organization;
use App\Modules\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class TestableMakeAdapter extends MakeAdapter
{
    public function setClient(Client $client): void
    {
        $this->client = $client;
    }

    protected function setupClient(string $baseUri, array $headers = [], array $clientConfig = []): void
    {
        // Overridden to preserve the injected mock client
    }

    protected function getWebhookClient(): Client
    {
        return $this->client;
    }
}

class MakeAdapterTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;
    protected User $user;
    protected Project $project;
    protected McpConnection $connection;
    protected string $clientId;

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

        // Pre-generate client UUID to avoid SQLite insertGetId returning 1
        $this->clientId = (string) Str::uuid();
        DB::table('clients')->insert([
            'id' => $this->clientId,
            'organization_id' => $this->organization->id,
            'name' => 'Client Make',
            'email' => 'clientmake@example.com',
            'company' => 'Make Corp',
            'tier' => 'basic',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'client_id' => $this->clientId,
            'name' => 'Test Project Make',
            'slug' => 'test-project-make',
            'type' => 'web_dev',
            'status' => 'active',
        ]);

        $this->connection = McpConnection::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'provider' => 'make',
            'name' => 'Make.com Connection',
            'status' => 'active',
            'credentials' => [
                'api_key' => 'make_api_key_123'
            ],
            'settings' => [
                'team_id' => 'team_xyz',
                'organization_id_make' => 'org_abc',
                'shared_secret' => 'webhook_secret_key',
                'scenario_ids' => [10001],
                'webhook_scenarios' => [
                    'task_completed' => 'https://hook.make.com/task_completed_hook'
                ]
            ]
        ]);
    }

    public function test_authenticate_success()
    {
        $clientMock = $this->createMock(Client::class);
        $responseMock = $this->createMock(ResponseInterface::class);

        $responseMock->method('getStatusCode')->willReturn(200);

        $clientMock->expects($this->once())
            ->method('get')
            ->with('users/me')
            ->willReturn($responseMock);

        $adapter = new TestableMakeAdapter();
        $adapter->setClient($clientMock);

        $result = $adapter->authenticate(['api_key' => 'valid_key']);
        $this->assertTrue($result);
    }

    public function test_test_connection_success()
    {
        $clientMock = $this->createMock(Client::class);
        $responseMock = $this->createMock(ResponseInterface::class);
        $streamMock = $this->createMock(StreamInterface::class);

        $userData = ['name' => 'John Make', 'email' => 'john@make.com'];
        $streamMock->method('getContents')->willReturn(json_encode($userData));
        $responseMock->method('getBody')->willReturn($streamMock);
        $responseMock->method('getStatusCode')->willReturn(200);

        $clientMock->expects($this->once())
            ->method('get')
            ->with('users/me')
            ->willReturn($responseMock);

        $adapter = new TestableMakeAdapter();
        $adapter->setClient($clientMock);

        $result = $adapter->testConnection(['api_key' => 'valid_key']);

        $this->assertTrue($result->isConnected);
        $this->assertEquals('make', $result->diagnostics['provider']);
        $this->assertEquals('John Make', $result->diagnostics['user_info']['name']);
    }

    public function test_sync_pulls_executions_and_logs_failures()
    {
        $clientMock = $this->createMock(Client::class);
        $responseMock = $this->createMock(ResponseInterface::class);
        $streamMock = $this->createMock(StreamInterface::class);

        $executionsData = [
            'executions' => [
                [
                    'id' => 'exec_fail_1',
                    'status' => 'failed',
                    'duration' => 2500,
                    'error' => 'Scenario failed during execution'
                ],
                [
                    'id' => 'exec_success_1',
                    'status' => 'success',
                    'duration' => 1200
                ]
            ]
        ];

        $streamMock->method('getContents')->willReturn(json_encode($executionsData));
        $responseMock->method('getBody')->willReturn($streamMock);
        $responseMock->method('getStatusCode')->willReturn(200);

        $clientMock->expects($this->once())
            ->method('get')
            ->with('scenarios/10001/executions', $this->callback(function($options) {
                return $options['query']['limit'] === 50;
            }))
            ->willReturn($responseMock);

        $adapter = new TestableMakeAdapter();
        $adapter->setClient($clientMock);

        $result = $adapter->sync($this->connection->id);

        $this->assertTrue($result->isSuccess);
        $this->assertEquals(2, $result->processedCount);
        $this->assertEquals(1, $result->failedCount);

        // Assert database logs
        $this->assertDatabaseHas('mcp_sync_logs', [
            'mcp_connection_id' => $this->connection->id,
            'direction' => 'inbound',
            'entity_type' => 'make_scenario_execution',
            'entity_id' => 'exec_fail_1',
            'status' => 'failed',
            'error_message' => 'Scenario failed during execution'
        ]);

        $this->assertDatabaseHas('mcp_sync_logs', [
            'mcp_connection_id' => $this->connection->id,
            'direction' => 'inbound',
            'entity_type' => 'make_scenario_execution',
            'entity_id' => 'exec_success_1',
            'status' => 'success'
        ]);
    }

    public function test_push_triggers_webhook_scenario_outbound()
    {
        $clientMock = $this->createMock(Client::class);
        $responseMock = $this->createMock(ResponseInterface::class);
        $streamMock = $this->createMock(StreamInterface::class);

        $streamMock->method('getContents')->willReturn('Accepted');
        $responseMock->method('getBody')->willReturn($streamMock);
        $responseMock->method('getStatusCode')->willReturn(200);

        $clientMock->expects($this->once())
            ->method('post')
            ->with('https://hook.make.com/task_completed_hook', $this->callback(function($options) {
                return $options['json']['task_id'] === 'task_123';
            }))
            ->willReturn($responseMock);

        $adapter = new TestableMakeAdapter();
        $adapter->setClient($clientMock);

        $result = $adapter->push($this->connection->id, [
            'event_type' => 'task_completed',
            'payload' => [
                'task_id' => 'task_123',
                'title' => 'Optimize database indexes'
            ]
        ]);

        $this->assertTrue($result->isSuccess);
        $this->assertEquals(1, $result->processedCount);

        $this->assertDatabaseHas('mcp_sync_logs', [
            'mcp_connection_id' => $this->connection->id,
            'direction' => 'outbound',
            'entity_type' => 'make_event_trigger',
            'entity_id' => 'task_completed',
            'status' => 'success'
        ]);
    }

    public function test_inbound_webhook_creates_task()
    {
        $payload = [
            'action' => 'create_task',
            'project_id' => $this->project->id,
            'title' => 'Make API integration test task',
            'description' => 'Verify inbound task creation from Make webhook',
            'assigned_role' => 'developer',
            'due_date' => date('Y-m-d H:i:s', strtotime('+3 days')),
            'assignee_email' => $this->user->email,
        ];

        $request = Request::create('/webhook/make', 'POST', $payload, [], [], [
            'HTTP_X-Make-Signature' => 'webhook_secret_key'
        ], json_encode($payload));

        $adapter = new MakeAdapter();
        $result = $adapter->handleWebhook($request);

        $this->assertTrue($result->isProcessed);
        $this->assertEquals('processed', $result->status);
        $this->assertNotNull($result->responsePayload['task_id']);

        // Verify task exists in DB
        $this->assertDatabaseHas('tasks', [
            'project_id' => $this->project->id,
            'title' => 'Make API integration test task',
            'assigned_to' => $this->user->id,
            'role_required' => 'developer'
        ]);
    }

    public function test_inbound_webhook_updates_metric()
    {
        $payload = [
            'action' => 'update_metric',
            'kpi_slug' => 'make_conversions_count',
            'value' => 45.0,
            'recorded_at' => date('Y-m-d H:i:s'),
            'project_id' => $this->project->id,
            'client_id' => $this->clientId,
        ];

        $request = Request::create('/webhook/make', 'POST', $payload, [], [], [
            'HTTP_X-Make-Signature' => 'webhook_secret_key'
        ], json_encode($payload));

        $adapter = new MakeAdapter();
        $result = $adapter->handleWebhook($request);

        $this->assertTrue($result->isProcessed);
        $this->assertEquals('processed', $result->status);

        // Verify KPI and snapshot exist
        $this->assertDatabaseHas('kpi_definitions', [
            'organization_id' => $this->organization->id,
            'slug' => 'make_conversions_count'
        ]);

        $this->assertDatabaseHas('metric_snapshots', [
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'value' => 45.0
        ]);
    }

    public function test_inbound_webhook_sends_notification()
    {
        $payload = [
            'action' => 'send_notification',
            'user_id' => $this->user->id,
            'message' => 'Congratulations! Make scenario executed with no errors.',
            'channel' => 'in_app',
            'title' => 'System Alert'
        ];

        $request = Request::create('/webhook/make', 'POST', $payload, [], [], [
            'HTTP_X-Make-Signature' => 'webhook_secret_key'
        ], json_encode($payload));

        $adapter = new MakeAdapter();
        $result = $adapter->handleWebhook($request);

        $this->assertTrue($result->isProcessed);

        // Verify notification in log
        $this->assertDatabaseHas('notifications_log', [
            'user_id' => $this->user->id,
            'channel' => 'in_app',
            'title' => 'System Alert',
            'body' => 'Congratulations! Make scenario executed with no errors.',
            'status' => 'pending'
        ]);
    }

    public function test_inbound_webhook_unauthorized_with_wrong_secret()
    {
        $payload = [
            'action' => 'create_task',
            'project_id' => $this->project->id,
            'title' => 'Intruder task'
        ];

        $request = Request::create('/webhook/make', 'POST', $payload, [], [], [
            'HTTP_X-Make-Signature' => 'wrong_secret_key'
        ], json_encode($payload));

        $adapter = new MakeAdapter();
        $result = $adapter->handleWebhook($request);

        $this->assertFalse($result->isProcessed);
        $this->assertEquals('failed', $result->status);
        $this->assertStringContainsString('Secret verification failed', $result->errorMessage);
    }
}

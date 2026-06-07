<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Modules\MCP\Adapters\MetaAdsAdapter;
use App\Modules\MCP\Models\McpConnection;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\Auth\Models\Organization;
use App\Modules\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class TestableMetaAdsAdapter extends MetaAdsAdapter
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

class MetaAdsAdapterTest extends TestCase
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

        $uuid = (string) \Illuminate\Support\Str::uuid();
        DB::table('clients')->insert([
            'id' => $uuid,
            'organization_id' => $this->organization->id,
            'name' => 'Client A',
            'email' => 'clienta@example.com',
            'company' => 'Acme Corp',
            'tier' => 'basic',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now()
        ]);
        $this->clientId = $uuid;

        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'client_id' => $this->clientId,
            'name' => 'Test Project',
            'slug' => 'test-project',
            'type' => 'seo',
            'status' => 'active',
        ]);

        $this->connection = McpConnection::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'provider' => 'meta_ads',
            'name' => 'Meta Ads Conn',
            'status' => 'active',
            'credentials' => [
                'access_token' => 'long_lived_token_123'
            ],
            'settings' => [
                'ad_account_ids' => ['act_123456789'],
                'client_id' => $this->clientId,
                'project_id' => $this->project->id,
                'metrics_to_pull' => ['impressions', 'clicks', 'spend'],
                'date_range' => 'yesterday'
            ]
        ]);
    }

    public function test_sync_pulls_insights_and_writes_to_snapshots()
    {
        $clientMock = $this->createMock(Client::class);
        $responseMock = $this->createMock(ResponseInterface::class);
        $streamMock = $this->createMock(StreamInterface::class);

        $insightsData = [
            'data' => [
                [
                    'campaign_id' => 'camp_999',
                    'campaign_name' => 'Summer Promo Ads',
                    'impressions' => '10000',
                    'clicks' => '350',
                    'spend' => '150.75',
                    'date_start' => date('Y-m-d', strtotime('yesterday')),
                    'date_stop' => date('Y-m-d', strtotime('yesterday'))
                ]
            ]
        ];

        $streamMock->method('getContents')->willReturn(json_encode($insightsData));
        $responseMock->method('getBody')->willReturn($streamMock);
        $responseMock->method('getStatusCode')->willReturn(200);

        $clientMock->expects($this->once())
            ->method('get')
            ->with($this->stringContains('act_123456789/insights'), $this->callback(function($options) {
                return $options['query']['level'] === 'campaign' && str_contains($options['query']['fields'], 'impressions');
            }))
            ->willReturn($responseMock);

        $adapter = new TestableMetaAdsAdapter();
        $adapter->setClient($clientMock);

        $result = $adapter->sync($this->connection->id);

        $this->assertTrue($result->isSuccess);
        // We sync 3 metrics (impressions, clicks, spend), so processedCount should be 3
        $this->assertEquals(3, $result->processedCount);

        // Verify KPIs were created
        $this->assertDatabaseHas('kpi_definitions', [
            'organization_id' => $this->organization->id,
            'slug' => 'meta_impressions'
        ]);

        // Verify snapshots were created
        $this->assertDatabaseHas('metric_snapshots', [
            'organization_id' => $this->organization->id,
            'source_external_id' => 'camp_999',
            'value' => 10000.0
        ]);

        $this->assertDatabaseHas('metric_snapshots', [
            'organization_id' => $this->organization->id,
            'source_external_id' => 'camp_999',
            'value' => 350.0
        ]);

        $this->assertDatabaseHas('metric_snapshots', [
            'organization_id' => $this->organization->id,
            'source_external_id' => 'camp_999',
            'value' => 150.75
        ]);
    }

    public function test_test_connection_returns_success_on_valid_credentials()
    {
        $clientMock = $this->createMock(Client::class);
        $responseMock = $this->createMock(ResponseInterface::class);
        $streamMock = $this->createMock(StreamInterface::class);

        $profileData = ['id' => '123456', 'name' => 'Demo User'];
        $streamMock->method('getContents')->willReturn(json_encode($profileData));
        $responseMock->method('getBody')->willReturn($streamMock);
        $responseMock->method('getStatusCode')->willReturn(200);

        $clientMock->expects($this->once())
            ->method('get')
            ->with('me?fields=id,name')
            ->willReturn($responseMock);

        $adapter = new TestableMetaAdsAdapter();
        $adapter->setClient($clientMock);

        $result = $adapter->testConnection(['access_token' => 'valid_token']);

        $this->assertTrue($result->isConnected);
    }

    public function test_handle_webhook_verification()
    {
        config()->set('services.meta.webhook_verify_token', 'dummy-verify-token');

        $request = Request::create('/webhook/meta', 'GET', [
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'dummy-verify-token',
            'hub_challenge' => '123456789'
        ]);

        $adapter = new MetaAdsAdapter();
        $result = $adapter->handleWebhook($request);

        $this->assertTrue($result->isProcessed);
        $this->assertEquals('123456789', $result->responsePayload['challenge']);
    }
}

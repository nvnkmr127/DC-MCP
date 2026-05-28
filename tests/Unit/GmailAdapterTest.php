<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Modules\MCP\Adapters\GmailAdapter;
use App\Modules\MCP\Models\McpConnection;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\ProjectManagement\Models\Task;
use App\Modules\Auth\Models\Organization;
use App\Modules\Auth\Models\User;
use App\Modules\DailyBriefing\Models\DailyBriefing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Google_Client;
use Google_Service_Gmail;
use Google_Service_Gmail_Resource_UsersMessages;
use Google_Service_Gmail_Resource_Users;
use Google_Service_Gmail_ListMessagesResponse;
use Google_Service_Gmail_Message;
use Google_Service_Gmail_MessagePart;
use Google_Service_Gmail_MessagePartHeader;
use Google_Service_Gmail_Profile;

class TestableGmailAdapter extends GmailAdapter
{
    public $mockedClient;
    public $mockedService;

    protected function getGoogleClient(array $credentials, ?McpConnection $connection = null): Google_Client
    {
        return $this->mockedClient;
    }

    protected function getGmailService(Google_Client $client): Google_Service_Gmail
    {
        return $this->mockedService;
    }
}

class GmailAdapterTest extends TestCase
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
            'provider' => 'gmail',
            'name' => 'Gmail Conn',
            'status' => 'active',
            'credentials' => [
                'access_token' => 'token123',
                'refresh_token' => 'refresh123',
                'expires_in' => 3600,
                'created' => time(),
            ],
            'settings' => [
                'labels_to_watch' => ['CLIENT'],
                'auto_create_tasks' => true,
                'linked_project_id' => $this->project->id
            ]
        ]);
    }

    public function test_sync_pulls_unread_emails_and_creates_tasks()
    {
        $clientMock = $this->createMock(Google_Client::class);
        $serviceMock = $this->createMock(Google_Service_Gmail::class);
        $messagesResourceMock = $this->createMock(Google_Service_Gmail_Resource_UsersMessages::class);
        $listResponseMock = $this->createMock(Google_Service_Gmail_ListMessagesResponse::class);
        
        $msgSummaryMock = $this->createMock(Google_Service_Gmail_Message::class);
        $msgSummaryMock->method('getId')->willReturn('msg123');

        $listResponseMock->method('getMessages')->willReturn([$msgSummaryMock]);

        $messageFullMock = $this->createMock(Google_Service_Gmail_Message::class);
        $messageFullMock->method('getId')->willReturn('msg123');
        $messageFullMock->method('getThreadId')->willReturn('thread123');
        $messageFullMock->method('getSnippet')->willReturn('Review needed for new landing page designs.');

        $payloadMock = $this->createMock(Google_Service_Gmail_MessagePart::class);
        
        $headerSubject = $this->createMock(Google_Service_Gmail_MessagePartHeader::class);
        $headerSubject->method('getName')->willReturn('Subject');
        $headerSubject->method('getValue')->willReturn('Feedback on Landing Page');

        $headerFrom = $this->createMock(Google_Service_Gmail_MessagePartHeader::class);
        $headerFrom->method('getName')->willReturn('From');
        $headerFrom->method('getValue')->willReturn('john@client.com');

        $headerDate = $this->createMock(Google_Service_Gmail_MessagePartHeader::class);
        $headerDate->method('getName')->willReturn('Date');
        $headerDate->method('getValue')->willReturn('Thu, 21 May 2026 14:00:00 +0000');

        $payloadMock->method('getHeaders')->willReturn([$headerSubject, $headerFrom, $headerDate]);
        $messageFullMock->method('getPayload')->willReturn($payloadMock);

        $messagesResourceMock->expects($this->once())
            ->method('listUsersMessages')
            ->with('me', $this->callback(function($params) {
                return str_contains($params['q'], 'label:"CLIENT"') && str_contains($params['q'], 'is:unread');
            }))
            ->willReturn($listResponseMock);

        $messagesResourceMock->expects($this->once())
            ->method('get')
            ->with('me', 'msg123', ['format' => 'full'])
            ->willReturn($messageFullMock);

        $messagesResourceMock->expects($this->once())
            ->method('modify')
            ->with('me', 'msg123', $this->isInstanceOf(\Google_Service_Gmail_ModifyMessageRequest::class));

        $serviceMock->users_messages = $messagesResourceMock;

        $adapter = new TestableGmailAdapter();
        $adapter->mockedClient = $clientMock;
        $adapter->mockedService = $serviceMock;

        $result = $adapter->sync($this->connection->id);

        $this->assertTrue($result->isSuccess);
        $this->assertEquals(1, $result->processedCount);

        $task = Task::where('project_id', $this->project->id)->first();
        $this->assertNotNull($task);
        $this->assertEquals('Feedback on Landing Page', $task->title);
        $this->assertEquals("Email from: john@client.com\n\nSnippet: Review needed for new landing page designs.", $task->description);
        $this->assertEquals('review', $task->type);
        $this->assertEquals('project_manager', $task->role_required);
        $this->assertEquals('thread123', $task->meta['gmail_thread_id'] ?? null);
    }

    public function test_sync_handles_threading_to_avoid_duplicate_tasks()
    {
        // Pre-create task with thread123
        $existingTask = Task::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'title' => 'Original Email Subject',
            'type' => 'review',
            'status' => 'backlog',
            'meta' => ['gmail_thread_id' => 'thread123']
        ]);

        $clientMock = $this->createMock(Google_Client::class);
        $serviceMock = $this->createMock(Google_Service_Gmail::class);
        $messagesResourceMock = $this->createMock(Google_Service_Gmail_Resource_UsersMessages::class);
        $listResponseMock = $this->createMock(Google_Service_Gmail_ListMessagesResponse::class);
        
        $msgSummaryMock = $this->createMock(Google_Service_Gmail_Message::class);
        $msgSummaryMock->method('getId')->willReturn('msg124');

        $listResponseMock->method('getMessages')->willReturn([$msgSummaryMock]);

        $messageFullMock = $this->createMock(Google_Service_Gmail_Message::class);
        $messageFullMock->method('getId')->willReturn('msg124');
        $messageFullMock->method('getThreadId')->willReturn('thread123'); // same thread

        $payloadMock = $this->createMock(Google_Service_Gmail_MessagePart::class);
        
        $headerSubject = $this->createMock(Google_Service_Gmail_MessagePartHeader::class);
        $headerSubject->method('getName')->willReturn('Subject');
        $headerSubject->method('getValue')->willReturn('Feedback on Landing Page (RE)');

        $headerFrom = $this->createMock(Google_Service_Gmail_MessagePartHeader::class);
        $headerFrom->method('getName')->willReturn('From');
        $headerFrom->method('getValue')->willReturn('john@client.com');

        $payloadMock->method('getHeaders')->willReturn([$headerSubject, $headerFrom]);
        $messageFullMock->method('getPayload')->willReturn($payloadMock);

        $messagesResourceMock->method('listUsersMessages')->willReturn($listResponseMock);
        $messagesResourceMock->method('get')->willReturn($messageFullMock);

        $serviceMock->users_messages = $messagesResourceMock;

        $adapter = new TestableGmailAdapter();
        $adapter->mockedClient = $clientMock;
        $adapter->mockedService = $serviceMock;

        $result = $adapter->sync($this->connection->id);

        $this->assertTrue($result->isSuccess);
        $this->assertEquals(1, $result->processedCount);

        // Assert that no new task was created in the database
        $this->assertEquals(1, Task::where('project_id', $this->project->id)->count());
    }

    public function test_push_sends_briefing_email()
    {
        $briefing = DailyBriefing::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'date' => '2026-05-21',
            'status' => 'ready',
            'digest_html' => '<p>Good morning! Here is your briefing.</p>',
            'digest_text' => 'Good morning! Here is your briefing.',
        ]);

        $clientMock = $this->createMock(Google_Client::class);
        $serviceMock = $this->createMock(Google_Service_Gmail::class);
        $messagesResourceMock = $this->createMock(Google_Service_Gmail_Resource_UsersMessages::class);

        $messagesResourceMock->expects($this->once())
            ->method('send')
            ->with('me', $this->isInstanceOf(Google_Service_Gmail_Message::class));

        $serviceMock->users_messages = $messagesResourceMock;

        $adapter = new TestableGmailAdapter();
        $adapter->mockedClient = $clientMock;
        $adapter->mockedService = $serviceMock;

        $result = $adapter->push($this->connection->id, [
            'entity_type' => 'briefing',
            'entity_id' => $briefing->id,
            'user_id' => $this->user->id,
        ]);

        $this->assertTrue($result->isSuccess);
        $this->assertEquals(1, $result->processedCount);

        // Verify notification log is created
        $this->assertDatabaseHas('notifications_log', [
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'channel' => 'email',
            'type' => 'briefing_ready',
            'status' => 'sent',
        ]);
    }

    public function test_get_client_emails_summary()
    {
        $clientMock = $this->createMock(Google_Client::class);
        $serviceMock = $this->createMock(Google_Service_Gmail::class);
        $messagesResourceMock = $this->createMock(Google_Service_Gmail_Resource_UsersMessages::class);
        $listResponseMock = $this->createMock(Google_Service_Gmail_ListMessagesResponse::class);
        
        $msgSummaryMock = $this->createMock(Google_Service_Gmail_Message::class);
        $msgSummaryMock->method('getId')->willReturn('msg_summary_123');

        $listResponseMock->method('getMessages')->willReturn([$msgSummaryMock]);

        $messageFullMock = $this->createMock(Google_Service_Gmail_Message::class);
        $messageFullMock->method('getId')->willReturn('msg_summary_123');
        $messageFullMock->method('getThreadId')->willReturn('thread_summary_123');
        $messageFullMock->method('getSnippet')->willReturn('This is an email snippet.');
        $messageFullMock->method('getLabelIds')->willReturn(['INBOX', 'CLIENT']);

        $payloadMock = $this->createMock(Google_Service_Gmail_MessagePart::class);
        
        $headerSubject = $this->createMock(Google_Service_Gmail_MessagePartHeader::class);
        $headerSubject->method('getName')->willReturn('Subject');
        $headerSubject->method('getValue')->willReturn('Ad campaign results');

        $headerFrom = $this->createMock(Google_Service_Gmail_MessagePartHeader::class);
        $headerFrom->method('getName')->willReturn('From');
        $headerFrom->method('getValue')->willReturn('mark@client.com');

        $payloadMock->method('getHeaders')->willReturn([$headerSubject, $headerFrom]);
        $messageFullMock->method('getPayload')->willReturn($payloadMock);

        $messagesResourceMock->method('listUsersMessages')->willReturn($listResponseMock);
        $messagesResourceMock->method('get')->willReturn($messageFullMock);

        $serviceMock->users_messages = $messagesResourceMock;

        $adapter = new TestableGmailAdapter();
        $adapter->mockedClient = $clientMock;
        $adapter->mockedService = $serviceMock;

        $summary = $adapter->getClientEmailsSummary(24);

        $this->assertCount(1, $summary);
        $this->assertEquals('mark@client.com', $summary[0]['from']);
        $this->assertEquals('Ad campaign results', $summary[0]['subject']);
        $this->assertEquals('This is an email snippet.', $summary[0]['snippet']);
        $this->assertEquals('thread_summary_123', $summary[0]['thread_id']);
        $this->assertContains('CLIENT', $summary[0]['labels']);
    }

    public function test_test_connection_returns_success_on_valid_credentials()
    {
        $clientMock = $this->createMock(Google_Client::class);
        $serviceMock = $this->createMock(Google_Service_Gmail::class);
        $usersResourceMock = $this->createMock(Google_Service_Gmail_Resource_Users::class);

        $usersResourceMock->expects($this->once())
            ->method('getProfile')
            ->with('me')
            ->willReturn(new Google_Service_Gmail_Profile());

        $serviceMock->users = $usersResourceMock;

        $adapter = new TestableGmailAdapter();
        $adapter->mockedClient = $clientMock;
        $adapter->mockedService = $serviceMock;

        $result = $adapter->testConnection($this->connection->credentials);

        $this->assertTrue($result->isConnected);
        $this->assertNull($result->errorMessage);
    }
}

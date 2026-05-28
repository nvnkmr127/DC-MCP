<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Modules\MCP\Adapters\GoogleCalendarAdapter;
use App\Modules\MCP\Models\McpConnection;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\ProjectManagement\Models\Milestone;
use App\Modules\ProjectManagement\Models\Task;
use App\Modules\Auth\Models\Organization;
use App\Modules\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Google_Client;
use Google_Service_Calendar;
use Google_Service_Calendar_Events;
use Google_Service_Calendar_Event;
use Google_Service_Calendar_EventDateTime;
use Google_Service_Calendar_Resource_Events;
use Google_Service_Calendar_Resource_CalendarList;

class TestableGoogleCalendarAdapter extends GoogleCalendarAdapter
{
    public $mockedClient;
    public $mockedService;

    protected function getGoogleClient(array $credentials, ?McpConnection $connection = null): Google_Client
    {
        return $this->mockedClient;
    }

    protected function getCalendarService(Google_Client $client): Google_Service_Calendar
    {
        return $this->mockedService;
    }
}

class GoogleCalendarAdapterTest extends TestCase
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
            'provider' => 'google_calendar',
            'name' => 'Google Cal',
            'status' => 'active',
            'credentials' => [
                'access_token' => 'token123',
                'refresh_token' => 'refresh123',
                'expires_in' => 3600,
                'created' => time(),
            ],
            'settings' => [
                'calendar_ids' => ['primary'],
                'project_mapping' => [
                    'primary' => $this->project->id
                ]
            ]
        ]);
    }

    public function test_sync_pulls_and_creates_task_when_summary_has_task_tag()
    {
        $clientMock = $this->createMock(Google_Client::class);
        $serviceMock = $this->createMock(Google_Service_Calendar::class);
        $eventsResourceMock = $this->createMock(Google_Service_Calendar_Resource_Events::class);
        $eventsListMock = $this->createMock(Google_Service_Calendar_Events::class);
        
        $eventMock = $this->createMock(Google_Service_Calendar_Event::class);
        $eventMock->method('getId')->willReturn('evt_task_123');
        $eventMock->method('getSummary')->willReturn('[TASK] Write Blog Post');
        $eventMock->method('getDescription')->willReturn('Post details...');

        $startMock = $this->createMock(Google_Service_Calendar_EventDateTime::class);
        $startMock->method('getDateTime')->willReturn('2026-05-30T10:00:00Z');
        $eventMock->method('getStart')->willReturn($startMock);

        $eventsListMock->method('getItems')->willReturn([$eventMock]);
        $eventsResourceMock->method('listEvents')->willReturn($eventsListMock);
        $serviceMock->events = $eventsResourceMock;

        $adapter = new TestableGoogleCalendarAdapter();
        $adapter->mockedClient = $clientMock;
        $adapter->mockedService = $serviceMock;

        $result = $adapter->sync($this->connection->id);

        $this->assertTrue($result->isSuccess);
        $this->assertEquals(1, $result->processedCount);

        $task = Task::where('project_id', $this->project->id)->first();
        $this->assertNotNull($task);
        $this->assertEquals('Write Blog Post', $task->title);
        $this->assertEquals('Post details...', $task->description);
        $this->assertEquals('2026-05-30', $task->due_date->format('Y-m-d'));
    }

    public function test_sync_updates_milestone_due_date_on_matching_summary()
    {
        $milestone = Milestone::create([
            'project_id' => $this->project->id,
            'name' => 'Kickoff Milestone',
            'due_date' => '2026-05-25',
            'status' => 'pending',
        ]);

        $clientMock = $this->createMock(Google_Client::class);
        $serviceMock = $this->createMock(Google_Service_Calendar::class);
        $eventsResourceMock = $this->createMock(Google_Service_Calendar_Resource_Events::class);
        $eventsListMock = $this->createMock(Google_Service_Calendar_Events::class);
        
        $eventMock = $this->createMock(Google_Service_Calendar_Event::class);
        $eventMock->method('getId')->willReturn('evt_milestone_123');
        $eventMock->method('getSummary')->willReturn('Kickoff Milestone');

        $startMock = $this->createMock(Google_Service_Calendar_EventDateTime::class);
        $startMock->method('getDateTime')->willReturn('2026-06-05T10:00:00Z');
        $eventMock->method('getStart')->willReturn($startMock);

        $eventsListMock->method('getItems')->willReturn([$eventMock]);
        $eventsResourceMock->method('listEvents')->willReturn($eventsListMock);
        $serviceMock->events = $eventsResourceMock;

        $adapter = new TestableGoogleCalendarAdapter();
        $adapter->mockedClient = $clientMock;
        $adapter->mockedService = $serviceMock;

        $result = $adapter->sync($this->connection->id);

        $this->assertTrue($result->isSuccess);
        $this->assertEquals(1, $result->processedCount);

        $milestone->refresh();
        $this->assertEquals('2026-06-05', $milestone->due_date->format('Y-m-d'));
    }

    public function test_push_creates_new_calendar_event_for_task()
    {
        $task = Task::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'title' => 'Design wireframe',
            'status' => 'todo',
            'due_date' => '2026-06-15',
        ]);

        $clientMock = $this->createMock(Google_Client::class);
        $serviceMock = $this->createMock(Google_Service_Calendar::class);
        $eventsResourceMock = $this->createMock(Google_Service_Calendar_Resource_Events::class);
        
        $createdEventMock = $this->createMock(Google_Service_Calendar_Event::class);
        $createdEventMock->method('getId')->willReturn('new_google_evt_id');

        $eventsResourceMock->expects($this->once())
            ->method('insert')
            ->with('primary', $this->isInstanceOf(Google_Service_Calendar_Event::class))
            ->willReturn($createdEventMock);

        $serviceMock->events = $eventsResourceMock;

        $adapter = new TestableGoogleCalendarAdapter();
        $adapter->mockedClient = $clientMock;
        $adapter->mockedService = $serviceMock;

        $result = $adapter->push($this->connection->id, [
            'entity_type' => 'task',
            'entity_id' => $task->id,
        ]);

        $this->assertTrue($result->isSuccess);
        $this->assertEquals(1, $result->processedCount);

        $task->refresh();
        $this->assertEquals('new_google_evt_id', $task->meta['google_event_id'] ?? null);
    }

    public function test_test_connection_returns_success_on_valid_credentials()
    {
        $clientMock = $this->createMock(Google_Client::class);
        $serviceMock = $this->createMock(Google_Service_Calendar::class);
        $calendarListResourceMock = $this->createMock(Google_Service_Calendar_Resource_CalendarList::class);

        $calendarListResourceMock->expects($this->once())
            ->method('listCalendarList')
            ->willReturn(new \stdClass());

        $serviceMock->calendarList = $calendarListResourceMock;

        $adapter = new TestableGoogleCalendarAdapter();
        $adapter->mockedClient = $clientMock;
        $adapter->mockedService = $serviceMock;

        $result = $adapter->testConnection($this->connection->credentials);

        $this->assertTrue($result->isConnected);
        $this->assertNull($result->errorMessage);
    }
}

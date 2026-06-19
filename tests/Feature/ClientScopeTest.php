<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\ProjectManagement\Models\Client;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\Auth\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_cannot_access_other_clients_projects(): void
    {
        $orgId = \Illuminate\Support\Str::uuid()->toString();
        $org = Organization::forceCreate(['id' => $orgId, 'name' => 'Test Org', 'slug' => 'test-org']);

        $clientA = Client::forceCreate(['id' => \Illuminate\Support\Str::uuid()->toString(), 'organization_id' => $org->id, 'name' => 'Client A']);
        $clientB = Client::forceCreate(['id' => \Illuminate\Support\Str::uuid()->toString(), 'organization_id' => $org->id, 'name' => 'Client B']);

        $userClientA = User::forceCreate([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'organization_id' => $org->id,
            'client_id'       => $clientA->id,
            'role'            => 'client',
            'name'            => 'User A',
            'email'           => 'a@example.com',
            'password'        => 'password'
        ]);

        $userClientB = User::forceCreate([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'organization_id' => $org->id,
            'client_id'       => $clientB->id,
            'role'            => 'client',
            'name'            => 'User B',
            'email'           => 'b@example.com',
            'password'        => 'password'
        ]);

        $projectA = Project::forceCreate([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'organization_id' => $org->id,
            'client_id'       => $clientA->id,
            'name'            => 'Project A',
            'status'          => 'active'
        ]);

        $projectB = Project::forceCreate([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'organization_id' => $org->id,
            'client_id'       => $clientB->id,
            'name'            => 'Project B',
            'status'          => 'active'
        ]);

        $this->actingAs($userClientA);
        $projects = Project::all();

        $this->assertCount(1, $projects);
        $this->assertEquals($projectA->id, $projects->first()->id);
        $this->assertFalse($projects->contains($projectB));

        $this->actingAs($userClientB);
        $projects = Project::all();

        $this->assertCount(1, $projects);
        $this->assertEquals($projectB->id, $projects->first()->id);
    }

    public function test_internal_users_can_see_all_clients_projects(): void
    {
        $orgId = \Illuminate\Support\Str::uuid()->toString();
        $org = Organization::forceCreate(['id' => $orgId, 'name' => 'Test Org', 'slug' => 'test-org']);
        
        $clientA = Client::forceCreate(['id' => \Illuminate\Support\Str::uuid()->toString(), 'organization_id' => $org->id, 'name' => 'Client A']);
        $clientB = Client::forceCreate(['id' => \Illuminate\Support\Str::uuid()->toString(), 'organization_id' => $org->id, 'name' => 'Client B']);

        $projectManager = User::forceCreate([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'organization_id' => $org->id,
            'role'            => 'project_manager',
            'name'            => 'PM',
            'email'           => 'pm@example.com',
            'password'        => 'password'
        ]);

        Project::forceCreate(['id' => \Illuminate\Support\Str::uuid()->toString(), 'organization_id' => $org->id, 'client_id' => $clientA->id, 'name' => 'P1', 'status' => 'active']);
        Project::forceCreate(['id' => \Illuminate\Support\Str::uuid()->toString(), 'organization_id' => $org->id, 'client_id' => $clientB->id, 'name' => 'P2', 'status' => 'active']);

        $this->actingAs($projectManager);

        $projects = Project::all();
        $this->assertCount(2, $projects);
    }
}

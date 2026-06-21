<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\Organization;
use App\Modules\Auth\Models\User;
use App\Modules\ProjectManagement\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that RLS prevents accessing data from other tenants at the database level.
     */
    public function test_cross_tenant_data_is_isolated_at_database_level(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Database-level RLS isolation tests require PostgreSQL.');
        }

        // Create Organization A and User A
        $orgA = Organization::create(['name' => 'Org A']);
        $userA = User::factory()->create(['organization_id' => $orgA->id]);

        // Create Organization B and User B
        $orgB = Organization::create(['name' => 'Org B']);
        $userB = User::factory()->create(['organization_id' => $orgB->id]);

        // Act as super-admin / bypass RLS to create data initially (simulating typical factory setup)
        DB::statement("SET app.bypass_rls = 'on'");
        
        $clientA = Client::create([
            'organization_id' => $orgA->id,
            'name' => 'Client A',
            'email' => 'clientA@example.com',
            'company' => 'Company A',
        ]);
        
        $clientB = Client::create([
            'organization_id' => $orgB->id,
            'name' => 'Client B',
            'email' => 'clientB@example.com',
            'company' => 'Company B',
        ]);

        // Scenario 1: User A logs in. They should only see Client A.
        // We simulate the middleware by explicitly setting the session variables.
        $this->actingAs($userA);
        DB::statement("SET app.current_tenant_id = '{$orgA->id}'");
        DB::statement("SET app.bypass_rls = 'off'");

        // Even without the global application scope, the database should reject Org B's data
        $clients = Client::withoutGlobalScopes()->get();
        
        $this->assertCount(1, $clients, 'User A should only see 1 client via RLS.');
        $this->assertEquals($clientA->id, $clients->first()->id);

        // A raw query should also fail to find Client B
        $rawClientB = DB::select('SELECT * FROM clients WHERE id = ?', [$clientB->id]);
        $this->assertEmpty($rawClientB, 'Raw SQL query should not return other tenant data.');

        // Scenario 2: User B logs in. They should only see Client B.
        $this->actingAs($userB);
        DB::statement("SET app.current_tenant_id = '{$orgB->id}'");
        DB::statement("SET app.bypass_rls = 'off'");

        $clientsB = Client::withoutGlobalScopes()->get();
        
        $this->assertCount(1, $clientsB, 'User B should only see 1 client via RLS.');
        $this->assertEquals($clientB->id, $clientsB->first()->id);
    }
}

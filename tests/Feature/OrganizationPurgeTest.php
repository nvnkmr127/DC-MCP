<?php

namespace Tests\Feature;

use App\Jobs\PurgeOrganizationDataJob;
use App\Modules\Auth\Models\Organization;
use App\Modules\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrganizationPurgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_purge_organization_dispatches_job_and_logs_out()
    {
        Queue::fake();

        $org = Organization::create(['name' => 'Org to Purge']);
        $user = User::factory()->create(['organization_id' => $org->id]);

        $response = $this->actingAs($user)->post(route('web.settings.organization.purge'));

        $response->assertRedirect('/login');
        $this->assertGuest();
        Queue::assertPushed(PurgeOrganizationDataJob::class);
    }
}

<?php

namespace Tests\Feature;

use App\Jobs\ExportOrganizationDataJob;
use App\Modules\Auth\Models\Organization;
use App\Modules\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrganizationExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_organization_data_dispatches_job()
    {
        Queue::fake();

        $org = Organization::create(['name' => 'Org A']);
        $user = User::factory()->create(['organization_id' => $org->id]);

        $response = $this->actingAs($user)->post(route('web.settings.organization.export'));

        $response->assertSessionHas('success');
        Queue::assertPushed(ExportOrganizationDataJob::class);
    }

    public function test_download_organization_export_unauthorized_access()
    {
        $orgA = Organization::create(['name' => 'Org A']);
        $userA = User::factory()->create(['organization_id' => $orgA->id]);

        $orgB = Organization::create(['name' => 'Org B']);
        $userB = User::factory()->create(['organization_id' => $orgB->id]);

        // Attempting to download Org A's export using User B
        $response = $this->actingAs($userB)->get(route('web.settings.organization.export.download', ['filename' => "{$orgA->id}_export.zip"]));

        $response->assertStatus(403);
    }
}

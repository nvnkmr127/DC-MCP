<?php

namespace Tests\Feature;

use App\Jobs\PurgeOrganizationDataJob;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class TenantJobRateLimitingTest extends TestCase
{
    public function test_tenant_job_limiter_extracts_organization_id()
    {
        $orgId = 'test-org-123';
        $job = new PurgeOrganizationDataJob($orgId);

        $limiter = RateLimiter::limiter('tenant-jobs');
        
        // The limiter should return a Limit instance or array of Limit instances
        $limits = $limiter($job);
        
        if (!is_array($limits)) {
            $limits = [$limits];
        }
        
        $this->assertEquals($orgId, $limits[0]->key);
        $this->assertEquals(100, $limits[0]->maxAttempts);
    }
}

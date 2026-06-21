<?php

namespace App\Shared\Traits;

use Illuminate\Queue\Middleware\RateLimited;

trait RateLimitsTenantJobs
{
    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited('tenant-jobs')];
    }
}

<?php

namespace App\Modules\Revenue\Providers;

use App\Shared\Providers\ModuleServiceProvider;
use App\Modules\Revenue\Services\ClientHealthService;
use App\Modules\Revenue\Services\FinancialService;
use App\Modules\Revenue\Services\OnboardingService;
use App\Modules\Revenue\Services\RevenueService;

class RevenueServiceProvider extends ModuleServiceProvider
{
    protected function getModuleDir(): string
    {
        return dirname(__DIR__);
    }

    protected function getModuleName(): string
    {
        return 'Revenue';
    }

    public function register(): void
    {
        $this->app->singleton(ClientHealthService::class);
        $this->app->singleton(FinancialService::class);
        $this->app->singleton(OnboardingService::class);
        $this->app->singleton(RevenueService::class);
    }
}

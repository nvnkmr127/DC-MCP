<?php

namespace App\Modules\ClientPortal\Providers;

use App\Modules\ClientPortal\Services\PortalService;
use App\Shared\Providers\ModuleServiceProvider;

class ClientPortalServiceProvider extends ModuleServiceProvider
{
    protected function getModuleDir(): string
    {
        return dirname(__DIR__);
    }

    protected function getModuleName(): string
    {
        return 'ClientPortal';
    }

    public function register(): void
    {
        $this->app->singleton(PortalService::class);
    }

    public function boot(): void
    {
        parent::boot();
    }
}

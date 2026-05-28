<?php

namespace App\Modules\DailyBriefing\Providers;

use App\Shared\Providers\ModuleServiceProvider;

class DailyBriefingServiceProvider extends ModuleServiceProvider
{
    protected function getModuleDir(): string
    {
        return dirname(__DIR__);
    }

    protected function getModuleName(): string
    {
        return 'DailyBriefing';
    }

    public function register(): void
    {
        // Bind services here
    }
}

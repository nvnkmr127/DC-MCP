<?php

namespace App\Modules\Automation\Providers;

use App\Shared\Providers\ModuleServiceProvider;

class AutomationServiceProvider extends ModuleServiceProvider
{
    protected function getModuleDir(): string
    {
        return dirname(__DIR__);
    }

    protected function getModuleName(): string
    {
        return 'Automation';
    }
}

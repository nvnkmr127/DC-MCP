<?php

namespace App\Modules\HR\Providers;

use App\Shared\Providers\ModuleServiceProvider;

class HRServiceProvider extends ModuleServiceProvider
{
    protected function getModuleDir(): string
    {
        return dirname(__DIR__);
    }

    protected function getModuleName(): string
    {
        return 'HR';
    }
}

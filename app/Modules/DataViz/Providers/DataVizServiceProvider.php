<?php

namespace App\Modules\DataViz\Providers;

use App\Shared\Providers\ModuleServiceProvider;

class DataVizServiceProvider extends ModuleServiceProvider
{
    protected function getModuleDir(): string
    {
        return dirname(__DIR__);
    }

    protected function getModuleName(): string
    {
        return 'DataViz';
    }

    public function register(): void
    {
        // Bind services here
    }
}

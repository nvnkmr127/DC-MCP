<?php

namespace App\Modules\Reporting\Providers;

use App\Shared\Providers\ModuleServiceProvider;

class ReportingServiceProvider extends ModuleServiceProvider
{
    protected function getModuleDir(): string
    {
        return dirname(__DIR__);
    }

    protected function getModuleName(): string
    {
        return 'Reporting';
    }

    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Modules\Reporting\Console\Commands\RunScheduledReports::class,
            ]);
        }
    }

    public function register(): void {}
}

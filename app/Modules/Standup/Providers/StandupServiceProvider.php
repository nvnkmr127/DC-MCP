<?php

namespace App\Modules\Standup\Providers;

use App\Shared\Providers\ModuleServiceProvider;
use App\Modules\Standup\Console\Commands\SendStandupReminderCommand;

class StandupServiceProvider extends ModuleServiceProvider
{
    protected function getModuleDir(): string
    {
        return dirname(__DIR__);
    }

    protected function getModuleName(): string
    {
        return 'Standup';
    }

    public function register(): void {}

    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([
                SendStandupReminderCommand::class,
            ]);
        }
    }
}

<?php

namespace App\Modules\TaskEngine\Providers;

use App\Shared\Providers\ModuleServiceProvider;
use App\Modules\TaskEngine\Services\TaskSpawnerService;
use App\Modules\TaskEngine\Services\SlaEngine;
use App\Modules\TaskEngine\Services\TaskDependencyService;
use App\Modules\TaskEngine\Console\Commands\CheckSlasCommand;

class TaskEngineServiceProvider extends ModuleServiceProvider
{
    /**
     * Get the directory path of the module.
     */
    protected function getModuleDir(): string
    {
        return dirname(__DIR__);
    }

    /**
     * Get the name of the module.
     */
    protected function getModuleName(): string
    {
        return 'TaskEngine';
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TaskSpawnerService::class);
        $this->app->singleton(SlaEngine::class);
        $this->app->singleton(TaskDependencyService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([
                CheckSlasCommand::class,
            ]);
        }
    }
}

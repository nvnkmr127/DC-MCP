<?php

namespace App\Modules\Notifications\Providers;

use App\Shared\Providers\ModuleServiceProvider;
use App\Modules\Notifications\Services\NotificationService;

class NotificationsServiceProvider extends ModuleServiceProvider
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
        return 'Notifications';
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(NotificationService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();
    }
}

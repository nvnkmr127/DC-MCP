<?php

namespace App\Modules\Auth\Providers;

use App\Shared\Providers\ModuleServiceProvider;
use App\Modules\Auth\Middleware\RoleMiddleware;
use App\Modules\Auth\Middleware\PermissionMiddleware;
use App\Modules\Auth\Middleware\EnsureOrganizationContext;

class AuthServiceProvider extends ModuleServiceProvider
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
        return 'Auth';
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Register middleware aliases
        $router = $this->app['router'];
        $router->aliasMiddleware('role', RoleMiddleware::class);
        $router->aliasMiddleware('permission', PermissionMiddleware::class);
        $router->aliasMiddleware('org.context', EnsureOrganizationContext::class);
    }
}

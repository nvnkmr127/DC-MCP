<?php

namespace App\Shared\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

abstract class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Get the directory path of the module.
     */
    abstract protected function getModuleDir(): string;

    /**
     * Get the name of the module.
     */
    abstract protected function getModuleName(): string;

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $moduleDir = $this->getModuleDir();
        $moduleName = strtolower($this->getModuleName());

        // Load migrations from Database/Migrations
        if (is_dir($moduleDir . '/Database/Migrations')) {
            $this->loadMigrationsFrom($moduleDir . '/Database/Migrations');
        }

        // Load views from Resources/views
        if (is_dir($moduleDir . '/Resources/views')) {
            $this->loadViewsFrom($moduleDir . '/Resources/views', $moduleName);
        }

        // Register routes
        $this->registerRoutes($moduleDir);
    }

    /**
     * Register module routes.
     */
    protected function registerRoutes(string $moduleDir): void
    {
        $namespace = $this->getNamespace();

        // API routes (normally prefixed with api/v1 and under auth:sanctum)
        if (file_exists($moduleDir . '/routes/api.php')) {
            Route::prefix('api')
                ->middleware(['api', 'auth:sanctum'])
                ->namespace($namespace . '\\Http\\Controllers')
                ->group($moduleDir . '/routes/api.php');
        }

        // Web routes
        if (file_exists($moduleDir . '/routes/web.php')) {
            Route::middleware(['web'])
                ->namespace($namespace . '\\Http\\Controllers')
                ->group($moduleDir . '/routes/web.php');
        }
    }

    /**
     * Get the module root namespace.
     */
    protected function getNamespace(): string
    {
        $class = get_class($this);
        $providerNamespace = substr($class, 0, strrpos($class, '\\'));
        return substr($providerNamespace, 0, strrpos($providerNamespace, '\\'));
    }
}

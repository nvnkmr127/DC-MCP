<?php

namespace App\Modules\MCP\Providers;

use App\Shared\Providers\ModuleServiceProvider;
use App\Modules\MCP\Events\McpSyncCompleted;
use App\Modules\MCP\Events\McpSyncFailed;
use App\Modules\MCP\Listeners\HandleMcpSyncCompleted;
use App\Modules\MCP\Listeners\HandleMcpSyncFailed;
use Illuminate\Support\Facades\Event;

class MCPServiceProvider extends ModuleServiceProvider
{
    /**
     * Get the directory path of the module.
     *
     * @return string
     */
    protected function getModuleDir(): string
    {
        return dirname(__DIR__);
    }

    /**
     * Get the name of the module.
     *
     * @return string
     */
    protected function getModuleName(): string
    {
        return 'MCP';
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        // Any bindings for adapters can be declared here.
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        parent::boot();

        Event::listen(McpSyncCompleted::class, HandleMcpSyncCompleted::class);
        Event::listen(McpSyncFailed::class, HandleMcpSyncFailed::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Modules\MCP\Console\Commands\CheckProviderUpdatesCommand::class,
            ]);
        }
    }
}

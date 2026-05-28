<?php

namespace App\Modules\ProjectManagement\Providers;

use App\Shared\Providers\ModuleServiceProvider;
use App\Modules\ProjectManagement\Services\ProjectService;
use App\Modules\ProjectManagement\Services\TaskService;
use App\Modules\ProjectManagement\Services\SprintService;
use App\Modules\ProjectManagement\Events\ProjectCreated;
use App\Modules\ProjectManagement\Events\TaskStatusChanged;
use App\Modules\ProjectManagement\Events\TaskAssigned;
use App\Modules\ProjectManagement\Listeners\NotifyOnProjectCreated;
use App\Modules\ProjectManagement\Listeners\NotifyOnTaskStatusChanged;
use App\Modules\ProjectManagement\Listeners\NotifyOnTaskAssigned;
use Illuminate\Support\Facades\Event;

class ProjectManagementServiceProvider extends ModuleServiceProvider
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
        return 'ProjectManagement';
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ProjectService::class);
        $this->app->singleton(TaskService::class);
        $this->app->singleton(SprintService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        Event::listen(ProjectCreated::class, NotifyOnProjectCreated::class);
        Event::listen(TaskStatusChanged::class, NotifyOnTaskStatusChanged::class);
        Event::listen(TaskAssigned::class, NotifyOnTaskAssigned::class);
    }
}

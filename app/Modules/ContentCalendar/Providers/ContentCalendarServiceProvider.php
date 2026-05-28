<?php

namespace App\Modules\ContentCalendar\Providers;

use App\Shared\Providers\ModuleServiceProvider;

class ContentCalendarServiceProvider extends ModuleServiceProvider
{
    protected function getModuleDir(): string
    {
        return dirname(__DIR__);
    }

    protected function getModuleName(): string
    {
        return 'ContentCalendar';
    }

    public function register(): void {}

    public function boot(): void
    {
        parent::boot();
    }
}

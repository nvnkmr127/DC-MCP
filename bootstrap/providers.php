<?php

use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    App\Modules\Auth\Providers\AuthServiceProvider::class,
    App\Modules\MCP\Providers\MCPServiceProvider::class,
    App\Modules\ProjectManagement\Providers\ProjectManagementServiceProvider::class,
    App\Modules\DailyBriefing\Providers\DailyBriefingServiceProvider::class,
    App\Modules\Reporting\Providers\ReportingServiceProvider::class,
    App\Modules\DataViz\Providers\DataVizServiceProvider::class,
    App\Modules\TaskEngine\Providers\TaskEngineServiceProvider::class,
    App\Modules\Notifications\Providers\NotificationsServiceProvider::class,
    App\Modules\ContentCalendar\Providers\ContentCalendarServiceProvider::class,
    App\Modules\ClientPortal\Providers\ClientPortalServiceProvider::class,
];

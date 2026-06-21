<?php

return [
    App\Modules\Auth\Providers\AuthServiceProvider::class,
    App\Modules\Automation\Providers\AutomationServiceProvider::class,
    App\Modules\ClientPortal\Providers\ClientPortalServiceProvider::class,
    App\Modules\ContentCalendar\Providers\ContentCalendarServiceProvider::class,
    App\Modules\DailyBriefing\Providers\DailyBriefingServiceProvider::class,
    App\Modules\DataViz\Providers\DataVizServiceProvider::class,
    App\Modules\HR\Providers\HRServiceProvider::class,
    App\Modules\MCP\Providers\MCPServiceProvider::class,
    App\Modules\Notifications\Providers\NotificationsServiceProvider::class,
    App\Modules\ProjectManagement\Providers\ProjectManagementServiceProvider::class,
    App\Modules\Reporting\Providers\ReportingServiceProvider::class,
    App\Modules\Revenue\Providers\RevenueServiceProvider::class,
    App\Modules\Standup\Providers\StandupServiceProvider::class,
    App\Modules\TaskEngine\Providers\TaskEngineServiceProvider::class,
    App\Providers\AppServiceProvider::class,
    App\Providers\HorizonServiceProvider::class,
];

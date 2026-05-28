<?php

namespace App\Modules\DailyBriefing\Services;

use App\Modules\Auth\Models\User;
use App\Modules\ProjectManagement\Models\Task;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\MCP\Models\McpConnection;
use App\Modules\MCP\Adapters\GoogleCalendarAdapter;
use App\Modules\MCP\Adapters\GmailAdapter;
use App\Modules\MCP\Adapters\NotionAdapter;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BriefingDataCollector
{
    /**
     * Collect data for a user's daily briefing.
     */
    public function collect(User $user, Carbon $date): array
    {
        $orgId = $user->organization_id;
        $userId = $user->id;

        // Determine user roles
        $roles = $user->roles->pluck('slug')->toArray();
        $isCeo = in_array('ceo', $roles);
        $isPm = in_array('project_manager', $roles);
        $isAnalyst = in_array('analyst', $roles);
        $isDev = in_array('developer', $roles);

        // 1. Tasks
        $tasksQuery = Task::where('assigned_to', $userId);
        
        $dueToday = (clone $tasksQuery)->whereDate('due_date', $date)
            ->whereNotIn('status', ['done', 'cancelled'])
            ->get()
            ->toArray();

        $overdue = (clone $tasksQuery)->whereDate('due_date', '<', $date)
            ->whereNotIn('status', ['done', 'cancelled'])
            ->get()
            ->toArray();

        $completedYesterday = (clone $tasksQuery)->whereDate('completed_at', $date->copy()->subDay())
            ->where('status', 'done')
            ->get()
            ->toArray();

        // SLA Warnings: Active tasks assigned to user where deadline is within 4 hours
        $slaWarning = [];
        $activeTasks = Task::where('assigned_to', $userId)
            ->whereNotIn('status', ['done', 'cancelled'])
            ->whereNotNull('sla_hours')
            ->get();

        foreach ($activeTasks as $task) {
            $deadline = $task->created_at->addHours($task->sla_hours);
            $warningTime = $deadline->copy()->subHours(4);
            if (now()->greaterThanOrEqualTo($warningTime) && now()->lessThan($deadline)) {
                $slaWarning[] = $task->toArray();
            }
        }

        // 2. Calendar (Google Calendar)
        $calendarEvents = [];
        $calendarConnection = McpConnection::where('organization_id', $orgId)
            ->where('provider', 'google_calendar')
            ->where('status', 'active')
            ->first();

        if ($calendarConnection) {
            try {
                $calendarAdapter = app(GoogleCalendarAdapter::class);
                $calendarEvents = $calendarAdapter->getTodayEvents($calendarConnection);
            } catch (\Exception $e) {
                // Ignore calendar API errors
            }
        }

        // 3. Projects
        $dueSoonProjects = [];
        $staleProjects = [];
        $staleThreshold = $date->copy()->subDays(3);

        if ($isPm || $isCeo) {
            // Projects managed by the user or all org projects for CEO
            $projectsQuery = Project::where('organization_id', $orgId);
            if (!$isCeo) {
                $projectsQuery->where('project_manager_id', $userId);
            }

            $dueSoonProjects = (clone $projectsQuery)
                ->whereBetween('end_date', [$date->toDateString(), $date->copy()->addDays(7)->toDateString()])
                ->whereNotIn('status', ['completed', 'archived'])
                ->get()
                ->toArray();

            // Projects with no activity in last 3 days
            $staleProjects = (clone $projectsQuery)
                ->whereNotIn('status', ['completed', 'archived'])
                ->where('updated_at', '<', $staleThreshold)
                ->whereDoesntHave('tasks', function ($q) use ($staleThreshold) {
                    $q->where('updated_at', '>=', $staleThreshold);
                })
                ->get()
                ->toArray();
        }

        // 4. Metrics & Snaps (Analyst/CEO)
        $metaAds = [];
        $campaignAlerts = [];

        if ($isCeo || $isAnalyst) {
            // Query metric_snapshots for meta ads metrics
            // Slugs: meta_spend, meta_clicks, meta_impressions
            $metrics = ['meta_spend', 'meta_clicks', 'meta_impressions'];
            $yesterday = $date->copy()->subDay()->toDateString();
            $dayBefore = $date->copy()->subDays(2)->toDateString();

            foreach ($metrics as $metricSlug) {
                $kpi = DB::table('kpi_definitions')
                    ->where('organization_id', $orgId)
                    ->where('slug', $metricSlug)
                    ->first();

                if ($kpi) {
                    $valYesterday = DB::table('metric_snapshots')
                        ->where('kpi_definition_id', $kpi->id)
                        ->whereDate('date_key', $yesterday)
                        ->sum('value');

                    $valDayBefore = DB::table('metric_snapshots')
                        ->where('kpi_definition_id', $kpi->id)
                        ->whereDate('date_key', $dayBefore)
                        ->sum('value');

                    $metaAds[str_replace('meta_', '', $metricSlug)] = [
                        'yesterday' => floatval($valYesterday),
                        'previous_day' => floatval($valDayBefore),
                        'change_pct' => $valDayBefore > 0 ? round((($valYesterday - $valDayBefore) / $valDayBefore) * 100, 2) : 0
                    ];
                }
            }

            // Yesterday's campaign_alert notifications
            $campaignAlerts = DB::table('notifications_log')
                ->where('organization_id', $orgId)
                ->where('type', 'campaign_alert')
                ->whereDate('created_at', $date->copy()->subDay())
                ->get()
                ->toArray();
        }

        // 5. Notion recent updates (CEO/PM/Analyst)
        $notionUpdates = [];
        if ($isCeo || $isPm || $isAnalyst) {
            $notionConnection = McpConnection::where('organization_id', $orgId)
                ->where('provider', 'notion')
                ->where('status', 'active')
                ->first();

            if ($notionConnection) {
                try {
                    $notionAdapter = app(NotionAdapter::class);
                    $notionUpdates = $notionAdapter->getRecentUpdates(24);
                } catch (\Exception $e) {
                    // Ignore
                }
            }
        }

        // 6. Emails (Gmail)
        $unreadEmails = [];
        $gmailConnection = McpConnection::where('organization_id', $orgId)
            ->where('provider', 'gmail')
            ->where('status', 'active')
            ->first();

        if ($gmailConnection) {
            try {
                $gmailAdapter = app(GmailAdapter::class);
                // Temporarily logging in the user for Gmail Adapter context
                $originalUser = auth()->user();
                auth()->login($user);
                $unreadEmails = $gmailAdapter->getClientEmailsSummary(24);
                if ($originalUser) {
                    auth()->login($originalUser);
                } else {
                    auth()->logout();
                }
            } catch (\Exception $e) {
                // Ignore email sync issues
            }
        }

        // 7. Team blockers & overdue (PM/CEO)
        $overdueByMember = [];
        $blockedCount = 0;

        if ($isPm || $isCeo) {
            $overdueTasks = Task::where('organization_id', $orgId)
                ->whereDate('due_date', '<', $date)
                ->whereNotIn('status', ['done', 'cancelled'])
                ->with('assignee')
                ->get();

            $grouped = $overdueTasks->groupBy('assigned_to');
            foreach ($grouped as $assignedId => $tasks) {
                $name = $tasks->first()->assignee->name ?? 'Unassigned';
                $overdueByMember[] = [
                    'name' => $name,
                    'count' => $tasks->count()
                ];
            }

            // Blocked tasks count
            $blockedCount = Task::where('organization_id', $orgId)
                ->where('status', 'backlog')
                ->count();
        }

        // 8. Reports due today (Analyst/PM)
        $reportsDue = [];
        if ($isAnalyst || $isPm) {
            // Check scheduled reports in report_schedules if table exists
            try {
                $dayOfWeek = $date->dayOfWeek; // 0 (Sunday) to 6 (Saturday)
                $dayOfMonth = $date->day;

                // Frequency check: we look at report schedules due today
                // For simplicity, query schedules that match frequency criteria
                $schedules = DB::table('report_schedules')
                    ->where('organization_id', $orgId)
                    ->whereNull('deleted_at')
                    ->get();

                foreach ($schedules as $schedule) {
                    $isDue = false;
                    if ($schedule->frequency === 'weekly' && intval($schedule->send_day) === $dayOfWeek) {
                        $isDue = true;
                    } elseif ($schedule->frequency === 'monthly' && intval($schedule->send_day) === $dayOfMonth) {
                        $isDue = true;
                    }
                    
                    if ($isDue) {
                        $reportsDue[] = [
                            'id' => $schedule->id,
                            'template' => $schedule->report_template,
                            'recipients' => json_decode($schedule->recipients, true) ?: []
                        ];
                    }
                }
            } catch (\Exception $e) {
                // Table might not exist yet if running tests before migrations
            }
        }

        return [
            'user' => [
                'name' => $user->name,
                'role' => count($roles) > 0 ? $roles[0] : 'member'
            ],
            'date' => $date->toDateString(),
            'tasks' => [
                'due_today' => $dueToday,
                'overdue' => $overdue,
                'sla_warning' => $slaWarning,
                'completed_yesterday' => $completedYesterday
            ],
            'calendar' => [
                'events' => $calendarEvents
            ],
            'projects' => [
                'due_soon' => $dueSoonProjects,
                'stale' => $staleProjects
            ],
            'metrics' => [
                'meta_ads' => $metaAds,
                'alerts' => $campaignAlerts
            ],
            'notion' => [
                'recent_updates' => $notionUpdates
            ],
            'emails' => [
                'unread_client_emails' => $unreadEmails
            ],
            'team' => [
                'overdue_by_member' => $overdueByMember,
                'blocked_count' => $blockedCount
            ],
            'reports' => [
                'due_today' => $reportsDue
            ]
        ];
    }
}

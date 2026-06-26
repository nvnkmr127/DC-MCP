<?php

namespace App\Modules\DailyBriefing\Services;

use App\Modules\Auth\Models\User;
use App\Modules\ProjectManagement\Models\Task;
use App\Modules\ProjectManagement\Models\Client;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\MCP\Models\McpConnection;
use App\Modules\MCP\Adapters\GoogleCalendarAdapter;
use App\Modules\MCP\Adapters\GmailAdapter;
use App\Modules\MCP\Adapters\NotionAdapter;
use App\Modules\Revenue\Models\ClientRetainer;
use App\Modules\Revenue\Models\Invoice;
use App\Modules\Revenue\Models\Expense;
use App\Modules\Revenue\Models\PayrollRecord;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Log;
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
                Log::warning('Google Calendar data collection failed', [
                    'user_id'       => $user->id,
                    'connection_id' => $calendarConnection->id,
                    'exception'     => $e->getMessage(),
                ]);
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

        // 4. Removed Metrics & Snaps to avoid overlap with Reports

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
                    Log::warning('Notion data collection failed', [
                        'user_id'       => $user->id,
                        'connection_id' => $notionConnection->id,
                        'exception'     => $e->getMessage(),
                    ]);
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
                Log::warning('Gmail data collection failed', [
                    'user_id'       => $user->id,
                    'connection_id' => $gmailConnection->id,
                    'exception'     => $e->getMessage(),
                ]);
            }
        }

        // 7. Removed Team blockers & overdue to keep briefing personal

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
                Log::warning('Report schedules query failed during briefing collection', [
                    'organization_id' => $orgId,
                    'exception'       => $e->getMessage(),
                ]);
            }
        }

        // 9. Client health alerts (CEO/PM)
        $clientHealthAlerts = [];
        if ($isCeo || $isPm) {
            $clientHealthAlerts = Client::where('organization_id', $orgId)
                ->where('status', 'active')
                ->whereIn('health_status', ['red', 'yellow'])
                ->select('id', 'name', 'company', 'health_score', 'health_status', 'health_breakdown')
                ->orderBy('health_score')
                ->get()
                ->map(fn($c) => [
                    'id'     => $c->id,
                    'name'   => $c->company ?? $c->name,
                    'score'  => $c->health_score,
                    'status' => $c->health_status,
                ])
                ->toArray();
        }

        // 10. Overdue invoices & upcoming renewals (CEO)
        $overdueInvoices = [];
        $renewalAlerts   = [];
        if ($isCeo) {
            $overdueInvoices = Invoice::where('organization_id', $orgId)
                ->where('status', 'sent')
                ->whereDate('due_date', '<', $date)
                ->with('client:id,name,company')
                ->get()
                ->map(fn($i) => [
                    'invoice_number' => $i->invoice_number,
                    'amount'         => (float) $i->amount,
                    'currency'       => $i->currency,
                    'due_date'       => $i->due_date?->toDateString(),
                    'client'         => $i->client?->company ?? $i->client?->name,
                ])
                ->toArray();

            $renewalAlerts = ClientRetainer::where('organization_id', $orgId)
                ->where('status', 'active')
                ->whereDate('next_renewal_date', '<=', $date->copy()->addDays(30))
                ->whereDate('next_renewal_date', '>=', $date)
                ->with('client:id,name,company')
                ->orderBy('next_renewal_date')
                ->get()
                ->map(fn($r) => [
                    'name'              => $r->name,
                    'monthly_value'     => (float) $r->monthly_value,
                    'next_renewal_date' => $r->next_renewal_date?->toDateString(),
                    'client'            => $r->client?->company ?? $r->client?->name,
                ])
                ->toArray();
        }

        // 11. Removed Standup summary to keep briefing focused on user tasks

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
            'notion' => [
                'recent_updates' => $notionUpdates
            ],
            'emails' => [
                'unread_client_emails' => $unreadEmails
            ],
            'reports' => [
                'due_today' => $reportsDue
            ],
            'client_health' => [
                'alerts' => $clientHealthAlerts,
            ],
            'revenue' => [
                'overdue_invoices' => $overdueInvoices,
                'renewal_alerts'   => $renewalAlerts,
            ]
        ];
    }


}

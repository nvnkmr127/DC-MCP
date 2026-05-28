<?php

namespace App\Modules\Reporting\Console\Commands;

use App\Modules\Reporting\Models\ReportSchedule;
use App\Modules\Reporting\Services\ReportService;
use App\Modules\Reporting\Jobs\GenerateReportJob;
use Illuminate\Console\Command;
use Carbon\Carbon;

class RunScheduledReports extends Command
{
    protected $signature = 'reports:run-scheduled';
    protected $description = 'Process recurring report schedules and dispatch background generator jobs';

    public function handle(ReportService $reportService): int
    {
        $today = Carbon::today();
        $dayOfWeek = $today->dayOfWeekIso; // 1 (Mon) - 7 (Sun)
        $dayOfMonth = $today->day;

        $dueSchedules = ReportSchedule::where('is_active', true)
            ->where(function ($query) use ($dayOfWeek, $dayOfMonth) {
                $query->where(function ($q) use ($dayOfWeek) {
                    $q->where('frequency', 'weekly')
                      ->where('send_day', $dayOfWeek);
                })->orWhere(function ($q) use ($dayOfMonth) {
                    $q->where('frequency', 'monthly')
                      ->where('send_day', $dayOfMonth);
                });
            })
            ->get();

        $this->info("Found {$dueSchedules->count()} report schedules due today.");

        foreach ($dueSchedules as $schedule) {
            $this->line("Processing schedule: {$schedule->title}...");

            $dateTo = Carbon::today();
            $dateFrom = $schedule->frequency === 'weekly' 
                ? Carbon::today()->subDays(7)
                : Carbon::today()->subMonth();

            // Create a new report record from schedule configurations
            $report = $reportService->createReport([
                'organization_id' => $schedule->organization_id,
                'project_id' => $schedule->project_id,
                'client_id' => $schedule->client_id,
                'title' => $schedule->title . ' - ' . $today->format('Y-m-d'),
                'type' => $schedule->type,
                'template' => $schedule->template,
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'config' => $schedule->config,
                'recipients' => $schedule->recipients,
                'generated_by' => $schedule->created_by,
            ]);

            // Dispatch GenerateReportJob immediately
            GenerateReportJob::dispatch($report);

            // Update schedule metrics
            $schedule->update([
                'last_run_at' => now(),
                'next_run_at' => $schedule->frequency === 'weekly'
                    ? now()->addWeek()
                    : now()->addMonth(),
            ]);

            $this->info("Dispatched generation job for report: {$report->title}");
        }

        return 0;
    }
}

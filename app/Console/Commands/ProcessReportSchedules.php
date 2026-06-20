<?php

namespace App\Console\Commands;

use App\Modules\Reporting\Models\Report;
use App\Modules\Reporting\Models\ReportSchedule;
use App\Modules\Reporting\Jobs\GenerateReportJob;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessReportSchedules extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:process-schedules';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process active report schedules and dispatch report generation jobs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Processing report schedules...');

        $now = now();
        $schedules = ReportSchedule::where('is_active', true)
            ->where(function ($query) use ($now) {
                $query->whereNull('next_run_at')
                      ->orWhere('next_run_at', '<=', $now);
            })
            ->get();

        $count = 0;

        foreach ($schedules as $schedule) {
            try {
                // Determine date_from and date_to based on frequency
                if ($schedule->frequency === 'weekly') {
                    $dateFrom = $now->copy()->subWeek()->startOfDay();
                    $dateTo = $now->copy()->endOfDay();
                    $nextRunAt = $now->copy()->addWeek();
                } else { // monthly
                    $dateFrom = $now->copy()->subMonth()->startOfDay();
                    $dateTo = $now->copy()->endOfDay();
                    $nextRunAt = $now->copy()->addMonth();
                }

                // Create the Report record
                $report = Report::create([
                    'organization_id' => $schedule->organization_id,
                    'project_id'      => $schedule->project_id,
                    'client_id'       => $schedule->client_id,
                    'title'           => $schedule->title . ' - ' . $now->format('Y-m-d'),
                    'type'            => $schedule->type,
                    'template'        => $schedule->template,
                    'status'          => 'generating',
                    'date_from'       => $dateFrom,
                    'date_to'         => $dateTo,
                    'config'          => $schedule->config,
                    'recipients'      => $schedule->recipients,
                    'generated_by'    => $schedule->created_by, // Automated, but attributed to creator
                ]);

                // Dispatch generation job
                GenerateReportJob::dispatch($report);

                // Update schedule next run
                $schedule->update([
                    'last_run_at' => $now,
                    'next_run_at' => $nextRunAt,
                ]);

                $count++;
            } catch (\Exception $e) {
                Log::error('Failed to process report schedule', [
                    'schedule_id' => $schedule->id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        $this->info("Processed {$count} report schedules.");
    }
}

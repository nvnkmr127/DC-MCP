<?php

namespace App\Modules\Reporting\Jobs;

use App\Modules\Reporting\Models\Report;
use App\Modules\Reporting\Services\ReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;

    /** Backoff: retry after 30s on second attempt */
    public function backoff(): array
    {
        return [30];
    }

    public function __construct(
        public readonly Report $report
    ) {}

    public function handle(ReportService $reportService): void
    {
        Log::info('Report generation job started', [
            'report_id'      => $this->report->id,
            'organization_id'=> $this->report->organization_id,
        ]);

        try {
            $reportService->generateReport($this->report);

            if (!empty($this->report->recipients)) {
                $reportService->sendReport($this->report, $this->report->recipients);
            }

            Log::info('Report generation completed', [
                'report_id' => $this->report->id,
                'sent' => !empty($this->report->recipients),
            ]);
        } catch (\Exception $e) {
            Log::error('Report generation failed', [
                'report_id' => $this->report->id,
                'exception' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Report generation job permanently failed', [
            'report_id' => $this->report->id,
            'exception' => $exception->getMessage(),
        ]);

        // Mark report as failed so the UI can surface it
        $this->report->update(['status' => 'failed']);
    }
}

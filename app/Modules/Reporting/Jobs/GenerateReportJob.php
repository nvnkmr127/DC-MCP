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
    public int $timeout = 120; // PDF generation is slow

    public function __construct(
        public readonly Report $report
    ) {}

    public function handle(ReportService $reportService): void
    {
        try {
            $reportService->generateReport($this->report);
        } catch (\Exception $e) {
            Log::error("Failed to generate PDF for Report {$this->report->id}: " . $e->getMessage());
            throw $e;
        }
    }
}

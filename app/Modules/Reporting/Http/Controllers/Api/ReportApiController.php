<?php

namespace App\Modules\Reporting\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Reporting\Models\Report;
use App\Modules\Reporting\Models\ReportSchedule;
use App\Modules\Reporting\Services\ReportService;
use App\Modules\Reporting\Jobs\GenerateReportJob;
use App\Shared\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportApiController extends Controller
{
    public function __construct(
        protected readonly ReportService $reportService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $reports = Report::with(['project', 'client', 'generatedBy'])
            ->orderByDesc('created_at')
            ->paginate(15);

        return ApiResponse::paginated($reports);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => ['nullable', 'uuid'],
            'client_id'  => ['nullable', 'uuid'],
            'title'      => ['nullable', 'string', 'max:255'],
            'type'       => ['required', 'in:weekly,monthly,campaign,sprint,custom,client'],
            'template'   => ['required', 'in:seo_report,ads_report,social_report,sprint_report,full_service'],
            'date_from'  => ['required', 'date'],
            'date_to'    => ['required', 'date'],
            'config'     => ['nullable', 'array'],
            'recipients' => ['nullable', 'array'],
            'recipients.*' => ['email'],
        ]);

        $validated['organization_id'] = $request->user()->organization_id;
        $validated['generated_by'] = $request->user()->id;

        $report = $this->reportService->createReport($validated);

        // Dispatch background generation immediately
        GenerateReportJob::dispatch($report);

        return ApiResponse::success($report, 201);
    }

    public function show(Report $report): JsonResponse
    {
        $report->load(['project', 'client', 'generatedBy']);
        return ApiResponse::success($report);
    }

    public function generate(Report $report): JsonResponse
    {
        GenerateReportJob::dispatch($report);
        return ApiResponse::success(['message' => 'Report regeneration job dispatched successfully.']);
    }

    public function download(Report $report)
    {
        if (!$report->generated_file_path || !Storage::disk('public')->exists($report->generated_file_path)) {
            return response()->json(['message' => 'Report file not found or not yet generated.'], 404);
        }

        $filePath = Storage::disk('public')->path($report->generated_file_path);
        return response()->download($filePath, "{$report->title}.pdf");
    }

    public function send(Report $report, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'recipients' => ['required', 'array', 'min:1'],
            'recipients.*' => ['email'],
        ]);

        try {
            $this->reportService->sendReport($report, $validated['recipients']);
            return ApiResponse::success(['message' => 'Report sent to recipients successfully.']);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage());
        }
    }
}

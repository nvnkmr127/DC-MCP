<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reporting\Models\ReportSchedule;
use App\Shared\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportScheduleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $schedules = ReportSchedule::with(['project', 'client', 'creator'])
            ->orderByDesc('created_at')
            ->paginate(15);

        return ApiResponse::paginated($schedules);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => ['nullable', 'uuid'],
            'client_id'  => ['nullable', 'uuid'],
            'title'      => ['required', 'string', 'max:255'],
            'type'       => ['required', 'in:weekly,monthly,campaign,sprint,custom,client'],
            'template'   => ['required', 'in:seo_report,ads_report,social_report,sprint_report,full_service'],
            'frequency'  => ['required', 'in:weekly,monthly'],
            'send_day'   => ['required', 'integer', 'min:1', 'max:31'],
            'config'     => ['nullable', 'array'],
            'recipients' => ['required', 'array', 'min:1'],
            'recipients.*' => ['email'],
        ]);

        $validated['organization_id'] = $request->user()->organization_id;
        $validated['created_by'] = $request->user()->id;

        // Calculate next run time
        $validated['next_run_at'] = $validated['frequency'] === 'weekly'
            ? now()->addWeek()
            : now()->addMonth();

        $schedule = ReportSchedule::create($validated);

        return ApiResponse::success($schedule, 201);
    }

    public function show(ReportSchedule $schedule): JsonResponse
    {
        $schedule->load(['project', 'client', 'creator']);
        return ApiResponse::success($schedule);
    }

    public function update(ReportSchedule $schedule, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'      => ['sometimes', 'required', 'string', 'max:255'],
            'frequency'  => ['sometimes', 'required', 'in:weekly,monthly'],
            'send_day'   => ['sometimes', 'required', 'integer', 'min:1', 'max:31'],
            'config'     => ['nullable', 'array'],
            'recipients' => ['sometimes', 'required', 'array', 'min:1'],
            'recipients.*' => ['email'],
            'is_active'  => ['sometimes', 'required', 'boolean'],
        ]);

        $schedule->update($validated);

        return ApiResponse::success($schedule);
    }

    public function destroy(ReportSchedule $schedule): JsonResponse
    {
        $schedule->delete();
        return ApiResponse::success(['message' => 'Report schedule deleted successfully.']);
    }
}

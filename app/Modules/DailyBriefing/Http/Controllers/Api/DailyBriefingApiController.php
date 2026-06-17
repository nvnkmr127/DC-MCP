<?php

namespace App\Modules\DailyBriefing\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\DailyBriefing\Models\DailyBriefing;
use App\Modules\DailyBriefing\Jobs\GenerateDailyBriefingJob;
use App\Shared\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DailyBriefingApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $briefings = DailyBriefing::where('user_id', $request->user()->id)
            ->orderByDesc('date')
            ->paginate(20);

        return ApiResponse::paginated($briefings);
    }

    public function today(Request $request): JsonResponse
    {
        $briefing = DailyBriefing::where('user_id', $request->user()->id)
            ->whereDate('date', today())
            ->first();

        if (!$briefing) {
            return ApiResponse::error('No briefing generated for today yet.', [], 404);
        }

        return ApiResponse::success($briefing);
    }

    public function show(Request $request, DailyBriefing $briefing): JsonResponse
    {
        if ($briefing->user_id !== $request->user()->id) {
            return ApiResponse::error('Forbidden.', [], 403);
        }

        return ApiResponse::success($briefing);
    }

    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'date' => ['nullable', 'date'],
        ]);

        $date = $request->input('date', today()->toDateString());

        // Prevent duplicate generation for same user+date
        $existing = DailyBriefing::where('user_id', $request->user()->id)
            ->whereDate('date', $date)
            ->whereIn('status', ['generating', 'ready', 'delivered'])
            ->first();

        if ($existing) {
            return ApiResponse::success($existing, 'Briefing already exists for this date.');
        }

        // Create a placeholder record immediately
        $briefing = DailyBriefing::create([
            'organization_id' => $request->user()->organization_id,
            'user_id' => $request->user()->id,
            'date' => $date,
            'status' => 'generating',
        ]);

        GenerateDailyBriefingJob::dispatch($request->user(), $date);

        return ApiResponse::success($briefing, 'Briefing generation queued.', [], 202);
    }
}

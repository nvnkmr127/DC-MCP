<?php

namespace App\Modules\DataViz\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Modules\DataViz\Models\DashboardConfig;
use App\Modules\DataViz\Services\VizQueryEngine;
use App\Shared\Helpers\ApiResponse;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class PublicDashboardController extends Controller
{
    public function __construct(
        protected readonly VizQueryEngine $queryEngine
    ) {}

    /**
     * Render the public dashboard view.
     */
    public function show($token)
    {
        $dashboard = DashboardConfig::where('share_token', $token)->firstOrFail();

        return Inertia::render('Dashboard/Public', [
            'dashboard' => $dashboard,
            'token' => $token,
        ]);
    }

    /**
     * Fetch widget data for the public dashboard.
     */
    public function data($token, Request $request): JsonResponse
    {
        $dashboard = DashboardConfig::where('share_token', $token)->first();

        if (!$dashboard) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $layout = $dashboard->layout ?? [];
        $widgetData = [];

        // The user associated with the dashboard is used as the context for querying
        $owner = $dashboard->user;

        foreach ($layout as $widget) {
            $id = $widget['id'] ?? null;
            $spec = $widget['spec'] ?? null;

            if ($id && $spec) {
                if ($request->filled('from') && $request->filled('to')) {
                    $spec['filters'] = array_merge($spec['filters'] ?? [], [
                        'date_from' => $request->from,
                        'date_to'   => $request->to,
                    ]);
                }

                try {
                    $widgetData[$id] = $this->queryEngine->query($owner, $spec);
                } catch (\Exception $e) {
                    Log::error('Public dashboard widget query failed', [
                        'widget_id'      => $id,
                        'token'          => $token,
                        'exception'      => $e->getMessage(),
                    ]);
                    $widgetData[$id] = ['error' => 'Widget data unavailable.', 'data' => []];
                }
            }
        }

        return ApiResponse::success([
            'dashboard' => $dashboard,
            'widgets'   => $widgetData,
        ]);
    }
}

<?php

namespace App\Modules\DataViz\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\DataViz\Models\DashboardConfig;
use App\Modules\DataViz\Services\VizQueryEngine;
use App\Shared\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DashboardApiController extends Controller
{
    public function __construct(
        protected readonly VizQueryEngine $queryEngine
    ) {}

    /**
     * Get list of dashboards.
     */
    public function index(Request $request): JsonResponse
    {
        $orgId = $request->user()->organization_id;
        $userId = $request->user()->id;

        $dashboards = DashboardConfig::where('organization_id', $orgId)
            ->where('user_id', $userId)
            ->get();

        // If no dashboards found, return or create a default for the user's role
        if ($dashboards->isEmpty()) {
            $role = $request->user()->roles->first()?->slug ?? 'developer';
            $default = $this->createDefaultDashboard($request->user(), $role);
            $dashboards = collect([$default]);
        }

        return ApiResponse::success($dashboards);
    }

    /**
     * Create or save a dashboard config.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'layout'     => ['required', 'array'],
            'is_default' => ['sometimes', 'boolean'],
            'role'       => ['sometimes', 'string'],
        ]);

        $validated['organization_id'] = $request->user()->organization_id;
        $validated['user_id'] = $request->user()->id;
        $validated['role'] = $validated['role'] ?? ($request->user()->roles->first()?->slug ?? 'developer');

        if (!empty($validated['is_default']) && $validated['is_default']) {
            DashboardConfig::where('organization_id', $validated['organization_id'])
                ->where('user_id', $validated['user_id'])
                ->update(['is_default' => false]);
        }

        $dashboard = DashboardConfig::create($validated);

        return ApiResponse::success($dashboard, 201);
    }

    /**
     * Update dashboard config layout.
     */
    public function update(DashboardConfig $dashboard, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'   => ['sometimes', 'required', 'string', 'max:255'],
            'layout' => ['sometimes', 'required', 'array'],
            'is_default' => ['sometimes', 'required', 'boolean'],
        ]);

        if (!empty($validated['is_default']) && $validated['is_default']) {
            DashboardConfig::where('organization_id', $dashboard->organization_id)
                ->where('user_id', $dashboard->user_id)
                ->where('id', '!=', $dashboard->id)
                ->update(['is_default' => false]);
        }

        $dashboard->update($validated);

        return ApiResponse::success($dashboard);
    }

    /**
     * Get dashboard widget data in parallel.
     */
    public function data(DashboardConfig $dashboard, Request $request): JsonResponse
    {
        $layout = $dashboard->layout ?? [];
        $widgetData = [];

        foreach ($layout as $widget) {
            $id = $widget['id'] ?? null;
            $spec = $widget['spec'] ?? null;

            if ($id && $spec) {
                // Ensure date filters from request are mapped to spec filters
                if ($request->filled('from') && $request->filled('to')) {
                    $spec['filters'] = array_merge($spec['filters'] ?? [], [
                        'date_from' => $request->from,
                        'date_to'   => $request->to,
                    ]);
                }

                try {
                    $widgetData[$id] = $this->queryEngine->query($request->user(), $spec);
                } catch (\Exception $e) {
                    Log::error('Dashboard widget query failed', [
                        'widget_id'      => $id,
                        'metric_key'     => $spec['metric_key'] ?? null,
                        'user_id'        => $request->user()->id,
                        'organization_id'=> $request->user()->organization_id,
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

    /**
     * Generate or revoke a share token for the dashboard.
     */
    public function share(DashboardConfig $dashboard, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => 'required|boolean',
        ]);

        if ($validated['enabled']) {
            if (!$dashboard->share_token) {
                $dashboard->update([
                    'share_token' => \Illuminate\Support\Str::random(32)
                ]);
            }
        } else {
            $dashboard->update([
                'share_token' => null
            ]);
        }

        return ApiResponse::success([
            'dashboard' => $dashboard,
            'url' => $dashboard->share_token ? url("/public/dashboards/{$dashboard->share_token}") : null,
        ]);
    }

    /**
     * List all active KPIs definitions.
     */
    public function kpis(Request $request): JsonResponse
    {
        $kpis = DB::table('kpi_definitions')
            ->where('organization_id', $request->user()->organization_id)
            ->where('is_active', true)
            ->get();

        return ApiResponse::success($kpis);
    }

    /**
     * Query API endpoint.
     */
    public function query(Request $request): JsonResponse
    {
        $spec = $request->all();
        $results = $this->queryEngine->query($request->user(), $spec);
        return ApiResponse::success($results);
    }

    /**
     * Helper to create a default dashboard config based on role.
     */
    protected function createDefaultDashboard($user, string $role): DashboardConfig
    {
        $layout = match ($role) {
            'ceo' => [
                [
                    'id' => 'ceo-w1',
                    'title' => 'Tasks Completed',
                    'type' => 'metric_card',
                    'spec' => ['metric_key' => 'tasks_completed_count', 'aggregation' => 'count', 'filters' => []],
                    'position' => ['x' => 0, 'y' => 0, 'w' => 3, 'h' => 2]
                ],
                [
                    'id' => 'ceo-w2',
                    'title' => 'Hours Logged',
                    'type' => 'metric_card',
                    'spec' => ['metric_key' => 'time_logged_hours', 'aggregation' => 'sum', 'filters' => []],
                    'position' => ['x' => 3, 'y' => 0, 'w' => 3, 'h' => 2]
                ],
                [
                    'id' => 'ceo-w3',
                    'title' => 'Ad Spend Overview',
                    'type' => 'line_chart',
                    'spec' => ['metric_key' => 'meta_ads_spend', 'aggregation' => 'sum', 'group_by' => 'day', 'filters' => []],
                    'position' => ['x' => 0, 'y' => 2, 'w' => 6, 'h' => 4]
                ]
            ],
            default => [
                [
                    'id' => 'dev-w1',
                    'title' => 'Active Tasks',
                    'type' => 'metric_card',
                    'spec' => ['metric_key' => 'tasks_completed_count', 'aggregation' => 'count', 'filters' => []],
                    'position' => ['x' => 0, 'y' => 0, 'w' => 4, 'h' => 2]
                ],
                [
                    'id' => 'dev-w2',
                    'title' => 'SLA Breach Rate',
                    'type' => 'metric_card',
                    'spec' => ['metric_key' => 'sla_breach_rate', 'aggregation' => 'percentage', 'filters' => []],
                    'position' => ['x' => 4, 'y' => 0, 'w' => 4, 'h' => 2]
                ]
            ]
        };

        return DashboardConfig::create([
            'organization_id' => $user->organization_id,
            'user_id' => $user->id,
            'role' => $role,
            'name' => 'Default ' . ucfirst(str_replace('_', ' ', $role)) . ' Dashboard',
            'is_default' => true,
            'layout' => $layout,
        ]);
    }
}

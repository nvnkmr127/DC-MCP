<?php

namespace App\Modules\DataViz\Services;

use App\Modules\Auth\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class VizQueryEngine
{
    /**
     * Query metrics based on user preferences and query specification.
     */
    public function query(User $user, array $spec): array
    {
        $orgId = $user->organization_id;
        $metricKey = $spec['metric_key'] ?? 'meta_ads_spend';
        $filters = $spec['filters'] ?? [];
        $dateFrom = $filters['date_from'] ?? now()->subDays(30)->toDateString();
        $dateTo = $filters['date_to'] ?? now()->toDateString();

        // Create cache key based on org, specs, and date filters
        $cacheKey = "viz:{$orgId}:" . md5(json_encode($spec));
        
        return Cache::remember($cacheKey, 300, function () use ($orgId, $metricKey, $spec, $filters, $dateFrom, $dateTo) {
            $isComputed = in_array($metricKey, [
                'tasks_completed_count',
                'tasks_overdue_count',
                'avg_task_completion_hours',
                'time_logged_hours',
                'sla_breach_rate',
            ]);

            if ($isComputed) {
                return $this->queryComputed($orgId, $metricKey, $filters, $dateFrom, $dateTo);
            }

            return $this->querySnapshots($orgId, $metricKey, $spec, $filters, $dateFrom, $dateTo);
        });
    }

    /**
     * Query snapshots table.
     */
    protected function querySnapshots(string $orgId, string $metricKey, array $spec, array $filters, string $dateFrom, string $dateTo): array
    {
        $aggregation = $spec['aggregation'] ?? 'sum';
        $groupBy = $spec['group_by'] ?? 'day';
        $compare = $spec['compare'] ?? null;

        $kpi = DB::table('kpi_definitions')
            ->where('organization_id', $orgId)
            ->where('slug', $metricKey)
            ->first();

        $unit = $kpi?->unit ?? 'INR';

        // Base query
        $query = DB::table('metric_snapshots')
            ->join('kpi_definitions', 'metric_snapshots.kpi_definition_id', '=', 'kpi_definitions.id')
            ->where('metric_snapshots.organization_id', $orgId)
            ->where('kpi_definitions.slug', $metricKey)
            ->whereBetween('metric_snapshots.date_key', [$dateFrom, $dateTo]);

        if (!empty($filters['project_id'])) {
            $query->where('metric_snapshots.project_id', $filters['project_id']);
        }
        if (!empty($filters['client_id'])) {
            $query->where('metric_snapshots.client_id', $filters['client_id']);
        }
        if (!empty($filters['dimension_1'])) {
            $query->where('metric_snapshots.dimension_1', $filters['dimension_1']);
        }

        // Apply aggregation & grouping
        if ($groupBy === 'day') {
            $query->selectRaw('metric_snapshots.date_key as label');
        } elseif ($groupBy === 'month') {
            $query->selectRaw("TO_CHAR(metric_snapshots.date_key, 'YYYY-MM') as label");
        } else {
            $query->selectRaw('metric_snapshots.dimension_1 as label');
        }

        if ($aggregation === 'avg' || $aggregation === 'average') {
            $query->selectRaw('avg(metric_snapshots.value) as value');
        } elseif ($aggregation === 'count') {
            $query->selectRaw('count(metric_snapshots.value) as value');
        } else {
            $query->selectRaw('sum(metric_snapshots.value) as value');
        }

        $results = $query->groupBy('label')
            ->orderBy('label')
            ->get()
            ->map(fn($row) => [
                'label' => $row->label,
                'value' => round((float) $row->value, 2),
                'compare_value' => 0.0,
            ])
            ->toArray();

        $totalValue = array_sum(array_column($results, 'value'));
        $avgValue = count($results) > 0 ? $totalValue / count($results) : 0;

        return [
            'data' => $results,
            'summary' => [
                'total' => round($totalValue, 2),
                'average' => round($avgValue, 2),
                'change_pct' => 0.0,
                'trend' => 'up',
            ],
            'meta' => [
                'unit' => $unit,
                'chart_type' => $spec['chart_type'] ?? 'line',
                'date_range' => "{$dateFrom} to {$dateTo}",
            ],
        ];
    }

    /**
     * Query computed database values.
     */
    protected function queryComputed(string $orgId, string $metricKey, array $filters, string $dateFrom, string $dateTo): array
    {
        $data = [];
        $summaryTotal = 0.0;
        $unit = 'count';

        $projectFilter = $filters['project_id'] ?? null;
        $clientFilter = $filters['client_id'] ?? null;

        if ($metricKey === 'tasks_completed_count') {
            $query = DB::table('tasks')
                ->where('organization_id', $orgId)
                ->where('status', 'done')
                ->whereBetween('completed_at', [Carbon::parse($dateFrom)->startOfDay(), Carbon::parse($dateTo)->endOfDay()]);

            if ($projectFilter) {
                $query->where('project_id', $projectFilter);
            }

            $results = $query->selectRaw('DATE(completed_at) as label, count(*) as val')
                ->groupBy('label')
                ->orderBy('label')
                ->get();

            foreach ($results as $row) {
                $data[] = [
                    'label' => $row->label,
                    'value' => (float) $row->val,
                    'compare_value' => 0.0,
                ];
            }
            $summaryTotal = (float) $results->sum('val');
        } elseif ($metricKey === 'time_logged_hours') {
            $unit = 'hours';
            $query = DB::table('time_entries')
                ->where('organization_id', $orgId)
                ->whereBetween('logged_date', [$dateFrom, $dateTo]);

            if ($projectFilter) {
                $query->where('project_id', $projectFilter);
            }

            $results = $query->selectRaw('logged_date as label, sum(hours) as val')
                ->groupBy('logged_date')
                ->orderBy('logged_date')
                ->get();

            foreach ($results as $row) {
                $data[] = [
                    'label' => $row->label,
                    'value' => (float) $row->val,
                    'compare_value' => 0.0,
                ];
            }
            $summaryTotal = (float) $results->sum('val');
        } else {
            // Default flat layout for items like overdue/breach rate which aren't time series
            $summaryTotal = 0.0;
            if ($metricKey === 'tasks_overdue_count') {
                $query = DB::table('tasks')
                    ->where('organization_id', $orgId)
                    ->whereNotNull('due_date')
                    ->where('due_date', '<', now())
                    ->whereNotIn('status', ['done', 'cancelled']);

                if ($projectFilter) {
                    $query->where('project_id', $projectFilter);
                }

                $summaryTotal = (float) $query->count();
            } elseif ($metricKey === 'avg_task_completion_hours') {
                $unit = 'hours';
                $query = DB::table('tasks')
                    ->where('organization_id', $orgId)
                    ->where('status', 'done')
                    ->whereBetween('completed_at', [Carbon::parse($dateFrom)->startOfDay(), Carbon::parse($dateTo)->endOfDay()])
                    ->whereNotNull('created_at')
                    ->whereNotNull('completed_at');

                if ($projectFilter) {
                    $query->where('project_id', $projectFilter);
                }

                $summaryTotal = (float) $query->select(DB::raw('avg(extract(epoch from (completed_at - created_at)) / 3600) as avg_hrs'))->value('avg_hrs');
            } elseif ($metricKey === 'sla_breach_rate') {
                $unit = '%';
                $totalQuery = DB::table('tasks')
                    ->where('organization_id', $orgId)
                    ->whereNotNull('sla_hours')
                    ->whereBetween('created_at', [Carbon::parse($dateFrom)->startOfDay(), Carbon::parse($dateTo)->endOfDay()]);

                if ($projectFilter) {
                    $totalQuery->where('project_id', $projectFilter);
                }

                $total = $totalQuery->count();
                if ($total > 0) {
                    $breached = $totalQuery->whereNotNull('sla_breached_at')->count();
                    $summaryTotal = ($breached / $total) * 100;
                }
            }

            $data = [
                ['label' => 'Current', 'value' => round($summaryTotal, 2), 'compare_value' => 0.0]
            ];
        }

        return [
            'data' => $data,
            'summary' => [
                'total' => round($summaryTotal, 2),
                'average' => count($data) > 0 ? round($summaryTotal / count($data), 2) : 0,
                'change_pct' => 0.0,
                'trend' => 'up',
            ],
            'meta' => [
                'unit' => $unit,
                'chart_type' => 'card',
                'date_range' => "{$dateFrom} to {$dateTo}",
            ],
        ];
    }
}

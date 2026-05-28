<?php

namespace App\Modules\Revenue\Services;

use App\Modules\ProjectManagement\Models\Client;
use App\Modules\Revenue\Models\ClientRetainer;
use App\Modules\Revenue\Models\Invoice;
use App\Modules\Revenue\Models\Prospect;
use Illuminate\Support\Collection;

class RevenueService
{
    public function getMrrForOrg(string $orgId): float
    {
        return ClientRetainer::where('organization_id', $orgId)
            ->where('status', 'active')
            ->sum('monthly_value');
    }

    public function getOutstandingInvoices(string $orgId): Collection
    {
        return Invoice::where('organization_id', $orgId)
            ->whereIn('status', ['sent', 'overdue'])
            ->with('client:id,name,company')
            ->orderBy('due_date')
            ->get();
    }

    public function getOverdueInvoices(string $orgId): Collection
    {
        return Invoice::where('organization_id', $orgId)
            ->where('status', 'sent')
            ->whereDate('due_date', '<', now())
            ->with('client:id,name,company')
            ->get();
    }

    public function getRenewalsAlerts(string $orgId, int $daysAhead = 30): Collection
    {
        return ClientRetainer::where('organization_id', $orgId)
            ->where('status', 'active')
            ->whereDate('next_renewal_date', '<=', now()->addDays($daysAhead))
            ->whereDate('next_renewal_date', '>=', now())
            ->with('client:id,name,company')
            ->orderBy('next_renewal_date')
            ->get();
    }

    public function getPipelineSummary(string $orgId): array
    {
        $prospects = Prospect::where('organization_id', $orgId)
            ->whereNotIn('stage', ['won', 'lost'])
            ->get(['stage', 'estimated_value', 'probability']);

        return [
            'total_pipeline'    => $prospects->sum('estimated_value'),
            'weighted_pipeline' => $prospects->sum(fn($p) => $p->weightedValue()),
            'count'             => $prospects->count(),
            'by_stage'          => $prospects->groupBy('stage')->map->count(),
        ];
    }

    public function getDashboardStats(string $orgId): array
    {
        $mrr      = $this->getMrrForOrg($orgId);
        $overdue  = $this->getOverdueInvoices($orgId);
        $renewals = $this->getRenewalsAlerts($orgId, 30);
        $pipeline = $this->getPipelineSummary($orgId);

        $clients = Client::where('organization_id', $orgId)
            ->where('status', 'active')
            ->select('id', 'name', 'company', 'health_score', 'health_status')
            ->get();

        return [
            'mrr'                 => $mrr,
            'overdue_count'       => $overdue->count(),
            'overdue_amount'      => $overdue->sum('amount'),
            'renewals_count'      => $renewals->count(),
            'pipeline_weighted'   => $pipeline['weighted_pipeline'],
            'red_clients'         => $clients->where('health_status', 'red')->count(),
            'yellow_clients'      => $clients->where('health_status', 'yellow')->count(),
        ];
    }
}

<?php
namespace App\Modules\Revenue\Services;

use App\Modules\Auth\Models\User;
use App\Modules\ProjectManagement\Models\Client;
use App\Modules\Revenue\Models\ClientRetainer;
use App\Modules\Revenue\Models\Expense;
use App\Modules\Revenue\Models\Invoice;
use App\Modules\Revenue\Models\PayrollRecord;
use App\Modules\Revenue\Models\VendorContract;
use App\Modules\ProjectManagement\Models\Task;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class FinancialService
{
    /**
     * Compute (numerator / denominator) * 100, rounded to 1 decimal.
     * Returns 0.0 when denominator is zero to avoid division errors.
     * Uses bcmath throughout to maintain decimal precision.
     */
    private function safeMarginPercent(string $numerator, string $denominator): float
    {
        if (bccomp($denominator, '0', 2) <= 0) {
            return 0.0;
        }
        return round((float) bcdiv($numerator, $denominator, 6) * 100, 1);
    }

    public function getPnl(string $orgId, string $monthYear): array
    {
        [$year, $month] = explode('-', $monthYear);
        $startOfMonth = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endOfMonth   = $startOfMonth->copy()->endOfMonth();

        // Revenue — keep as string-based decimals to avoid float precision loss
        $mrr = ClientRetainer::where('organization_id', $orgId)
            ->where('status', 'active')
            ->sum('monthly_value') ?? '0';

        $invoiceRevenue = Invoice::where('organization_id', $orgId)
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$startOfMonth, $endOfMonth])
            ->sum('amount') ?? '0';

        $totalRevenue = bcadd((string) $mrr, (string) $invoiceRevenue, 2);

        // COGS — Payroll
        $payrollCost = PayrollRecord::where('organization_id', $orgId)
            ->where('month_year', $monthYear)
            ->sum('net_pay') ?? '0';

        // Operating Expenses
        $opExpenses = Expense::where('organization_id', $orgId)
            ->whereBetween('expense_date', [$startOfMonth, $endOfMonth])
            ->sum('amount') ?? '0';

        // Vendor/Tool costs
        $vendorCost = VendorContract::where('organization_id', $orgId)
            ->where('status', 'active')
            ->sum('monthly_cost') ?? '0';

        $totalCosts   = bcadd(bcadd((string) $payrollCost, (string) $opExpenses, 2), (string) $vendorCost, 2);
        $grossProfit  = bcsub($totalRevenue, (string) $payrollCost, 2);
        $netProfit    = bcsub($totalRevenue, $totalCosts, 2);
        $profitMargin = $this->safeMarginPercent($netProfit, $totalRevenue);

        return [
            'month_year' => $monthYear,
            'revenue'    => [
                'mrr'     => (float) $mrr,
                'invoices'=> (float) $invoiceRevenue,
                'total'   => (float) $totalRevenue,
            ],
            'costs' => [
                'payroll' => (float) $payrollCost,
                'expenses'=> (float) $opExpenses,
                'vendors' => (float) $vendorCost,
                'total'   => (float) $totalCosts,
            ],
            'gross_profit'  => (float) $grossProfit,
            'net_profit'    => (float) $netProfit,
            'profit_margin' => $profitMargin,
        ];
    }

    public function getClientProfitability(string $orgId, string $monthYear): Collection
    {
        $clients = Client::where('organization_id', $orgId)
            ->where('status', 'active')
            ->with('retainers')
            ->get();

        return $clients->map(function ($client) use ($orgId, $monthYear) {
            [$year, $month] = explode('-', $monthYear);
            $startOfMonth = Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $endOfMonth   = $startOfMonth->copy()->endOfMonth();

            $retainerRevenue = $client->retainers()->where('status', 'active')->sum('monthly_value') ?? '0';

            $invoiceRevenue = Invoice::where('client_id', $client->id)
                ->where('status', 'paid')
                ->whereBetween('paid_at', [$startOfMonth, $endOfMonth])
                ->sum('amount') ?? '0';

            $totalRevenue = bcadd((string) $retainerRevenue, (string) $invoiceRevenue, 2);

            $hoursLogged = (float) (Task::where('client_id', $client->id)
                ->whereBetween('updated_at', [$startOfMonth, $endOfMonth])
                ->sum('time_logged') ?? 0);

            $avgBillableRate = (float) (User::where('organization_id', $orgId)
                ->where('is_active', true)
                ->whereNotNull('billable_rate')
                ->avg('billable_rate') ?? 0);

            $estimatedCost = bcmul((string) $hoursLogged, (string) $avgBillableRate, 2);
            $profit        = bcsub($totalRevenue, $estimatedCost, 2);
            $margin = $this->safeMarginPercent($profit, $totalRevenue);

            return [
                'id'             => $client->id,
                'name'           => $client->company ?? $client->name,
                'revenue'        => (float) $totalRevenue,
                'estimated_cost' => (float) $estimatedCost,
                'profit'         => (float) $profit,
                'margin'         => $margin,
                'hours_logged'   => $hoursLogged,
                'health_status'  => $client->health_status,
            ];
        })->sortByDesc('profit')->values();
    }

    public function getCashFlowForecast(string $orgId, int $months = 3): array
    {
        $forecast = [];
        $mrr = (string) (ClientRetainer::where('organization_id', $orgId)
            ->where('status', 'active')
            ->sum('monthly_value') ?? '0');

        $pipeline = (string) (\App\Modules\Revenue\Models\Proposal::where('organization_id', $orgId)
            ->whereIn('status', ['draft', 'sent'])
            ->sum('total_value') ?? '0');        $monthlyPayroll = (string) (User::where('organization_id', $orgId)
            ->where('is_active', true)
            ->whereNotNull('monthly_salary')
            ->sum('monthly_salary') ?? '0');

        $monthlyVendors = (string) (VendorContract::where('organization_id', $orgId)
            ->where('status', 'active')
            ->sum('monthly_cost') ?? '0');

        $threeMonthExpenses = (string) (Expense::where('organization_id', $orgId)
            ->where('expense_date', '>=', now()->subMonths(3))
            ->sum('amount') ?? '0');
        $avgMonthlyExpenses = bcdiv($threeMonthExpenses, '3', 2);

        $monthlyCosts = bcadd(bcadd($monthlyPayroll, $monthlyVendors, 2), $avgMonthlyExpenses, 2);

        $projectedIn = bcadd($mrr, $pipeline, 2);

        for ($i = 1; $i <= $months; $i++) {
            $date     = now()->addMonths($i);
            $forecast[] = [
                'month'         => $date->format('Y-m'),
                'label'         => $date->format('M Y'),
                'mrr'           => (float) $mrr,
                'pipeline'      => (float) $pipeline,
                'projected_in'  => (float) $projectedIn,
                'projected_out' => (float) $monthlyCosts,
                'net'           => (float) bcsub($projectedIn, $monthlyCosts, 2),
            ];
        }

        return $forecast;
    }

    public function getMonthlyTrend(string $orgId, int $months = 6): array
    {
        $trend = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $date      = now()->subMonths($i);
            $monthYear = $date->format('Y-m');
            $pnl       = $this->getPnl($orgId, $monthYear);
            $trend[]   = [
                'month'     => $monthYear,
                'label'     => $date->format('M Y'),
                'revenue'   => $pnl['revenue']['total'],
                'costs'     => $pnl['costs']['total'],
                'net_profit'=> $pnl['net_profit'],
            ];
        }
        return $trend;
    }
}

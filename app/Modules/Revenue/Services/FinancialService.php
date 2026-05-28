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
    public function getPnl(string $orgId, string $monthYear): array
    {
        [$year, $month] = explode('-', $monthYear);
        $startOfMonth = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endOfMonth   = $startOfMonth->copy()->endOfMonth();

        // Revenue
        $mrr = (float) ClientRetainer::where('organization_id', $orgId)
            ->where('status', 'active')
            ->sum('monthly_value');

        $invoiceRevenue = (float) Invoice::where('organization_id', $orgId)
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $totalRevenue = $mrr + $invoiceRevenue;

        // COGS — Payroll
        $payrollCost = (float) PayrollRecord::where('organization_id', $orgId)
            ->where('month_year', $monthYear)
            ->sum('net_pay');

        // Operating Expenses
        $opExpenses = (float) Expense::where('organization_id', $orgId)
            ->whereBetween('expense_date', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        // Vendor/Tool costs (monthly prorated for annual)
        $vendorCost = (float) VendorContract::where('organization_id', $orgId)
            ->where('status', 'active')
            ->sum('monthly_cost');

        $totalCosts    = $payrollCost + $opExpenses + $vendorCost;
        $grossProfit   = $totalRevenue - $payrollCost;
        $netProfit     = $totalRevenue - $totalCosts;
        $profitMargin  = $totalRevenue > 0 ? round(($netProfit / $totalRevenue) * 100, 1) : 0;

        return [
            'month_year'      => $monthYear,
            'revenue'         => [
                'mrr'     => $mrr,
                'invoices'=> $invoiceRevenue,
                'total'   => $totalRevenue,
            ],
            'costs' => [
                'payroll' => $payrollCost,
                'expenses'=> $opExpenses,
                'vendors' => $vendorCost,
                'total'   => $totalCosts,
            ],
            'gross_profit'  => $grossProfit,
            'net_profit'    => $netProfit,
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

            $retainerRevenue = (float) $client->retainers()
                ->where('status', 'active')
                ->sum('monthly_value');

            $invoiceRevenue = (float) Invoice::where('client_id', $client->id)
                ->where('status', 'paid')
                ->whereBetween('paid_at', [$startOfMonth, $endOfMonth])
                ->sum('amount');

            $totalRevenue = $retainerRevenue + $invoiceRevenue;

            // Estimate cost: hours logged × team billable rate
            $hoursLogged = Task::where('client_id', $client->id)
                ->whereBetween('updated_at', [$startOfMonth, $endOfMonth])
                ->sum('time_logged');

            $avgBillableRate = User::where('organization_id', $orgId)
                ->where('is_active', true)
                ->whereNotNull('billable_rate')
                ->avg('billable_rate') ?? 0;

            $estimatedCost = $hoursLogged * $avgBillableRate;
            $profit        = $totalRevenue - $estimatedCost;
            $margin        = $totalRevenue > 0 ? round(($profit / $totalRevenue) * 100, 1) : 0;

            return [
                'id'               => $client->id,
                'name'             => $client->company ?? $client->name,
                'revenue'          => $totalRevenue,
                'estimated_cost'   => $estimatedCost,
                'profit'           => $profit,
                'margin'           => $margin,
                'hours_logged'     => (float) $hoursLogged,
                'health_status'    => $client->health_status,
            ];
        })->sortByDesc('profit')->values();
    }

    public function getCashFlowForecast(string $orgId, int $months = 3): array
    {
        $forecast = [];
        $mrr = (float) ClientRetainer::where('organization_id', $orgId)
            ->where('status', 'active')
            ->sum('monthly_value');

        $monthlyPayroll = (float) User::where('organization_id', $orgId)
            ->where('is_active', true)
            ->whereNotNull('monthly_salary')
            ->sum('monthly_salary');

        $monthlyVendors = (float) VendorContract::where('organization_id', $orgId)
            ->where('status', 'active')
            ->sum('monthly_cost');

        $avgMonthlyExpenses = (float) Expense::where('organization_id', $orgId)
            ->where('expense_date', '>=', now()->subMonths(3))
            ->sum('amount') / 3;

        $monthlyCosts = $monthlyPayroll + $monthlyVendors + $avgMonthlyExpenses;

        for ($i = 1; $i <= $months; $i++) {
            $date     = now()->addMonths($i);
            $forecast[] = [
                'month'         => $date->format('Y-m'),
                'label'         => $date->format('M Y'),
                'projected_in'  => $mrr,
                'projected_out' => $monthlyCosts,
                'net'           => $mrr - $monthlyCosts,
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

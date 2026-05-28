<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Revenue\Models\Expense;
use App\Modules\Revenue\Models\PayrollRecord;
use App\Modules\Revenue\Models\VendorContract;
use App\Modules\Revenue\Services\FinancialService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinancialController extends Controller
{
    public function __construct(private FinancialService $financialService) {}

    public function index(Request $request): Response
    {
        $orgId     = $request->user()->organization_id;
        $monthYear = $request->input('month', now()->format('Y-m'));

        $pnl              = $this->financialService->getPnl($orgId, $monthYear);
        $clientProfit     = $this->financialService->getClientProfitability($orgId, $monthYear);
        $cashForecast     = $this->financialService->getCashFlowForecast($orgId, 3);
        $trend            = $this->financialService->getMonthlyTrend($orgId, 6);

        $expenses = Expense::where('organization_id', $orgId)
            ->whereBetween('expense_date', [
                now()->parse($monthYear . '-01')->startOfMonth(),
                now()->parse($monthYear . '-01')->endOfMonth(),
            ])
            ->orderByDesc('expense_date')
            ->get()
            ->map(fn($e) => [
                'id'           => $e->id,
                'title'        => $e->title,
                'category'     => $e->category,
                'amount'       => (float) $e->amount,
                'currency'     => $e->currency,
                'expense_date' => $e->expense_date?->toDateString(),
                'vendor'       => $e->vendor,
                'is_recurring' => $e->is_recurring,
            ]);

        $vendors = VendorContract::where('organization_id', $orgId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get()
            ->map(fn($v) => [
                'id'           => $v->id,
                'name'         => $v->name,
                'type'         => $v->type,
                'monthly_cost' => (float) $v->monthly_cost,
                'currency'     => $v->currency,
                'billing_cycle'=> $v->billing_cycle,
                'status'       => $v->status,
            ]);

        return Inertia::render('Financials/Index', [
            'pnl'          => $pnl,
            'clientProfit' => $clientProfit,
            'cashForecast' => $cashForecast,
            'trend'        => $trend,
            'expenses'     => $expenses,
            'vendors'      => $vendors,
            'monthYear'    => $monthYear,
        ]);
    }
}

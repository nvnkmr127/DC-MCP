<?php
namespace App\Modules\Revenue\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ProjectManagement\Models\Client;
use App\Modules\Revenue\Models\CampaignBudget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CampaignBudgetWebController extends Controller
{
    public function index(Request $request): Response
    {
        $orgId     = $request->user()->organization_id;
        $monthYear = $request->input('month', now()->format('Y-m'));

        $budgets = CampaignBudget::where('organization_id', $orgId)
            ->where('month_year', $monthYear)
            ->with('client:id,name,company')
            ->orderBy('allocated_budget', 'desc')
            ->get()
            ->map(fn($b) => [
                'id'                => $b->id,
                'channel'           => $b->channel,
                'month_year'        => $b->month_year,
                'allocated_budget'  => (float) $b->allocated_budget,
                'spent_amount'      => (float) $b->spent_amount,
                'remaining'         => $b->remaining(),
                'utilization'       => $b->utilizationPercent(),
                'currency'          => $b->currency,
                'notes'             => $b->notes,
                'client'            => $b->client ? ['id' => $b->client->id, 'name' => $b->client->company ?? $b->client->name] : null,
            ]);

        $totalAllocated = $budgets->sum('allocated_budget');
        $totalSpent     = $budgets->sum('spent_amount');

        $clients = Client::where('organization_id', $orgId)
            ->where('status', 'active')
            ->select('id', 'name', 'company')
            ->orderBy('name')
            ->get();

        return Inertia::render('CampaignBudgets/Index', [
            'budgets'        => $budgets,
            'totalAllocated' => $totalAllocated,
            'totalSpent'     => $totalSpent,
            'monthYear'      => $monthYear,
            'clients'        => $clients,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'client_id'        => 'required|uuid',
            'channel'          => 'required|in:meta_ads,google_ads,seo,email,linkedin,twitter,youtube,other',
            'month_year'       => 'required|string|size:7',
            'allocated_budget' => 'required|numeric|min:0',
            'spent_amount'     => 'nullable|numeric|min:0',
            'notes'            => 'nullable|string|max:500',
        ]);

        CampaignBudget::updateOrCreate(
            [
                'organization_id' => $request->user()->organization_id,
                'client_id'       => $validated['client_id'],
                'channel'         => $validated['channel'],
                'month_year'      => $validated['month_year'],
            ],
            [
                'allocated_budget' => $validated['allocated_budget'],
                'spent_amount'     => $validated['spent_amount'] ?? 0,
                'notes'            => $validated['notes'] ?? null,
            ]
        );

        return back()->with('success', 'Campaign budget saved.');
    }

    public function updateSpend(Request $request, CampaignBudget $campaignBudget): RedirectResponse
    {
        $this->authorizeOrg($campaignBudget);
        $validated = $request->validate(['spent_amount' => 'required|numeric|min:0']);
        $campaignBudget->update($validated);
        return back()->with('success', 'Spend updated.');
    }
}

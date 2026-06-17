<?php

namespace App\Modules\Reporting\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Revenue\Models\CampaignResult;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CampaignResultWebController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'campaign_budget_id' => 'required|uuid|exists:campaign_budgets,id',
            'client_id'          => 'required|uuid|exists:clients,id',
            'report_date'        => 'required|date',
            'impressions'        => 'nullable|numeric|min:0',
            'clicks'             => 'nullable|numeric|min:0',
            'conversions'        => 'nullable|numeric|min:0',
            'spend'              => 'nullable|numeric|min:0',
            'revenue'            => 'nullable|numeric|min:0',
            'platform'           => 'nullable|string|max:100',
            'notes'              => 'nullable|string',
        ]);

        $impressions = (float) ($validated['impressions'] ?? 0);
        $clicks      = (float) ($validated['clicks'] ?? 0);
        $spend       = (float) ($validated['spend'] ?? 0);
        $revenue     = (float) ($validated['revenue'] ?? 0);

        $ctr  = $impressions > 0 ? round($clicks / $impressions, 4) : 0;
        $cpc  = $clicks > 0 ? round($spend / $clicks, 2) : 0;
        $roas = $spend > 0 ? round($revenue / $spend, 4) : 0;

        CampaignResult::create([
            'organization_id' => $request->user()->organization_id,
            'ctr'             => $ctr,
            'cpc'             => $cpc,
            'roas'            => $roas,
            ...$validated,
        ]);

        return back()->with('success', 'Performance logged.');
    }

    public function update(Request $request, CampaignResult $result): RedirectResponse
    {
        $this->authorizeOrg($result);

        $validated = $request->validate([
            'impressions' => 'nullable|numeric|min:0',
            'clicks'      => 'nullable|numeric|min:0',
            'conversions' => 'nullable|numeric|min:0',
            'spend'       => 'nullable|numeric|min:0',
            'revenue'     => 'nullable|numeric|min:0',
            'platform'    => 'nullable|string',
            'notes'       => 'nullable|string',
        ]);

        $impressions = (float) ($validated['impressions'] ?? $result->impressions);
        $clicks      = (float) ($validated['clicks'] ?? $result->clicks);
        $spend       = (float) ($validated['spend'] ?? $result->spend);
        $revenue     = (float) ($validated['revenue'] ?? $result->revenue);

        $result->update([
            ...$validated,
            'ctr'  => $impressions > 0 ? round($clicks / $impressions, 4) : 0,
            'cpc'  => $clicks > 0 ? round($spend / $clicks, 2) : 0,
            'roas' => $spend > 0 ? round($revenue / $spend, 4) : 0,
        ]);

        return back()->with('success', 'Result updated.');
    }

    public function destroy(Request $request, CampaignResult $result): RedirectResponse
    {
        $this->authorizeOrg($result);
        $result->delete();
        return back()->with('success', 'Result deleted.');
    }
}

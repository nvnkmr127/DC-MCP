<?php

namespace App\Modules\Revenue\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Revenue\Models\RateCard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RateCardWebController extends Controller
{
    public function index(Request $request): Response
    {
        $orgId = $request->user()->organization_id;

        $rateCards = RateCard::where('organization_id', $orgId)
            ->orderBy('category')
            ->orderBy('sort_order')
            ->get()
            ->map(fn($r) => [
                'id'           => $r->id,
                'service_name' => $r->service_name,
                'category'     => $r->category,
                'description'  => $r->description,
                'unit'         => $r->unit,
                'rate'         => (float) $r->rate,
                'currency'     => $r->currency,
                'is_active'    => $r->is_active,
                'sort_order'   => $r->sort_order,
            ]);

        return Inertia::render('RateCards/Index', [
            'rateCards' => $rateCards,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'service_name' => 'required|string|max:255',
            'category'     => 'nullable|string|max:255',
            'description'  => 'nullable|string',
            'unit'         => 'required|in:hour,post,campaign,month,project,word,video,other',
            'rate'         => 'required|numeric|min:0',
            'is_active'    => 'boolean',
            'sort_order'   => 'nullable|integer',
        ]);

        RateCard::create([
            'organization_id' => $request->user()->organization_id,
            ...$validated,
        ]);

        return back()->with('success', 'Rate card added.');
    }

    public function update(Request $request, RateCard $rateCard): RedirectResponse
    {
        $this->authorizeOrg($rateCard);

        $validated = $request->validate([
            'service_name' => 'sometimes|string|max:255',
            'category'     => 'nullable|string',
            'description'  => 'nullable|string',
            'unit'         => 'sometimes|in:hour,post,campaign,month,project,word,video,other',
            'rate'         => 'sometimes|numeric|min:0',
            'is_active'    => 'sometimes|boolean',
            'sort_order'   => 'nullable|integer',
        ]);

        $rateCard->update($validated);
        return back()->with('success', 'Rate card updated.');
    }

    public function destroy(Request $request, RateCard $rateCard): RedirectResponse
    {
        $this->authorizeOrg($rateCard);
        $rateCard->delete();
        return back()->with('success', 'Rate card deleted.');
    }
}

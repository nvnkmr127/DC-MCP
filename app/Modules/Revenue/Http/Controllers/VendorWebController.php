<?php

namespace App\Modules\Revenue\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Revenue\Models\VendorContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VendorWebController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'type'           => 'required|in:freelancer,tool,saas,service,infrastructure,other',
            'monthly_cost'   => 'required|numeric|min:0',
            'currency'       => 'nullable|string|size:3',
            'billing_cycle'  => 'required|in:monthly,annual,one_time',
            'billing_day'    => 'nullable|integer|min:1|max:31',
            'website'        => 'nullable|url|max:255',
            'contact_email'  => 'nullable|email|max:255',
            'contract_start' => 'nullable|date',
            'contract_end'   => 'nullable|date|after:contract_start',
            'notes'          => 'nullable|string|max:1000',
        ]);

        VendorContract::create([
            'organization_id' => $request->user()->organization_id,
            'status'          => 'active',
            'currency'        => $validated['currency'] ?? 'INR',
            ...$validated,
        ]);

        return back()->with('success', "\"{$validated['name']}\" added to vendor contracts.");
    }

    public function update(Request $request, VendorContract $vendorContract): RedirectResponse
    {
        abort_if($vendorContract->organization_id !== $request->user()->organization_id, 403);

        $validated = $request->validate([
            'name'         => 'sometimes|string|max:255',
            'monthly_cost' => 'sometimes|numeric|min:0',
            'status'       => 'sometimes|in:active,paused,cancelled',
            'contract_end' => 'sometimes|nullable|date',
            'notes'        => 'sometimes|nullable|string|max:1000',
        ]);

        $vendorContract->update($validated);
        return back()->with('success', 'Vendor updated.');
    }

    public function destroy(Request $request, VendorContract $vendorContract): RedirectResponse
    {
        abort_if($vendorContract->organization_id !== $request->user()->organization_id, 403);
        $vendorContract->delete();
        return back()->with('success', 'Vendor contract removed.');
    }
}

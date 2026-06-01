<?php

namespace App\Modules\Revenue\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Revenue\Models\Invoice;
use App\Modules\Revenue\Models\PaymentReceipt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentReceiptWebController extends Controller
{
    public function store(Request $request, Invoice $invoice): RedirectResponse
    {
        abort_if($invoice->organization_id !== $request->user()->organization_id, 403);

        $validated = $request->validate([
            'amount'         => 'required|numeric|min:0.01',
            'payment_date'   => 'required|date',
            'payment_method' => 'required|in:upi,neft,rtgs,cheque,cash,card,other',
            'reference_no'   => 'nullable|string|max:255',
            'notes'          => 'nullable|string|max:1000',
        ]);

        PaymentReceipt::create([
            'organization_id' => $request->user()->organization_id,
            'invoice_id'      => $invoice->id,
            'client_id'       => $invoice->client_id,
            'recorded_by'     => $request->user()->id,
            ...$validated,
        ]);

        return back()->with('success', 'Payment recorded.');
    }

    public function destroy(Request $request, PaymentReceipt $receipt): RedirectResponse
    {
        abort_if($receipt->organization_id !== $request->user()->organization_id, 403);
        $receipt->delete();
        return back()->with('success', 'Payment receipt deleted.');
    }
}

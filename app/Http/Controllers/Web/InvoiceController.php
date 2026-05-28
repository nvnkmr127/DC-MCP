<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Revenue\Models\ClientRetainer;
use App\Modules\Revenue\Models\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'client_id'      => 'required|uuid',
            'retainer_id'    => 'nullable|uuid',
            'invoice_number' => 'required|string|max:50',
            'amount'         => 'required|numeric|min:0',
            'currency'       => 'required|string|size:3',
            'issue_date'     => 'required|date',
            'due_date'       => 'required|date|after_or_equal:issue_date',
            'notes'          => 'nullable|string|max:1000',
        ]);

        Invoice::create([
            'organization_id' => $request->user()->organization_id,
            'status'          => 'draft',
            ...$validated,
        ]);

        return back()->with('success', "Invoice #{$validated['invoice_number']} created.");
    }

    public function update(Request $request, Invoice $invoice): RedirectResponse
    {
        abort_if($invoice->organization_id !== $request->user()->organization_id, 403);

        $validated = $request->validate([
            'status'         => 'sometimes|in:draft,sent,paid,overdue,cancelled',
            'due_date'       => 'sometimes|date',
            'paid_at'        => 'sometimes|nullable|date',
            'payment_method' => 'sometimes|nullable|string|max:50',
            'notes'          => 'sometimes|nullable|string|max:1000',
        ]);

        if (($validated['status'] ?? null) === 'paid' && empty($validated['paid_at'])) {
            $validated['paid_at'] = now();
        }

        $invoice->update($validated);

        return back()->with('success', 'Invoice updated.');
    }

    public function destroy(Request $request, Invoice $invoice): RedirectResponse
    {
        abort_if($invoice->organization_id !== $request->user()->organization_id, 403);
        $invoice->delete();
        return back()->with('success', 'Invoice deleted.');
    }
}

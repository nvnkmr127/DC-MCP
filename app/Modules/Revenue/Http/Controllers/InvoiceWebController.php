<?php

namespace App\Modules\Revenue\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Revenue\Models\ClientRetainer;
use App\Modules\Revenue\Models\Invoice;
use App\Modules\Revenue\Models\PaymentReceipt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InvoiceWebController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $orgId = $request->user()->organization_id;

        $validated = $request->validate([
            'client_id'      => 'required|uuid|exists:clients,id',
            'retainer_id'    => 'nullable|uuid',
            'invoice_number' => 'required|string|max:50',
            'amount'         => 'required|numeric|min:0',
            'currency'       => 'required|string|size:3',
            'issue_date'     => 'required|date',
            'due_date'       => 'required|date|after_or_equal:issue_date',
            'notes'          => 'nullable|string|max:1000',
        ]);

        $invoice = Invoice::create([
            'organization_id' => $orgId,
            'status'          => 'draft',
            ...$validated,
        ]);

        Log::info('Invoice created', [
            'invoice_id'     => $invoice->id,
            'invoice_number' => $validated['invoice_number'],
            'client_id'      => $validated['client_id'],
            'amount'         => $validated['amount'],
            'currency'       => $validated['currency'],
            'by_user_id'     => $request->user()->id,
            'organization_id'=> $orgId,
        ]);

        return back()->with('success', "Invoice #{$validated['invoice_number']} created.");
    }

    public function update(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorizeOrg($invoice);

        $validated = $request->validate([
            'status'         => 'sometimes|in:draft,sent,paid,overdue,cancelled',
            'due_date'       => 'sometimes|date',
            'paid_at'        => 'sometimes|nullable|date',
            'payment_method' => 'sometimes|nullable|string|max:50',
            'notes'          => 'sometimes|nullable|string|max:1000',
        ]);

        // Require at least one payment receipt before allowing 'paid' status
        if (($validated['status'] ?? null) === 'paid') {
            $receiptTotal = PaymentReceipt::where('invoice_id', $invoice->id)->sum('amount');
            if ((float) $receiptTotal <= 0) {
                return back()->withErrors(['status' => 'Add at least one payment receipt before marking the invoice as paid.']);
            }
            if (empty($validated['paid_at'])) {
                $validated['paid_at'] = now();
            }
        }

        $oldStatus = $invoice->status;
        $invoice->update($validated);

        Log::info('Invoice updated', [
            'invoice_id'     => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'by_user_id'     => $request->user()->id,
            'organization_id'=> $invoice->organization_id,
            'changes'        => array_keys($validated),
            'old_status'     => $oldStatus,
            'new_status'     => $validated['status'] ?? $oldStatus,
        ]);

        return back()->with('success', 'Invoice updated.');
    }

    public function destroy(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorizeOrg($invoice);

        Log::warning('Invoice deleted', [
            'invoice_id'     => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'status'         => $invoice->status,
            'amount'         => $invoice->amount,
            'by_user_id'     => $request->user()->id,
            'organization_id'=> $invoice->organization_id,
        ]);

        $invoice->delete();
        return back()->with('success', 'Invoice deleted.');
    }
}

<?php

namespace App\Modules\Revenue\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\ProjectManagement\Models\Client;
use App\Modules\Revenue\Models\CreditNote;
use App\Modules\Revenue\Models\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CreditNoteWebController extends Controller
{
    public function index(Request $request): Response
    {
        $orgId = $request->user()->organization_id;

        $creditNotes = CreditNote::where('organization_id', $orgId)
            ->with(['client:id,name,company', 'invoice:id,invoice_number'])
            ->orderByDesc('issue_date')
            ->get()
            ->map(fn($cn) => [
                'id'                 => $cn->id,
                'credit_note_number' => $cn->credit_note_number,
                'issue_date'         => $cn->issue_date->toDateString(),
                'amount'             => (float) $cn->amount,
                'reason'             => $cn->reason,
                'status'             => $cn->status,
                'applied_at'         => $cn->applied_at?->toDateString(),
                'client'             => $cn->client ? ['id' => $cn->client->id, 'name' => $cn->client->company ?? $cn->client->name] : null,
                'invoice'            => $cn->invoice ? ['id' => $cn->invoice->id, 'number' => $cn->invoice->invoice_number] : null,
            ]);

        $clients  = Client::where('organization_id', $orgId)->select('id', 'name', 'company')->get();
        $invoices = Invoice::where('organization_id', $orgId)->select('id', 'invoice_number', 'client_id')->get();

        return Inertia::render('CreditNotes/Index', [
            'creditNotes' => $creditNotes,
            'clients'     => $clients,
            'invoices'    => $invoices,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'client_id'  => 'required|uuid|exists:clients,id',
            'invoice_id' => 'nullable|uuid|exists:invoices,id',
            'issue_date' => 'required|date',
            'amount'     => 'required|numeric|min:0.01',
            'reason'     => 'required|string|max:1000',
        ]);

        $count    = CreditNote::where('organization_id', $request->user()->organization_id)->count() + 1;
        $cnNumber = 'CN-' . now()->format('Ym') . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);

        CreditNote::create([
            'organization_id'    => $request->user()->organization_id,
            'created_by'         => $request->user()->id,
            'credit_note_number' => $cnNumber,
            ...$validated,
        ]);

        return back()->with('success', 'Credit note created.');
    }

    public function update(Request $request, CreditNote $note): RedirectResponse
    {
        $this->authorizeOrg($note);

        $validated = $request->validate([
            'reason' => 'sometimes|string',
            'amount' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|in:draft,issued,applied',
        ]);

        $note->update($validated);
        return back()->with('success', 'Credit note updated.');
    }

    public function destroy(Request $request, CreditNote $note): RedirectResponse
    {
        $this->authorizeOrg($note);
        $note->delete();
        return back()->with('success', 'Credit note deleted.');
    }

    public function apply(Request $request, CreditNote $note): RedirectResponse
    {
        $this->authorizeOrg($note);
        $note->update(['status' => 'applied', 'applied_at' => now()]);
        return back()->with('success', 'Credit note applied.');
    }
}

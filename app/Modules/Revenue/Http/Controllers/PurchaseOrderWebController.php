<?php

namespace App\Modules\Revenue\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Revenue\Models\PurchaseOrder;
use App\Modules\Revenue\Models\VendorContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderWebController extends Controller
{
    public function index(Request $request): Response
    {
        $orgId = $request->user()->organization_id;

        $pos = PurchaseOrder::where('organization_id', $orgId)
            ->orderByDesc('issue_date')
            ->get()
            ->map(fn($p) => [
                'id'                => $p->id,
                'po_number'         => $p->po_number,
                'issue_date'        => $p->issue_date?->toDateString(),
                'expected_delivery' => $p->expected_delivery?->toDateString(),
                'total_amount'      => (float) $p->total_amount,
                'status'            => $p->status,
                'notes'             => $p->notes,
                'line_items'        => $p->line_items ?? [],
                'vendor_id'         => $p->vendor_id,
            ]);

        $vendors = VendorContract::where('organization_id', $orgId)->select('id', 'vendor_name')->distinct('vendor_name')->get();

        return Inertia::render('PurchaseOrders/Index', [
            'purchaseOrders' => $pos,
            'vendors'        => $vendors,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'vendor_id'         => 'nullable|uuid',
            'issue_date'        => 'required|date',
            'expected_delivery' => 'nullable|date',
            'notes'             => 'nullable|string',
            'line_items'        => 'required|array|min:1',
            'line_items.*.description' => 'required|string',
            'line_items.*.quantity'    => 'required|numeric|min:0',
            'line_items.*.unit_price'  => 'required|numeric|min:0',
        ]);

        $total = collect($validated['line_items'])->sum(fn($li) => $li['quantity'] * $li['unit_price']);
        $items = collect($validated['line_items'])->map(fn($li) => array_merge($li, ['total' => $li['quantity'] * $li['unit_price']]))->all();

        $count   = PurchaseOrder::where('organization_id', $request->user()->organization_id)->count() + 1;
        $poNumber = 'PO-' . now()->format('Ym') . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);

        PurchaseOrder::create([
            'organization_id' => $request->user()->organization_id,
            'created_by'      => $request->user()->id,
            'po_number'       => $poNumber,
            'total_amount'    => $total,
            'line_items'      => $items,
            'vendor_id'       => $validated['vendor_id'] ?? null,
            'issue_date'      => $validated['issue_date'],
            'expected_delivery' => $validated['expected_delivery'] ?? null,
            'notes'           => $validated['notes'] ?? null,
        ]);

        return back()->with('success', 'Purchase order created.');
    }

    public function update(Request $request, PurchaseOrder $po): RedirectResponse
    {
        abort_if($po->organization_id !== $request->user()->organization_id, 403);

        $validated = $request->validate([
            'notes'             => 'nullable|string',
            'expected_delivery' => 'nullable|date',
            'line_items'        => 'nullable|array',
        ]);

        if (isset($validated['line_items'])) {
            $validated['total_amount'] = collect($validated['line_items'])->sum(fn($li) => $li['quantity'] * $li['unit_price']);
            $validated['line_items']   = collect($validated['line_items'])->map(fn($li) => array_merge($li, ['total' => $li['quantity'] * $li['unit_price']]))->all();
        }

        $po->update($validated);
        return back()->with('success', 'Purchase order updated.');
    }

    public function destroy(Request $request, PurchaseOrder $po): RedirectResponse
    {
        abort_if($po->organization_id !== $request->user()->organization_id, 403);
        $po->delete();
        return back()->with('success', 'Purchase order deleted.');
    }

    public function updateStatus(Request $request, PurchaseOrder $po): RedirectResponse
    {
        abort_if($po->organization_id !== $request->user()->organization_id, 403);

        $validated = $request->validate([
            'status' => 'required|in:draft,sent,acknowledged,received,cancelled',
        ]);

        $po->update($validated);
        return back()->with('success', 'Status updated.');
    }
}

<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Revenue\Models\Invoice;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GSTReportController extends Controller
{
    public function index(Request $request): Response
    {
        $month = $request->get('month', now()->format('Y-m'));
        $orgId = $request->user()->organization_id;

        [$year, $mon] = explode('-', $month);

        $invoices = Invoice::where('organization_id', $orgId)
            ->where('gst_amount', '>', 0)
            ->whereYear('issue_date', $year)
            ->whereMonth('issue_date', $mon)
            ->with('client:id,name,company')
            ->get()
            ->map(fn($inv) => [
                'id'             => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'client_name'    => $inv->client?->company ?? $inv->client?->name ?? '—',
                'client_gstin'   => $inv->client_gstin,
                'amount'         => (float) ($inv->amount - $inv->gst_amount),
                'gst_rate'       => (float) $inv->gst_rate,
                'gst_amount'     => (float) $inv->gst_amount,
                'supply_type'    => $inv->supply_type ?? 'intra',
                'month_year'     => $month,
            ]);

        return Inertia::render('GSTReport/Index', [
            'invoices' => $invoices,
            'month'    => $month,
        ]);
    }
}

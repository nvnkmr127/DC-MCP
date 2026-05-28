<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\ProjectManagement\Models\Client;
use App\Modules\Revenue\Models\ClientRetainer;
use App\Modules\Revenue\Models\Invoice;
use App\Modules\Revenue\Services\RevenueService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RetainerController extends Controller
{
    public function __construct(private RevenueService $revenueService) {}

    public function index(Request $request): Response
    {
        $orgId = $request->user()->organization_id;

        $retainers = ClientRetainer::where('organization_id', $orgId)
            ->with('client:id,name,company,health_score,health_status')
            ->withCount('invoices')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($r) => [
                'id'                => $r->id,
                'name'              => $r->name,
                'monthly_value'     => (float) $r->monthly_value,
                'currency'          => $r->currency,
                'billing_cycle'     => $r->billing_cycle,
                'status'            => $r->status,
                'start_date'        => $r->start_date?->toDateString(),
                'end_date'          => $r->end_date?->toDateString(),
                'next_renewal_date' => $r->next_renewal_date?->toDateString(),
                'auto_renew'        => $r->auto_renew,
                'invoices_count'    => $r->invoices_count,
                'client'            => $r->client ? [
                    'id'           => $r->client->id,
                    'name'         => $r->client->company ?? $r->client->name,
                    'health_score' => $r->client->health_score,
                    'health_status'=> $r->client->health_status,
                ] : null,
            ]);

        $stats = $this->revenueService->getDashboardStats($orgId);

        $clients = Client::where('organization_id', $orgId)
            ->where('status', 'active')
            ->select('id', 'name', 'company')
            ->orderBy('name')
            ->get();

        return Inertia::render('Revenue/Index', [
            'retainers' => $retainers,
            'stats'     => $stats,
            'clients'   => $clients,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'client_id'         => 'required|uuid',
            'name'              => 'required|string|max:255',
            'monthly_value'     => 'required|numeric|min:0',
            'currency'          => 'required|string|size:3',
            'billing_cycle'     => 'required|in:monthly,quarterly,annual',
            'start_date'        => 'required|date',
            'end_date'          => 'nullable|date|after:start_date',
            'payment_terms_days'=> 'nullable|integer|min:0',
            'auto_renew'        => 'boolean',
            'notes'             => 'nullable|string|max:1000',
        ]);

        $retainer = ClientRetainer::create([
            'organization_id' => $request->user()->organization_id,
            'status'          => 'active',
            ...$validated,
        ]);

        $nextRenewal = match($validated['billing_cycle']) {
            'quarterly' => now()->addMonths(3),
            'annual'    => now()->addYear(),
            default     => now()->addMonth(),
        };
        $retainer->update(['next_renewal_date' => $nextRenewal->toDateString()]);

        return back()->with('success', "Retainer \"{$retainer->name}\" created.");
    }

    public function update(Request $request, ClientRetainer $retainer): RedirectResponse
    {
        abort_if($retainer->organization_id !== $request->user()->organization_id, 403);

        $validated = $request->validate([
            'name'              => 'sometimes|string|max:255',
            'monthly_value'     => 'sometimes|numeric|min:0',
            'billing_cycle'     => 'sometimes|in:monthly,quarterly,annual',
            'status'            => 'sometimes|in:active,paused,cancelled',
            'end_date'          => 'sometimes|nullable|date',
            'next_renewal_date' => 'sometimes|nullable|date',
            'auto_renew'        => 'sometimes|boolean',
            'notes'             => 'sometimes|nullable|string|max:1000',
        ]);

        $retainer->update($validated);

        return back()->with('success', 'Retainer updated.');
    }

    public function destroy(Request $request, ClientRetainer $retainer): RedirectResponse
    {
        abort_if($retainer->organization_id !== $request->user()->organization_id, 403);
        $retainer->delete();
        return back()->with('success', 'Retainer deleted.');
    }
}

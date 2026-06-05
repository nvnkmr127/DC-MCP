<?php
namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\Revenue\Models\PayrollRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayrollWebController extends Controller
{
    public function index(Request $request): Response
    {
        $orgId     = $request->user()->organization_id;
        $monthYear = $request->input('month', now()->format('Y-m'));

        $team = User::where('organization_id', $orgId)
            ->where('is_active', true)
            ->with('roles')
            ->select('id', 'name', 'email', 'monthly_salary', 'billable_rate')
            ->orderBy('name')
            ->get();

        $records = PayrollRecord::where('organization_id', $orgId)
            ->where('month_year', $monthYear)
            ->with(['user' => function ($query) {
                $query->select('id', 'name', 'email')->with('roles');
            }])
            ->get()
            ->keyBy('user_id')
            ->map(fn($r) => [
                'id'          => $r->id,
                'user_id'     => $r->user_id,
                'base_salary' => (float) $r->base_salary,
                'bonuses'     => (float) $r->bonuses,
                'deductions'  => (float) $r->deductions,
                'net_pay'     => (float) $r->net_pay,
                'currency'    => $r->currency,
                'status'      => $r->status,
                'paid_at'     => $r->paid_at?->toISOString(),
                'notes'       => $r->notes,
                'user'        => $r->user ? ['id' => $r->user->id, 'name' => $r->user->name, 'role' => $r->user->role] : null,
            ]);

        // Build payslip rows — merge team with records
        $payslips = $team->map(fn($u) => [
            'user'   => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'role' => $u->role],
            'record' => $records[$u->id] ?? null,
            'salary' => (float) ($u->monthly_salary ?? 0),
        ]);

        $totalPayroll = $records->sum('net_pay');

        return Inertia::render('Payroll/Index', [
            'payslips'     => $payslips,
            'monthYear'    => $monthYear,
            'totalPayroll' => $totalPayroll,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id'     => 'required|uuid',
            'month_year'  => 'required|string|size:7',
            'base_salary' => 'required|numeric|min:0',
            'bonuses'     => 'nullable|numeric|min:0',
            'deductions'  => 'nullable|numeric|min:0',
            'notes'       => 'nullable|string|max:500',
        ]);

        $bonuses    = (float) ($validated['bonuses']    ?? 0);
        $deductions = (float) ($validated['deductions'] ?? 0);
        $netPay     = (float) $validated['base_salary'] + $bonuses - $deductions;

        PayrollRecord::updateOrCreate(
            [
                'organization_id' => $request->user()->organization_id,
                'user_id'         => $validated['user_id'],
                'month_year'      => $validated['month_year'],
            ],
            [
                'base_salary' => $validated['base_salary'],
                'bonuses'     => $bonuses,
                'deductions'  => $deductions,
                'net_pay'     => $netPay,
                'status'      => 'pending',
                'notes'       => $validated['notes'] ?? null,
            ]
        );

        return back()->with('success', 'Payroll record saved.');
    }

    public function markPaid(Request $request, PayrollRecord $payrollRecord): RedirectResponse
    {
        $this->authorizeOrg($payrollRecord);
        $payrollRecord->update([
            'status'       => 'paid',
            'paid_at'      => now(),
            'processed_by' => $request->user()->id,
        ]);
        return back()->with('success', 'Payslip marked as paid.');
    }

    public function bulkGenerate(Request $request): RedirectResponse
    {
        $orgId     = $request->user()->organization_id;
        $monthYear = $request->input('month_year', now()->format('Y-m'));

        $team = User::where('organization_id', $orgId)
            ->where('is_active', true)
            ->whereNotNull('monthly_salary')
            ->get();

        $generated = 0;
        foreach ($team as $user) {
            PayrollRecord::firstOrCreate(
                ['organization_id' => $orgId, 'user_id' => $user->id, 'month_year' => $monthYear],
                [
                    'base_salary' => $user->monthly_salary,
                    'bonuses'     => 0,
                    'deductions'  => 0,
                    'net_pay'     => $user->monthly_salary,
                    'status'      => 'pending',
                ]
            );
            $generated++;
        }

        return back()->with('success', "Generated {$generated} payslip(s) for {$monthYear}.");
    }
}

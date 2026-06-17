<?php

namespace App\Modules\HR\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\HR\Models\LeaveBalance;
use App\Modules\HR\Models\LeaveRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;

class LeaveWebController extends Controller
{
    public function index(Request $request): Response
    {
        $user  = $request->user();
        $orgId = $user->organization_id;
        $year  = now()->year;

        $myRequests = LeaveRequest::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($r) => [
                'id'             => $r->id,
                'type'           => $r->type,
                'from_date'      => $r->from_date->toDateString(),
                'to_date'        => $r->to_date->toDateString(),
                'days'           => (float) $r->days,
                'reason'         => $r->reason,
                'status'         => $r->status,
                'reviewer_notes' => $r->reviewer_notes,
                'reviewed_at'    => $r->reviewed_at?->toDateString(),
            ]);

        $teamRequests = [];
        if (in_array($user->role, ['ceo', 'project_manager'])) {
            $teamRequests = LeaveRequest::where('organization_id', $orgId)
                ->where('user_id', '!=', $user->id)
                ->with('user:id,name')
                ->orderByDesc('created_at')
                ->get()
                ->map(fn($r) => [
                    'id'        => $r->id,
                    'type'      => $r->type,
                    'from_date' => $r->from_date->toDateString(),
                    'to_date'   => $r->to_date->toDateString(),
                    'days'      => (float) $r->days,
                    'reason'    => $r->reason,
                    'status'    => $r->status,
                    'user'      => ['id' => $r->user?->id, 'name' => $r->user?->name],
                ]);
        }

        $balance = $this->findOrCreateBalance($orgId, $user->id, $year);

        return Inertia::render('HR/Leave/Index', [
            'myRequests'   => $myRequests,
            'teamRequests' => $teamRequests,
            'balance'      => [
                'earned_total'  => (float) $balance->earned_total,
                'earned_used'   => (float) $balance->earned_used,
                'sick_total'    => (float) $balance->sick_total,
                'sick_used'     => (float) $balance->sick_used,
                'casual_total'  => (float) $balance->casual_total,
                'casual_used'   => (float) $balance->casual_used,
            ],
            'canReview' => in_array($user->role, ['ceo', 'project_manager']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type'      => 'required|in:sick,earned,casual,wfh,unpaid',
            'from_date' => 'required|date',
            'to_date'   => 'required|date|after_or_equal:from_date',
            'reason'    => 'nullable|string|max:1000',
        ]);

        $user  = $request->user();
        $orgId = $user->organization_id;

        $from = Carbon::parse($validated['from_date']);
        $to   = Carbon::parse($validated['to_date']);
        $days = $from->diffInWeekdays($to) + 1;

        $overlap = LeaveRequest::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved'])
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('from_date', [$from, $to])
                  ->orWhereBetween('to_date', [$from, $to])
                  ->orWhere(fn($q2) => $q2->where('from_date', '<=', $from)->where('to_date', '>=', $to));
            })->exists();

        if ($overlap) {
            return back()->withErrors(['from_date' => 'You already have a leave request for overlapping dates.']);
        }

        $leave = LeaveRequest::create([
            'organization_id' => $orgId,
            'user_id'         => $user->id,
            'days'            => $days,
            ...$validated,
        ]);

        Log::info('Leave request submitted', [
            'leave_id'       => $leave->id,
            'user_id'        => $user->id,
            'organization_id'=> $orgId,
            'type'           => $validated['type'],
            'from_date'      => $validated['from_date'],
            'to_date'        => $validated['to_date'],
            'days'           => $days,
        ]);

        return back()->with('success', 'Leave request submitted.');
    }

    public function approve(Request $request, LeaveRequest $leave): RedirectResponse
    {
        $this->authorizeOrg($leave);
        abort_unless(in_array($request->user()->role, ['ceo', 'project_manager']), 403);
        abort_if($leave->status !== 'pending', 422, 'Only pending leave requests can be approved.');

        DB::transaction(function () use ($leave, $request) {
            $leave->update([
                'status'      => 'approved',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            $col = match ($leave->type) {
                'earned' => 'earned_used',
                'sick'   => 'sick_used',
                'casual' => 'casual_used',
                default  => null,
            };

            if ($col) {
                $balance = $this->findOrCreateBalance(
                    $leave->organization_id,
                    $leave->user_id,
                    now()->year
                );
                $balance->increment($col, $leave->days);

                Log::info('Leave balance incremented', [
                    'leave_id'       => $leave->id,
                    'user_id'        => $leave->user_id,
                    'organization_id'=> $leave->organization_id,
                    'column'         => $col,
                    'days'           => $leave->days,
                    'approved_by'    => $request->user()->id,
                ]);
            }
        });

        Log::info('Leave approved', [
            'leave_id'       => $leave->id,
            'user_id'        => $leave->user_id,
            'organization_id'=> $leave->organization_id,
            'type'           => $leave->type,
            'days'           => $leave->days,
            'approved_by'    => $request->user()->id,
        ]);

        return back()->with('success', 'Leave approved.');
    }

    public function reject(Request $request, LeaveRequest $leave): RedirectResponse
    {
        $this->authorizeOrg($leave);
        abort_unless(in_array($request->user()->role, ['ceo', 'project_manager']), 403);
        abort_if($leave->status !== 'pending', 422, 'Only pending leave requests can be rejected.');

        $leave->update([
            'status'      => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        Log::info('Leave rejected', [
            'leave_id'       => $leave->id,
            'user_id'        => $leave->user_id,
            'organization_id'=> $leave->organization_id,
            'type'           => $leave->type,
            'days'           => $leave->days,
            'rejected_by'    => $request->user()->id,
        ]);

        return back()->with('success', 'Leave rejected.');
    }

    public function destroy(Request $request, LeaveRequest $leave): RedirectResponse
    {
        $this->authorizeOrg($leave);
        abort_if($leave->status !== 'pending', 403);

        $leave->delete();

        return back()->with('success', 'Leave request deleted.');
    }

    private function findOrCreateBalance(string $orgId, string $userId, int $year): LeaveBalance
    {
        // Use updateOrCreate with a unique key to avoid race-condition duplicates
        return LeaveBalance::firstOrCreate(
            ['organization_id' => $orgId, 'user_id' => $userId, 'year' => $year],
            ['earned_total' => 15, 'earned_used' => 0, 'sick_total' => 12, 'sick_used' => 0, 'casual_total' => 6, 'casual_used' => 0]
        );
    }
}

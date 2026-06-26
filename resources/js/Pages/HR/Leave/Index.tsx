import React, { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { cn } from '@/lib/utils';
import { Plus, X, CheckCircle2, XCircle, Clock } from 'lucide-react';

interface LeaveRequest {
    id: string; type: string; from_date: string; to_date: string; days: number;
    reason: string | null; status: string; reviewer_notes: string | null; reviewed_at: string | null;
    user?: { id: string; name: string };
    reviewer?: { id: string; name: string } | null;
}
interface Balance {
    earned_total: number; earned_used: number;
    sick_total: number; sick_used: number;
    casual_total: number; casual_used: number;
}
interface Props {
    myRequests: LeaveRequest[];
    teamRequests: LeaveRequest[];
    balance: Balance;
    canReview: boolean;
}

const STATUS_STYLES: Record<string, string> = {
    pending:  'bg-amber-100 text-amber-800',
    approved: 'bg-emerald-100 text-emerald-700',
    rejected: 'bg-rose-100 text-rose-700',
};

function BalanceCard({ label, used, total, color }: { label: string; used: number; total: number; color: string }) {
    const pct = total > 0 ? Math.round((used / total) * 100) : 0;
    return (
        <div className="bg-white rounded-xl border border-gray-200 p-4">
            <p className="text-xs text-gray-500 font-medium mb-1">{label}</p>
            <p className="text-2xl font-bold text-gray-900">{total - used} <span className="text-sm font-normal text-gray-400">/ {total} days</span></p>
            <div className="mt-2 h-1.5 bg-gray-100 rounded-full">
                <div className={cn('h-full rounded-full', color)} style={{ width: `${pct}%` }} />
            </div>
            <p className="text-xs text-gray-400 mt-1">{used} used</p>
        </div>
    );
}

function ApplyModal({ onClose }: { onClose: () => void }) {
    const form = useForm({ type: 'earned', from_date: '', to_date: '', reason: '' });
    const days = form.data.from_date && form.data.to_date
        ? Math.max(0, Math.round((new Date(form.data.to_date).getTime() - new Date(form.data.from_date).getTime()) / 86400000) + 1)
        : 0;

    return (
        <div className="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
            <div className="bg-white rounded-2xl shadow-xl w-full max-w-md">
                <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <h2 className="text-base font-semibold text-gray-900">Apply for Leave</h2>
                    <button onClick={onClose}><X size={16} className="text-gray-400" /></button>
                </div>
                <form onSubmit={e => { e.preventDefault(); form.post('/leave', { onSuccess: () => { onClose(); form.reset(); } }); }} className="px-6 py-4 space-y-4">
                    <div>
                        <label className="block text-xs font-medium text-gray-700 mb-1">Leave Type</label>
                        <select value={form.data.type} onChange={e => form.setData('type', e.target.value)}
                            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                            {['earned', 'sick', 'casual', 'wfh', 'unpaid'].map(t => <option key={t} value={t}>{t.charAt(0).toUpperCase() + t.slice(1)}</option>)}
                        </select>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="block text-xs font-medium text-gray-700 mb-1">From Date</label>
                            <input type="date" value={form.data.from_date} onChange={e => form.setData('from_date', e.target.value)}
                                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-700 mb-1">To Date</label>
                            <input type="date" value={form.data.to_date} onChange={e => form.setData('to_date', e.target.value)}
                                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                        </div>
                    </div>
                    {days > 0 && <p className="text-xs text-indigo-600 font-medium">{days} working day{days !== 1 ? 's' : ''}</p>}
                    <div>
                        <label className="block text-xs font-medium text-gray-700 mb-1">Reason</label>
                        <textarea value={form.data.reason} onChange={e => form.setData('reason', e.target.value)} rows={3}
                            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 resize-none" />
                    </div>
                    <div className="flex justify-end gap-3 pt-2">
                        <button type="button" onClick={onClose} className="px-4 py-2 text-sm text-gray-600">Cancel</button>
                        <button type="submit" disabled={form.processing}
                            className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                            {form.processing ? 'Submitting…' : 'Submit Request'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export default function LeaveIndex({ myRequests, teamRequests, balance, canReview }: Props) {
    const [showApply, setShowApply] = useState(false);

    return (
        <AppLayout title="Leave Management">
            <Head title="Leave Management" />
            <div className="max-w-5xl mx-auto px-4 py-6 space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Leave Management</h1>
                        <p className="text-sm text-gray-500 mt-0.5">Track and manage leave requests</p>
                    </div>
                    <button onClick={() => setShowApply(true)}
                        className="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                        <Plus size={14} /> Apply for Leave
                    </button>
                </div>

                <div className="grid grid-cols-3 gap-4">
                    <BalanceCard label="Earned Leave" used={balance.earned_used} total={balance.earned_total} color="bg-indigo-500" />
                    <BalanceCard label="Sick Leave" used={balance.sick_used} total={balance.sick_total} color="bg-rose-500" />
                    <BalanceCard label="Casual Leave" used={balance.casual_used} total={balance.casual_total} color="bg-amber-500" />
                </div>

                <div className="bg-white rounded-xl border border-gray-200">
                    <div className="px-5 py-4 border-b border-gray-100">
                        <h2 className="text-sm font-semibold text-gray-700">My Leave Requests</h2>
                    </div>
                    {myRequests.length === 0 ? (
                        <div className="py-8 text-center text-gray-400 text-sm">No leave requests yet.</div>
                    ) : (
                        <div className="divide-y divide-gray-100">
                            {myRequests.map(r => (
                                <div key={r.id} className="px-5 py-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                    <div>
                                        <div className="flex items-center gap-2 flex-wrap mb-1">
                                            <span className="text-sm font-medium text-gray-900 capitalize">{r.type} Leave</span>
                                            <span className={cn('px-2 py-0.5 rounded-full text-xs font-medium capitalize', STATUS_STYLES[r.status] || 'bg-gray-100 text-gray-700')}>{r.status}</span>
                                        </div>
                                        <div className="text-xs text-gray-500 flex items-center gap-1.5 mt-0.5">
                                            <Clock size={12} className="text-gray-400" />
                                            {r.from_date} to {r.to_date} ({r.days} day{r.days !== 1 ? 's' : ''})
                                        </div>
                                        {r.reason && <p className="text-xs text-gray-600 mt-1.5 max-w-lg border-l-2 border-gray-200 pl-2">{r.reason}</p>}
                                    </div>
                                    
                                    {/* Approval Tracking Section */}
                                    <div className="sm:text-right shrink-0">
                                        {r.status === 'pending' ? (
                                            <div className="text-xs text-gray-500 flex items-center gap-1.5 sm:justify-end">
                                                <Clock size={12} className="text-amber-500" />
                                                Waiting for approval
                                            </div>
                                        ) : (
                                            <div className="text-xs">
                                                <div className="flex items-center gap-1.5 sm:justify-end text-gray-900 font-medium">
                                                    {r.status === 'approved' ? (
                                                        <CheckCircle2 size={14} className="text-emerald-500" />
                                                    ) : (
                                                        <XCircle size={14} className="text-rose-500" />
                                                    )}
                                                    {r.status === 'approved' ? 'Approved' : 'Rejected'} by {r.reviewer?.name || 'Manager'}
                                                </div>
                                                {r.reviewed_at && (
                                                    <div className="text-gray-400 mt-0.5">on {r.reviewed_at}</div>
                                                )}
                                                {r.reviewer_notes && (
                                                    <div className="mt-1.5 text-gray-600 italic bg-gray-50 rounded p-1.5 text-[11px] inline-block sm:max-w-[250px] text-left">
                                                        "{r.reviewer_notes}"
                                                    </div>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {canReview && teamRequests.length > 0 && (
                    <div className="bg-white rounded-xl border border-gray-200">
                        <div className="px-5 py-4 border-b border-gray-100">
                            <h2 className="text-sm font-semibold text-gray-700">Team Leave Requests</h2>
                        </div>
                        <div className="divide-y divide-gray-100">
                            {teamRequests.map(r => (
                                <div key={r.id} className="px-5 py-3.5 flex items-center justify-between">
                                    <div>
                                        <div className="flex items-center gap-2 flex-wrap">
                                            <span className="text-sm font-medium text-gray-900">{r.user?.name}</span>
                                            <span className="text-sm text-gray-600 capitalize">· {r.type}</span>
                                            <span className={cn('px-2 py-0.5 rounded-full text-xs font-medium', STATUS_STYLES[r.status])}>{r.status}</span>
                                        </div>
                                        <p className="text-xs text-gray-500 mt-0.5">{r.from_date} → {r.to_date} · {r.days} days</p>
                                        {r.reason && <p className="text-xs text-gray-400 mt-0.5">{r.reason}</p>}
                                    </div>
                                    {r.status === 'pending' && (
                                        <div className="flex items-center gap-2">
                                            <form method="post" action={`/leave/${r.id}/approve`}>
                                                <input type="hidden" name="_token" value={(document.querySelector('meta[name=csrf-token]') as HTMLMetaElement)?.content} />
                                                <button type="submit" className="px-3 py-1.5 bg-emerald-600 text-white text-xs font-medium rounded-lg hover:bg-emerald-700">Approve</button>
                                            </form>
                                            <form method="post" action={`/leave/${r.id}/reject`}>
                                                <input type="hidden" name="_token" value={(document.querySelector('meta[name=csrf-token]') as HTMLMetaElement)?.content} />
                                                <button type="submit" className="px-3 py-1.5 bg-rose-600 text-white text-xs font-medium rounded-lg hover:bg-rose-700">Reject</button>
                                            </form>
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </div>
            {showApply && <ApplyModal onClose={() => setShowApply(false)} />}
        </AppLayout>
    );
}

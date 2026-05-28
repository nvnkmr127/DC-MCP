import React, { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { CreditCard, ChevronLeft, ChevronRight, CheckCircle2, Clock, FileText, Plus, X } from 'lucide-react';
import { cn } from '@/lib/utils';

interface PayrollRecord {
    id: string; base_salary: number; bonuses: number; deductions: number;
    net_pay: number; status: string; paid_at: string | null; notes: string | null;
}
interface Payslip {
    user: { id: string; name: string; email: string; role: string };
    record: PayrollRecord | null;
    salary: number;
}
interface Props { payslips: Payslip[]; monthYear: string; totalPayroll: number; }

const fmt = (n: number) => '₹' + new Intl.NumberFormat('en-IN', { maximumFractionDigits: 0 }).format(n);

const STATUS_STYLES: Record<string, string> = {
    draft:   'bg-gray-100 text-gray-600',
    pending: 'bg-amber-100 text-amber-700',
    paid:    'bg-emerald-100 text-emerald-700',
};

function MonthNav({ monthYear, onChange }: { monthYear: string; onChange: (m: string) => void }) {
    const navigate = (offset: number) => {
        const [y, m] = monthYear.split('-').map(Number);
        const d = new Date(y, m - 1 + offset, 1);
        onChange(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`);
    };
    const label = new Date(monthYear + '-01').toLocaleDateString('en-IN', { month: 'long', year: 'numeric' });
    return (
        <div className="flex items-center gap-2">
            <button onClick={() => navigate(-1)} className="p-1.5 rounded-lg border border-gray-200 hover:bg-gray-50"><ChevronLeft className="w-4 h-4" /></button>
            <span className="text-sm font-semibold text-gray-700 min-w-32 text-center">{label}</span>
            <button onClick={() => navigate(1)} className="p-1.5 rounded-lg border border-gray-200 hover:bg-gray-50"><ChevronRight className="w-4 h-4" /></button>
        </div>
    );
}

function PayslipCard({ payslip, monthYear }: { payslip: Payslip; monthYear: string }) {
    const [expanded, setExpanded] = useState(false);
    const form = useForm({
        user_id:     payslip.user.id,
        month_year:  monthYear,
        base_salary: String(payslip.record?.base_salary ?? payslip.salary ?? ''),
        bonuses:     String(payslip.record?.bonuses ?? '0'),
        deductions:  String(payslip.record?.deductions ?? '0'),
        notes:       payslip.record?.notes ?? '',
    });

    const r = payslip.record;

    return (
        <div className={cn('bg-white rounded-xl border p-4 space-y-3', r?.status === 'paid' ? 'border-emerald-200' : 'border-gray-200')}>
            <div className="flex items-start justify-between">
                <div className="flex items-center gap-3">
                    <div className="w-10 h-10 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-sm font-bold">
                        {payslip.user.name[0]}
                    </div>
                    <div>
                        <p className="font-semibold text-gray-900 text-sm">{payslip.user.name}</p>
                        <p className="text-xs text-gray-500 capitalize">{payslip.user.role?.replace('_', ' ')}</p>
                    </div>
                </div>
                <div className="text-right">
                    {r ? (
                        <>
                            <p className="text-lg font-bold text-gray-900">{fmt(r.net_pay)}</p>
                            <span className={cn('text-xs font-medium px-2 py-0.5 rounded-full', STATUS_STYLES[r.status] ?? STATUS_STYLES.draft)}>
                                {r.status}
                            </span>
                        </>
                    ) : (
                        <p className="text-sm text-gray-400">No record</p>
                    )}
                </div>
            </div>

            {r && (
                <div className="grid grid-cols-3 gap-2 text-xs">
                    <div className="bg-gray-50 rounded-lg p-2 text-center">
                        <p className="text-gray-400">Base</p>
                        <p className="font-semibold text-gray-700">{fmt(r.base_salary)}</p>
                    </div>
                    <div className="bg-emerald-50 rounded-lg p-2 text-center">
                        <p className="text-gray-400">Bonus</p>
                        <p className="font-semibold text-emerald-700">+{fmt(r.bonuses)}</p>
                    </div>
                    <div className="bg-rose-50 rounded-lg p-2 text-center">
                        <p className="text-gray-400">Deduction</p>
                        <p className="font-semibold text-rose-700">-{fmt(r.deductions)}</p>
                    </div>
                </div>
            )}

            <div className="flex items-center gap-2">
                <button onClick={() => setExpanded(!expanded)} className="flex-1 py-1.5 text-xs text-indigo-600 hover:text-indigo-800 font-medium border border-indigo-200 rounded-lg hover:bg-indigo-50 transition-colors">
                    {r ? 'Edit Record' : 'Add Record'}
                </button>
                {r?.status === 'pending' && (
                    <button onClick={() => router.post(`/payroll/${r.id}/paid`)}
                        className="flex items-center gap-1 px-3 py-1.5 text-xs bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 font-medium">
                        <CheckCircle2 className="w-3 h-3" /> Mark Paid
                    </button>
                )}
            </div>

            {expanded && (
                <form onSubmit={e => { e.preventDefault(); form.post('/payroll', { onSuccess: () => setExpanded(false) }); }}
                    className="space-y-2 pt-2 border-t border-gray-100">
                    <div className="grid grid-cols-3 gap-2">
                        <div>
                            <label className="text-xs text-gray-500">Base Salary</label>
                            <input type="number" value={form.data.base_salary} onChange={e => form.setData('base_salary', e.target.value)}
                                className="w-full border border-gray-300 rounded px-2 py-1.5 text-sm focus:ring-1 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label className="text-xs text-gray-500">Bonus</label>
                            <input type="number" value={form.data.bonuses} onChange={e => form.setData('bonuses', e.target.value)}
                                className="w-full border border-gray-300 rounded px-2 py-1.5 text-sm focus:ring-1 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label className="text-xs text-gray-500">Deduction</label>
                            <input type="number" value={form.data.deductions} onChange={e => form.setData('deductions', e.target.value)}
                                className="w-full border border-gray-300 rounded px-2 py-1.5 text-sm focus:ring-1 focus:ring-indigo-500" />
                        </div>
                    </div>
                    <input type="text" placeholder="Notes" value={form.data.notes} onChange={e => form.setData('notes', e.target.value)}
                        className="w-full border border-gray-300 rounded px-2 py-1.5 text-sm focus:ring-1 focus:ring-indigo-500" />
                    <div className="flex justify-end gap-2">
                        <button type="button" onClick={() => setExpanded(false)} className="px-3 py-1.5 text-xs text-gray-600">Cancel</button>
                        <button type="submit" disabled={form.processing}
                            className="px-3 py-1.5 bg-indigo-600 text-white text-xs rounded hover:bg-indigo-700 disabled:opacity-50">
                            {form.processing ? 'Saving…' : 'Save'}
                        </button>
                    </div>
                </form>
            )}
        </div>
    );
}

export default function PayrollIndex({ payslips, monthYear, totalPayroll }: Props) {
    const navigate = (m: string) => router.get('/payroll', { month: m }, { preserveState: true });

    const paid    = payslips.filter(p => p.record?.status === 'paid').length;
    const pending = payslips.filter(p => p.record?.status === 'pending').length;
    const noRecord= payslips.filter(p => !p.record).length;

    return (
        <AppLayout>
            <Head title="Payroll" />
            <div className="max-w-5xl mx-auto px-4 py-6 space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Payroll</h1>
                        <p className="text-sm text-gray-500 mt-0.5">Team salaries and payslip management</p>
                    </div>
                    <div className="flex items-center gap-3">
                        <MonthNav monthYear={monthYear} onChange={navigate} />
                        <button onClick={() => router.post('/payroll/bulk-generate', { month_year: monthYear })}
                            className="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                            <FileText className="w-4 h-4" /> Generate All
                        </button>
                    </div>
                </div>

                {/* Stats */}
                <div className="grid grid-cols-4 gap-4">
                    {[
                        { label: 'Total Payroll', value: fmt(totalPayroll), color: 'text-gray-900' },
                        { label: 'Paid', value: String(paid), color: 'text-emerald-600' },
                        { label: 'Pending', value: String(pending), color: 'text-amber-600' },
                        { label: 'No Record', value: String(noRecord), color: 'text-gray-400' },
                    ].map(({ label, value, color }) => (
                        <div key={label} className="bg-white rounded-xl border border-gray-200 p-4">
                            <p className="text-xs text-gray-500">{label}</p>
                            <p className={cn('text-2xl font-bold mt-1', color)}>{value}</p>
                        </div>
                    ))}
                </div>

                {/* Payslip Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {payslips.map(p => (
                        <PayslipCard key={p.user.id} payslip={p} monthYear={monthYear} />
                    ))}
                    {payslips.length === 0 && (
                        <div className="col-span-2 bg-white rounded-xl border border-gray-200 px-5 py-12 text-center">
                            <CreditCard className="w-10 h-10 text-gray-300 mx-auto mb-3" />
                            <p className="text-gray-400 text-sm">No team members yet. Add team members in Settings.</p>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}

import React, { useState } from 'react';
import { Button } from '@/Components/ui/Button';
import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { cn } from '@/lib/utils';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/Components/ui/Table";
import {
    Plus, X, ChevronLeft, ChevronRight, TrendingUp, DollarSign,
    PieChart, ArrowDownRight, AlertTriangle,
} from 'lucide-react';

// ── Indian Rupee formatter ──────────────────────────────────────────────────
const fmt = (n: number) => '₹' + new Intl.NumberFormat('en-IN', { maximumFractionDigits: 0 }).format(n);

// ── Types ───────────────────────────────────────────────────────────────────
interface BudgetEntry {
    id: string; channel: string; month_year: string;
    allocated_budget: number; spent_amount: number; remaining: number;
    utilization: number; currency: string;
    client: { id: string; name: string } | null;
}
interface Props {
    budgets: BudgetEntry[]; totalAllocated: number; totalSpent: number;
    monthYear: string; clients: { id: string; name: string; company: string }[];
}

// ── Channel config ───────────────────────────────────────────────────────────
const CHANNEL_CONFIG: Record<string, { label: string; badge: string; dot: string }> = {
    meta_ads:   { label: 'Meta Ads',    badge: 'bg-blue-100 text-blue-700',    dot: 'bg-blue-500' },
    google_ads: { label: 'Google Ads',  badge: 'bg-red-100 text-red-700',      dot: 'bg-red-500' },
    seo:        { label: 'SEO',         badge: 'bg-emerald-100 text-emerald-700', dot: 'bg-emerald-500' },
    email:      { label: 'Email',       badge: 'bg-violet-100 text-violet-700', dot: 'bg-violet-500' },
    linkedin:   { label: 'LinkedIn',    badge: 'bg-sky-100 text-sky-700',      dot: 'bg-sky-500' },
    twitter:    { label: 'Twitter/X',   badge: 'bg-slate-100 text-slate-700',  dot: 'bg-slate-500' },
    youtube:    { label: 'YouTube',     badge: 'bg-rose-100 text-rose-700',    dot: 'bg-rose-500' },
    other:      { label: 'Other',       badge: 'bg-gray-100 text-gray-700',    dot: 'bg-gray-400' },
};

function utilizationColor(pct: number): string {
    if (pct > 90) return 'bg-rose-500';
    if (pct > 70) return 'bg-amber-400';
    return 'bg-emerald-500';
}
function utilizationTextColor(pct: number): string {
    if (pct > 90) return 'text-rose-600';
    if (pct > 70) return 'text-amber-600';
    return 'text-emerald-600';
}

function prevMonth(my: string): string {
    const [y, m] = my.split('-').map(Number);
    const d = new Date(y, m - 2, 1);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}
function nextMonth(my: string): string {
    const [y, m] = my.split('-').map(Number);
    const d = new Date(y, m, 1);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}
function monthLabel(my: string): string {
    const [y, m] = my.split('-').map(Number);
    return new Date(y, m - 1, 1).toLocaleString('en-IN', { month: 'long', year: 'numeric' });
}

// ── Add Budget Modal ──────────────────────────────────────────────────────────
function AddBudgetModal({
    clients,
    monthYear,
    onClose,
}: {
    clients: Props['clients'];
    monthYear: string;
    onClose: () => void;
}) {
    const form = useForm({
        client_id:        '',
        channel:          'meta_ads',
        month_year:       monthYear,
        allocated_budget: '',
        spent_amount:     '',
        notes:            '',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.post('/campaign-budgets', { onSuccess: () => onClose() });
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
            <div className="bg-white rounded-2xl border border-gray-200 shadow-2xl w-full max-w-md">
                <div className="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <h2 className="text-sm font-semibold text-gray-900">Add Campaign Budget</h2>
                    <Button onClick={onClose} className="p-1.5" variant="secondary" size="icon" >
                        <X size={16} />
                    </Button>
                </div>
                <form onSubmit={submit} className="px-5 py-4 space-y-4">
                    <div>
                        <label className="block text-xs font-medium text-gray-600 mb-1.5">Client *</label>
                        <select
                            value={form.data.client_id}
                            onChange={e => form.setData('client_id', e.target.value)}
                            className="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-400 bg-white"
                            required
                        >
                            <option value="">Select client…</option>
                            {clients.map(c => (
                                <option key={c.id} value={c.id}>{c.company || c.name}</option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-gray-600 mb-1.5">Channel *</label>
                        <select
                            value={form.data.channel}
                            onChange={e => form.setData('channel', e.target.value)}
                            className="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-400 bg-white"
                        >
                            {Object.entries(CHANNEL_CONFIG).map(([v, c]) => (
                                <option key={v} value={v}>{c.label}</option>
                            ))}
                        </select>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="block text-xs font-medium text-gray-600 mb-1.5">Month</label>
                            <input
                                type="month"
                                value={form.data.month_year}
                                onChange={e => form.setData('month_year', e.target.value)}
                                className="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-400 bg-white"
                                required
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-600 mb-1.5">Allocated Budget (₹) *</label>
                            <input
                                type="number" min="0" step="0.01"
                                value={form.data.allocated_budget}
                                onChange={e => form.setData('allocated_budget', e.target.value)}
                                className="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-400 bg-white"
                                placeholder="0"
                                required
                            />
                        </div>
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-gray-600 mb-1.5">Spent So Far (₹)</label>
                        <input
                            type="number" min="0" step="0.01"
                            value={form.data.spent_amount}
                            onChange={e => form.setData('spent_amount', e.target.value)}
                            className="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-400 bg-white"
                            placeholder="0"
                        />
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-gray-600 mb-1.5">Notes</label>
                        <input
                            value={form.data.notes}
                            onChange={e => form.setData('notes', e.target.value)}
                            className="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-400 bg-white"
                            placeholder="Optional notes…"
                        />
                    </div>
                    <div className="flex items-center gap-2 pt-1">
                        <Button
                            type="submit"
                            disabled={form.processing || !form.data.client_id || !form.data.allocated_budget}
                            className="flex-1 py-2.5 disabled:opacity-60" 
                        >
                            {form.processing ? 'Saving…' : 'Save Budget'}
                        </Button>
                        <Button type="button" onClick={onClose} className="py-2.5" variant="ghost" >
                            Cancel
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}

// ── Main Page ────────────────────────────────────────────────────────────────
export default function CampaignBudgetsIndex({ budgets, totalAllocated, totalSpent, monthYear, clients }: Props) {
    const [showModal, setShowModal] = useState(false);

    function navigate(my: string) {
        router.get('/campaign-budgets', { month: my }, { preserveState: false });
    }

    const totalRemaining   = totalAllocated - totalSpent;
    const overallUtil      = totalAllocated > 0 ? Math.round((totalSpent / totalAllocated) * 100) : 0;

    // Group by client
    const byClient: Record<string, { name: string; entries: BudgetEntry[] }> = {};
    for (const b of budgets) {
        const key  = b.client?.id ?? '__none';
        const name = b.client?.name ?? 'No Client';
        if (!byClient[key]) byClient[key] = { name, entries: [] };
        byClient[key].entries.push(b);
    }

    return (
        <AppLayout>
            <Head title="Campaign Budgets" />

            {showModal && (
                <AddBudgetModal
                    clients={clients}
                    monthYear={monthYear}
                    onClose={() => setShowModal(false)}
                />
            )}

            <div className="max-w-7xl mx-auto px-4 py-6 space-y-6">

                {/* ── Header ─────────────────────────────────────────────── */}
                <div className="flex items-center justify-between flex-wrap gap-3">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Campaign Budgets</h1>
                        <p className="text-sm text-gray-500 mt-0.5">Ad spend tracking across all channels</p>
                    </div>
                    <div className="flex items-center gap-2">
                        {/* Month Selector */}
                        <div className="flex items-center gap-1 bg-white border border-gray-200 rounded-xl p-1 shadow-sm">
                            <Button onClick={() => navigate(prevMonth(monthYear))} className="p-2 rounded-lg hover:bg-gray-100 text-gray-700 transition-colors">
                                <ChevronLeft size={16} />
                            </Button>
                            <span className="px-3 text-sm font-semibold text-gray-800 min-w-[140px] text-center">
                                {monthLabel(monthYear)}
                            </span>
                            <Button onClick={() => navigate(nextMonth(monthYear))} className="p-2 rounded-lg hover:bg-gray-100 text-gray-700 transition-colors">
                                <ChevronRight size={16} />
                            </Button>
                        </div>
                        <Button
                            onClick={() => setShowModal(true)}
                            className="flex items-center gap-1.5 px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700 transition-colors shadow-sm"
                        >
                            <Plus size={16} /> Add Budget
                        </Button>
                    </div>
                </div>

                {/* ── Burn alerts ─────────────────────────────────────────── */}
                {budgets.filter(b => b.utilization > 90).length > 0 && (
                    <div className="space-y-1.5">
                        {budgets.filter(b => b.utilization > 90).map(b => (
                            <div key={b.id} className="flex items-center gap-2 bg-rose-50 border border-rose-200 rounded-xl px-4 py-2.5">
                                <AlertTriangle size={16} className="text-rose-500 shrink-0" />
                                <p className="text-sm text-rose-700 font-medium">
                                    Budget alert: <strong>{b.client?.name ?? 'Unknown'}</strong> — {CHANNEL_CONFIG[b.channel]?.label ?? b.channel} is{' '}
                                    <strong>{b.utilization}%</strong> spent ({fmt(b.spent_amount)} / {fmt(b.allocated_budget)})
                                </p>
                            </div>
                        ))}
                    </div>
                )}

                {/* ── Stats ────────────────────────────────────────────────── */}
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    {[
                        { label: 'Total Allocated', value: fmt(totalAllocated), icon: DollarSign,      bg: 'bg-indigo-50', iconCls: 'text-indigo-600', valCls: 'text-indigo-700' },
                        { label: 'Total Spent',     value: fmt(totalSpent),     icon: ArrowDownRight,  bg: 'bg-rose-50',   iconCls: 'text-rose-600',   valCls: 'text-rose-700' },
                        { label: 'Remaining',       value: fmt(totalRemaining), icon: TrendingUp,      bg: 'bg-emerald-50',iconCls: 'text-emerald-600', valCls: 'text-emerald-700' },
                        { label: 'Overall Utilization', value: `${overallUtil}%`, icon: PieChart, bg: utilizationColor(overallUtil).replace('bg-', 'bg-').replace('500', '50'), iconCls: utilizationTextColor(overallUtil), valCls: utilizationTextColor(overallUtil) },
                    ].map(({ label, value, icon: Icon, bg, iconCls, valCls }) => (
                        <div key={label} className="bg-white rounded-2xl border border-gray-200 p-4 flex items-center gap-3 shadow-sm">
                            <div className={cn('w-10 h-10 rounded-xl flex items-center justify-center shrink-0', bg)}>
                                <Icon size={20} className={iconCls} />
                            </div>
                            <div>
                                <p className="text-xs text-gray-500">{label}</p>
                                <p className={cn('text-xl font-bold', valCls)}>{value}</p>
                            </div>
                        </div>
                    ))}
                </div>

                {/* ── Budgets Table ─────────────────────────────────────────── */}
                {budgets.length === 0 ? (
                    <div className="flex flex-col items-center justify-center p-20 text-center relative overflow-hidden bg-white rounded-[2rem] border border-gray-100 shadow-sm min-h-[450px]">
                        <div className="absolute top-0 left-0 w-full h-full pointer-events-none">
                            <div className="absolute top-[-30%] left-[-10%] w-[50%] h-[80%] bg-indigo-500/5 blur-[80px] rounded-full"></div>
                            <div className="absolute bottom-[-30%] right-[-10%] w-[50%] h-[80%] bg-purple-500/5 blur-[80px] rounded-full"></div>
                        </div>

                        <div className="w-24 h-24 mb-6 rounded-[2rem] bg-gradient-to-tr from-indigo-500 to-purple-500 flex items-center justify-center shadow-[0_8px_30px_rgba(99,102,241,0.25)] text-white transform rotate-3 hover:rotate-0 transition-transform duration-500 z-10">
                            <PieChart size={48} className="transform -rotate-3 hover:rotate-0 transition-transform duration-500" />
                        </div>
                        
                        <h3 className="text-2xl font-extrabold text-gray-900 mb-3 tracking-tight z-10">No campaigns tracked yet.</h3>
                        <p className="text-[13px] md:text-sm text-gray-500 max-w-md mx-auto mb-8 z-10 leading-relaxed">
                            You don't have any campaign budgets set up for {monthLabel(monthYear)}. Track your ad spend across different channels and clients by adding your first budget.
                        </p>
                        
                        <Button
                            onClick={() => setShowModal(true)}
                            className="flex items-center justify-center gap-2 bg-indigo-600 text-white px-8 py-3.5 rounded-xl font-semibold hover:bg-indigo-700 transition-all shadow-[0_4px_14px_0_rgba(79,70,229,0.39)] hover:shadow-[0_6px_20px_rgba(79,70,229,0.23)] hover:-translate-y-0.5 z-10 relative"
                        >
                            <Plus size={20} />
                            Add Budget
                        </Button>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {Object.entries(byClient).map(([clientId, { name, entries }]) => {
                            const clientAllocated = entries.reduce((s, e) => s + e.allocated_budget, 0);
                            const clientSpent     = entries.reduce((s, e) => s + e.spent_amount, 0);
                            const clientUtil      = clientAllocated > 0 ? Math.round((clientSpent / clientAllocated) * 100) : 0;

                            return (
                                <div key={clientId} className="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                                    {/* Client header */}
                                    <div className="px-5 py-3.5 border-b border-gray-100 bg-gray-50/40 flex items-center justify-between">
                                        <div className="flex items-center gap-2.5">
                                            <div className="w-7 h-7 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-bold">
                                                {name[0]?.toUpperCase()}
                                            </div>
                                            <span className="text-sm font-semibold text-gray-800">{name}</span>
                                        </div>
                                        <div className="flex items-center gap-4 text-xs text-gray-500">
                                            <span>Allocated: <span className="font-semibold text-indigo-600">{fmt(clientAllocated)}</span></span>
                                            <span>Spent: <span className={cn('font-semibold', utilizationTextColor(clientUtil))}>{fmt(clientSpent)}</span></span>
                                            <span className={cn('font-bold', utilizationTextColor(clientUtil))}>{clientUtil}% used</span>
                                        </div>
                                    </div>

                                    {/* Channel rows */}
                                    <div className="overflow-x-auto">
                                        <Table className="w-full text-sm">
                                            <TableHeader>
                                                <TableRow className="border-b border-gray-100">
                                                    <TableHead className="text-left px-5 py-2.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Channel</TableHead>
                                                    <TableHead className="text-right px-4 py-2.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Allocated</TableHead>
                                                    <TableHead className="text-right px-4 py-2.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Spent</TableHead>
                                                    <TableHead className="text-right px-4 py-2.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Remaining</TableHead>
                                                    <TableHead className="px-5 py-2.5 text-xs font-semibold text-gray-400 uppercase tracking-wide w-40">Utilization</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody className="divide-y divide-gray-50">
                                                {entries.map((b) => {
                                                    const ch  = CHANNEL_CONFIG[b.channel] ?? CHANNEL_CONFIG.other;
                                                    const pct = Math.min(b.utilization, 100);
                                                    return (
                                                        <TableRow key={b.id} className="hover:bg-gray-50/50 transition-colors">
                                                            <TableCell className="px-5 py-3.5">
                                                                <span className={cn('flex items-center gap-2 w-fit px-2.5 py-0.5 rounded-full text-xs font-semibold', ch.badge)}>
                                                                    <span className={cn('w-1.5 h-1.5 rounded-full', ch.dot)} />
                                                                    {ch.label}
                                                                </span>
                                                            </TableCell>
                                                            <TableCell className="px-4 py-3.5 text-right font-medium text-gray-800">{fmt(b.allocated_budget)}</TableCell>
                                                            <TableCell className="px-4 py-3.5 text-right font-medium text-gray-600">{fmt(b.spent_amount)}</TableCell>
                                                            <TableCell className={cn('px-4 py-3.5 text-right font-semibold', b.remaining >= 0 ? 'text-emerald-600' : 'text-rose-600')}>
                                                                {fmt(b.remaining)}
                                                            </TableCell>
                                                            <TableCell className="px-5 py-3.5">
                                                                <div className="flex items-center gap-2">
                                                                    <div className="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
                                                                        <div
                                                                            className={cn('h-full rounded-full transition-all', utilizationColor(b.utilization))}
                                                                            style={{ width: `${pct}%` }}
                                                                        />
                                                                    </div>
                                                                    <span className={cn('text-[11px] font-semibold w-9 text-right', utilizationTextColor(b.utilization))}>
                                                                        {b.utilization}%
                                                                    </span>
                                                                </div>
                                                            </TableCell>
                                                        </TableRow>
                                                    );
                                                })}
                                            </TableBody>
                                        </Table>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}

            </div>
        </AppLayout>
    );
}

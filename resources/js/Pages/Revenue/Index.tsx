import React, { useState } from 'react';
import { Button } from '@/Components/ui/Button';
import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import {
    TrendingUp, AlertCircle, RefreshCw, DollarSign,
    Plus, X, CheckCircle2, PauseCircle, XCircle,
    Heart, ChevronDown, ChevronUp,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/Components/ui/Table";

interface Retainer {
    id: string; name: string; monthly_value: number; currency: string;
    billing_cycle: string; status: string; start_date: string | null;
    end_date: string | null; next_renewal_date: string | null; auto_renew: boolean;
    invoices_count: number;
    client: { id: string; name: string; health_score: number | null; health_status: string | null } | null;
}
interface Stats {
    mrr: number; overdue_count: number; overdue_amount: number;
    renewals_count: number; pipeline_weighted: number;
    red_clients: number; yellow_clients: number;
}
interface Client { id: string; name: string; company: string; }
interface Props { retainers: Retainer[]; stats: Stats; clients: Client[]; }

const fmt = (n: number) =>
    '₹' + new Intl.NumberFormat('en-IN', { maximumFractionDigits: 0 }).format(n);

const HEALTH_COLORS: Record<string, string> = {
    green:  'bg-emerald-100 text-emerald-700 border-emerald-200',
    yellow: 'bg--100 text--800 border-amber-200',
    red:    'bg-rose-100 text-rose-700 border-rose-200',
};

const STATUS_ICON: Record<string, React.ReactNode> = {
    active:    <CheckCircle2 className="w-3.5 h-3.5 text-emerald-500" />,
    paused:    <PauseCircle className="w-3.5 h-3.5 text-amber-500" />,
    cancelled: <XCircle className="w-3.5 h-3.5 text-gray-400" />,
};

export default function RevenueIndex({ retainers, stats, clients }: Props) {
    const [showCreate, setShowCreate] = useState(false);

    const form = useForm({
        client_id: '', name: '', monthly_value: '', currency: 'INR',
        billing_cycle: 'monthly', start_date: '', auto_renew: true, notes: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/retainers', { onSuccess: () => { setShowCreate(false); form.reset(); } });
    };

    return (
        <AppLayout>
            <Head title="Revenue" />

            <div className="max-w-7xl mx-auto px-4 py-6 space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Revenue</h1>
                        <p className="text-sm text-gray-500 mt-0.5">Retainers, invoices & client health</p>
                    </div>
                    <Button
                        onClick={() => setShowCreate(true)}
                        className="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors"
                    >
                        <Plus className="w-4 h-4" /> Add Retainer
                    </Button>
                </div>

                {/* Stats */}
                <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
                    {[
                        { label: 'Monthly MRR', value: fmt(stats.mrr), icon: TrendingUp, color: 'text-emerald-600', bg: 'bg-emerald-50' },
                        { label: 'Overdue Invoices', value: `${stats.overdue_count} (${fmt(stats.overdue_amount)})`, icon: AlertCircle, color: 'text-rose-600', bg: 'bg-rose-50' },
                        { label: 'Renewals (30d)', value: String(stats.renewals_count), icon: RefreshCw, color: 'text-amber-600', bg: 'bg-amber-50' },
                        { label: 'Pipeline (Weighted)', value: fmt(stats.pipeline_weighted), icon: DollarSign, color: 'text-violet-600', bg: 'bg-violet-50' },
                        { label: 'At-Risk Clients', value: `${stats.red_clients} red · ${stats.yellow_clients} yellow`, icon: Heart, color: 'text-orange-600', bg: 'bg-orange-50' },
                    ].map(({ label, value, icon: Icon, color, bg }) => (
                        <div key={label} className="bg-white rounded-xl border border-gray-200 p-4">
                            <div className={cn('w-8 h-8 rounded-lg flex items-center justify-center mb-2', bg)}>
                                <Icon className={cn('w-4 h-4', color)} />
                            </div>
                            <p className="text-xs text-gray-500">{label}</p>
                            <p className="text-base font-semibold text-gray-900 mt-0.5">{value}</p>
                        </div>
                    ))}
                </div>

                {/* Retainers Table */}
                <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <div className="px-5 py-4 border-b border-gray-100">
                        <h2 className="text-sm font-semibold text-gray-700">Active Retainers</h2>
                    </div>
                    <div className="overflow-x-auto">
                        <Table className="w-full text-sm">
                            <TableHeader className="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
                                <TableRow>
                                    {['Client', 'Retainer', 'Monthly Value', 'Cycle', 'Status', 'Next Renewal', 'Health'].map(h => (
                                        <TableHead key={h} className="px-4 py-3 text-left font-medium">{h}</TableHead>
                                    ))}
                                </TableRow>
                            </TableHeader>
                            <TableBody className="divide-y divide-gray-100">
                                {retainers.length === 0 && (
                                    <TableRow><TableCell colSpan={7} className="px-4 py-8 text-center text-gray-400">No retainers yet. Add your first client retainer.</TableCell></TableRow>
                                )}
                                {retainers.map(r => (
                                    <TableRow key={r.id} className="hover:bg-gray-50 transition-colors">
                                        <TableCell className="px-4 py-3 font-medium text-gray-900">{r.client?.name ?? '—'}</TableCell>
                                        <TableCell className="px-4 py-3 text-gray-700">{r.name}</TableCell>
                                        <TableCell className="px-4 py-3 font-semibold text-gray-900">{fmt(r.monthly_value)}</TableCell>
                                        <TableCell className="px-4 py-3 capitalize text-gray-600">{r.billing_cycle}</TableCell>
                                        <TableCell className="px-4 py-3">
                                            <span className="flex items-center gap-1.5">
                                                {STATUS_ICON[r.status]}
                                                <span className="capitalize">{r.status}</span>
                                            </span>
                                        </TableCell>
                                        <TableCell className="px-4 py-3 text-gray-600">
                                            {r.next_renewal_date ?? '—'}
                                            {r.auto_renew && <span className="ml-1 text-xs text-emerald-500">(auto)</span>}
                                        </TableCell>
                                        <TableCell className="px-4 py-3">
                                            {r.client?.health_status ? (
                                                <span className={cn('inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border', HEALTH_COLORS[r.client.health_status])}>
                                                    {r.client.health_score ?? '?'}
                                                </span>
                                            ) : <span className="text-gray-300">—</span>}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                </div>
            </div>

            {/* Create Modal */}
            {showCreate && (
                <div className="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
                    <div className="bg-white rounded-2xl shadow-xl w-full max-w-md">
                        <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                            <h2 className="text-base font-semibold text-gray-900">New Retainer</h2>
                            <Button onClick={() => setShowCreate(false)} className="text-gray-400 hover:text-gray-600">
                                <X className="w-5 h-5" />
                            </Button>
                        </div>
                        <form onSubmit={submit} className="px-6 py-4 space-y-4">
                            <div>
                                <label className="block text-xs font-medium text-gray-700 mb-1">Client</label>
                                <select value={form.data.client_id} onChange={e => form.setData('client_id', e.target.value)}
                                    className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="">Select client…</option>
                                    {clients.map(c => <option key={c.id} value={c.id}>{c.company || c.name}</option>)}
                                </select>
                                {form.errors.client_id && <p className="text-xs text-rose-500 mt-1">{form.errors.client_id}</p>}
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-gray-700 mb-1">Retainer Name</label>
                                <input type="text" value={form.data.name} onChange={e => form.setData('name', e.target.value)}
                                    placeholder="e.g. SEO Monthly Retainer"
                                    className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                                {form.errors.name && <p className="text-xs text-rose-500 mt-1">{form.errors.name}</p>}
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-xs font-medium text-gray-700 mb-1">Monthly Value (₹)</label>
                                    <input type="number" min="0" value={form.data.monthly_value} onChange={e => form.setData('monthly_value', e.target.value)}
                                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-gray-700 mb-1">Billing Cycle</label>
                                    <select value={form.data.billing_cycle} onChange={e => form.setData('billing_cycle', e.target.value)}
                                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                        <option value="monthly">Monthly</option>
                                        <option value="quarterly">Quarterly</option>
                                        <option value="annual">Annual</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-gray-700 mb-1">Start Date</label>
                                <input type="date" value={form.data.start_date} onChange={e => form.setData('start_date', e.target.value)}
                                    className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                            </div>
                            <label className="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                <input type="checkbox" checked={form.data.auto_renew} onChange={e => form.setData('auto_renew', e.target.checked)}
                                    className="rounded border-gray-300 text-indigo-600" />
                                Auto-renew
                            </label>
                            <div className="flex justify-end gap-3 pt-2">
                                <Button type="button" onClick={() => setShowCreate(false)}
                                    className="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancel</Button>
                                <Button type="submit" disabled={form.processing}
                                    className="disabled:opacity-50" >
                                    {form.processing ? 'Saving…' : 'Create Retainer'}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}

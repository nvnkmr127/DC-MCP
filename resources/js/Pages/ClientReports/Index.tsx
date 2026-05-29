import React, { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { cn } from '@/lib/utils';
import { Plus, X, Sparkles, Send, Trash2, ChevronDown } from 'lucide-react';

interface Metric { key: string; value: string; }
interface Report {
    id: string; month_year: string; status: string; highlights: string | null;
    challenges: string | null; metrics: Record<string, string>;
    client: { id: string; name: string } | null;
}
interface Client { id: string; name: string; }
interface Props { reports: Report[]; clients: Client[]; }

const STATUS_STYLES: Record<string, string> = { draft: 'bg-amber-100 text-amber-700', sent: 'bg-emerald-100 text-emerald-700' };

function ReportModal({ clients, onClose }: { clients: Client[]; onClose: () => void }) {
    const form = useForm({ client_id: '', month_year: new Date().toISOString().slice(0, 7), highlights: '', challenges: '' });
    const [metrics, setMetrics] = useState<Metric[]>([{ key: '', value: '' }]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const metricsObj = Object.fromEntries(metrics.filter(m => m.key).map(m => [m.key, m.value]));
        form.transform(d => ({ ...d, metrics: metricsObj }));
        form.post('/client-reports', { onSuccess: onClose });
    };

    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 space-y-4 max-h-[90vh] overflow-y-auto">
                <div className="flex items-center justify-between">
                    <h2 className="text-[15px] font-bold text-gray-900">New Report</h2>
                    <button onClick={onClose}><X size={16} className="text-gray-400" /></button>
                </div>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Client *</label>
                            <select value={form.data.client_id} onChange={e => form.setData('client_id', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                <option value="">Select client…</option>
                                {clients.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Month *</label>
                            <input type="month" value={form.data.month_year} onChange={e => form.setData('month_year', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                        </div>
                    </div>
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Highlights</label>
                        <textarea value={form.data.highlights} onChange={e => form.setData('highlights', e.target.value)} rows={3}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 resize-none" />
                    </div>
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Challenges</label>
                        <textarea value={form.data.challenges} onChange={e => form.setData('challenges', e.target.value)} rows={2}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 resize-none" />
                    </div>
                    <div>
                        <div className="flex items-center justify-between mb-1">
                            <label className="text-xs text-gray-500 font-medium">Metrics (key / value)</label>
                            <button type="button" onClick={() => setMetrics(m => [...m, { key: '', value: '' }])}
                                className="text-xs text-indigo-600 font-medium flex items-center gap-1"><Plus size={11} /> Add</button>
                        </div>
                        {metrics.map((m, i) => (
                            <div key={i} className="flex gap-2 mb-1.5">
                                <input type="text" placeholder="Metric name" value={m.key}
                                    onChange={e => { const n = [...metrics]; n[i].key = e.target.value; setMetrics(n); }}
                                    className="flex-1 border border-gray-300 rounded-lg px-2 py-1.5 text-xs focus:ring-1 focus:ring-indigo-500" />
                                <input type="text" placeholder="Value" value={m.value}
                                    onChange={e => { const n = [...metrics]; n[i].value = e.target.value; setMetrics(n); }}
                                    className="flex-1 border border-gray-300 rounded-lg px-2 py-1.5 text-xs focus:ring-1 focus:ring-indigo-500" />
                                <button type="button" onClick={() => setMetrics(m => m.filter((_, j) => j !== i))} className="text-gray-400 hover:text-rose-500">
                                    <X size={13} />
                                </button>
                            </div>
                        ))}
                    </div>
                    <div className="flex justify-end gap-2 pt-1">
                        <button type="button" onClick={onClose} className="px-4 py-2 text-sm text-gray-600">Cancel</button>
                        <button type="submit" disabled={form.processing || !form.data.client_id}
                            className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                            {form.processing ? 'Creating…' : 'Create Report'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export default function ClientReportsIndex({ reports, clients }: Props) {
    const [modalOpen, setModalOpen] = useState(false);
    const [expandedId, setExpandedId] = useState<string | null>(null);
    const [clientFilter, setClientFilter] = useState('');

    const filtered = clientFilter ? reports.filter(r => r.client?.id === clientFilter) : reports;
    const fmtMonth = (m: string) => new Date(m + '-01').toLocaleString('en-IN', { month: 'long', year: 'numeric' });

    return (
        <AppLayout title="Client Reports">
            <Head title="Client Reports" />
            <div className="max-w-4xl space-y-5">
                <div className="flex items-center justify-between">
                    <h1 className="text-lg font-bold text-gray-900">Monthly Client Reports</h1>
                    <button onClick={() => setModalOpen(true)}
                        className="flex items-center gap-2 px-3 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                        <Plus size={14} /> New Report
                    </button>
                </div>

                <div className="flex items-center gap-3">
                    <select value={clientFilter} onChange={e => setClientFilter(e.target.value)}
                        className="border border-gray-200 rounded-lg px-3 py-1.5 text-sm text-gray-600 bg-white focus:ring-1 focus:ring-indigo-500">
                        <option value="">All Clients</option>
                        {clients.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                    </select>
                    <span className="text-xs text-gray-500">{filtered.length} report{filtered.length !== 1 ? 's' : ''}</span>
                </div>

                <div className="space-y-3">
                    {filtered.length === 0 && (
                        <div className="bg-white rounded-xl border border-dashed border-gray-200 px-5 py-10 text-center text-sm text-gray-400">
                            No reports yet.
                        </div>
                    )}
                    {filtered.map(r => (
                        <div key={r.id} className="bg-white rounded-xl border border-gray-200">
                            <div className="px-5 py-4 flex items-center gap-4">
                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center gap-2">
                                        <p className="text-sm font-semibold text-gray-900">{r.client?.name ?? '—'}</p>
                                        <span className={cn('px-2 py-0.5 rounded-full text-[10px] font-semibold', STATUS_STYLES[r.status])}>
                                            {r.status}
                                        </span>
                                    </div>
                                    <p className="text-xs text-gray-500 mt-0.5">{fmtMonth(r.month_year)}</p>
                                    {r.highlights && (
                                        <p className="text-xs text-gray-600 mt-1 line-clamp-1">{r.highlights}</p>
                                    )}
                                </div>
                                <div className="flex items-center gap-1.5 shrink-0">
                                    {r.status === 'draft' && (
                                        <>
                                            <button onClick={() => router.post(`/client-reports/${r.id}/draft`)}
                                                className="flex items-center gap-1 px-2.5 py-1.5 border border-indigo-200 text-indigo-600 text-xs font-medium rounded-lg hover:bg-indigo-50">
                                                <Sparkles size={11} /> AI Draft
                                            </button>
                                            <button onClick={() => router.post(`/client-reports/${r.id}/send`)}
                                                className="flex items-center gap-1 px-2.5 py-1.5 border border-emerald-200 text-emerald-600 text-xs font-medium rounded-lg hover:bg-emerald-50">
                                                <Send size={11} /> Send
                                            </button>
                                        </>
                                    )}
                                    <button onClick={() => setExpandedId(expandedId === r.id ? null : r.id)}
                                        className="p-1.5 text-gray-400 hover:text-gray-600">
                                        <ChevronDown size={14} className={cn('transition-transform', expandedId === r.id && 'rotate-180')} />
                                    </button>
                                    <button onClick={() => { if (confirm('Delete report?')) router.delete(`/client-reports/${r.id}`); }}
                                        className="p-1.5 text-gray-400 hover:text-rose-500">
                                        <Trash2 size={13} />
                                    </button>
                                </div>
                            </div>
                            {expandedId === r.id && (
                                <div className="border-t border-gray-100 px-5 py-4 space-y-3">
                                    {r.highlights && <div><p className="text-xs font-semibold text-gray-600 mb-1">Highlights</p><p className="text-sm text-gray-700">{r.highlights}</p></div>}
                                    {r.challenges && <div><p className="text-xs font-semibold text-gray-600 mb-1">Challenges</p><p className="text-sm text-gray-700">{r.challenges}</p></div>}
                                    {Object.keys(r.metrics ?? {}).length > 0 && (
                                        <div>
                                            <p className="text-xs font-semibold text-gray-600 mb-2">Metrics</p>
                                            <div className="grid grid-cols-3 gap-2">
                                                {Object.entries(r.metrics).map(([k, v]) => (
                                                    <div key={k} className="bg-gray-50 rounded-lg px-3 py-2">
                                                        <p className="text-xs text-gray-500">{k}</p>
                                                        <p className="text-sm font-semibold text-gray-900">{v}</p>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            </div>
            {modalOpen && <ReportModal clients={clients} onClose={() => setModalOpen(false)} />}
        </AppLayout>
    );
}

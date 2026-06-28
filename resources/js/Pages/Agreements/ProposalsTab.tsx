import React, { useState } from 'react';
import { Button } from '@/Components/ui/Button';
import { Link, router, useForm } from '@inertiajs/react';

import { cn } from '@/lib/utils';
import { useConfirm } from '@/hooks/useConfirm';
import { Plus, X, Send, CheckCircle, XCircle, Eye, Trash2 } from 'lucide-react';

interface LineItem { service: string; description: string; unit_price: number; quantity: number; frequency: string; }
interface Proposal {
    id: string; title: string; status: string; valid_until: string | null;
    total_value: number; subtotal: number; sent_at: string | null; accepted_at: string | null;
    client: { id: string; name: string } | null;
    line_items: LineItem[];
}
interface Client { id: string; name: string; }
interface Stats { total_sent: number; accepted_value: number; conversion_rate: number; }
interface Props { proposals: Proposal[]; stats: Stats; clients: Client[]; }

const STATUS_STYLES: Record<string, string> = {
    draft:    'bg-gray-100 text-gray-700',
    sent:     'bg-blue-100 text-blue-700',
    accepted: 'bg-emerald-100 text-emerald-700',
    rejected: 'bg-rose-100 text-rose-700',
    expired:  'bg--100 text--800',
};

const fmt = (n: number) => '₹' + new Intl.NumberFormat('en-IN', { maximumFractionDigits: 0 }).format(n);

function ProposalModal({ clients, onClose }: { clients: Client[]; onClose: () => void }) {
    const [items, setItems] = useState([{ service: '', description: '', unit_price: '', quantity: '1', frequency: 'one_time' }]);
    const form = useForm({ title: '', client_id: '', valid_until: '', notes: '' });

    const addItem = () => setItems([...items, { service: '', description: '', unit_price: '', quantity: '1', frequency: 'one_time' }]);
    const removeItem = (i: number) => setItems(items.filter((_, idx) => idx !== i));
    const updateItem = (i: number, field: string, value: string) => {
        const newItems = [...items];
        (newItems[i] as any)[field] = value;
        setItems(newItems);
    };
    const subtotal = items.reduce((s, li) => s + (parseFloat(li.unit_price || '0') * parseFloat(li.quantity || '0')), 0);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const data = {
            ...form.data,
            line_items: items.map(li => ({ ...li, unit_price: parseFloat(li.unit_price || '0'), quantity: parseFloat(li.quantity || '1') })),
        };
        form.transform(() => data);
        form.post('/proposals', { onSuccess: onClose });
    };

    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-2xl shadow-xl w-full max-w-2xl p-6 space-y-4 max-h-[90vh] overflow-y-auto">
                <div className="flex items-center justify-between">
                    <h2 className="text-[15px] font-bold text-gray-900">New Proposal</h2>
                    <Button onClick={onClose}><X size={16} className="text-gray-400" /></Button>
                </div>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid grid-cols-2 gap-3">
                        <div className="col-span-2">
                            <label className="text-xs text-gray-500 font-medium">Title *</label>
                            <input type="text" value={form.data.title} onChange={e => form.setData('title', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Client *</label>
                            <select value={form.data.client_id} onChange={e => form.setData('client_id', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                <option value="">Select client…</option>
                                {clients.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Valid Until</label>
                            <input type="date" value={form.data.valid_until} onChange={e => form.setData('valid_until', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                        </div>
                    </div>

                    <div>
                        <div className="flex items-center justify-between mb-2">
                            <label className="text-xs text-gray-500 font-medium">Line Items *</label>
                            <Button type="button" onClick={addItem} className="flex items-center gap-1" variant="ghost" size="sm" >
                                <Plus size={12} /> Add Row
                            </Button>
                        </div>
                        <div className="space-y-2">
                            {items.map((item, i) => (
                                <div key={i} className="grid grid-cols-12 gap-2 items-center">
                                    <input type="text" placeholder="Service" value={item.service}
                                        onChange={e => updateItem(i, 'service', e.target.value)}
                                        className="col-span-4 border border-gray-300 rounded-lg px-2 py-1.5 text-xs focus:ring-1 focus:ring-indigo-500" />
                                    <input type="number" placeholder="Price (₹)" value={item.unit_price}
                                        onChange={e => updateItem(i, 'unit_price', e.target.value)}
                                        className="col-span-2 border border-gray-300 rounded-lg px-2 py-1.5 text-xs focus:ring-1 focus:ring-indigo-500" />
                                    <input type="number" placeholder="Qty" value={item.quantity}
                                        onChange={e => updateItem(i, 'quantity', e.target.value)}
                                        className="col-span-2 border border-gray-300 rounded-lg px-2 py-1.5 text-xs focus:ring-1 focus:ring-indigo-500" />
                                    <select value={item.frequency} onChange={e => updateItem(i, 'frequency', e.target.value)}
                                        className="col-span-3 border border-gray-300 rounded-lg px-2 py-1.5 text-xs focus:ring-1 focus:ring-indigo-500">
                                        <option value="one_time">One Time</option>
                                        <option value="monthly">Monthly</option>
                                        <option value="quarterly">Quarterly</option>
                                        <option value="annual">Annual</option>
                                    </select>
                                    <Button type="button" onClick={() => removeItem(i)} className="col-span-1 text-gray-400 hover:text-rose-500 flex justify-center">
                                        <X size={16} />
                                    </Button>
                                </div>
                            ))}
                        </div>
                        <div className="text-right mt-2">
                            <span className="text-sm font-semibold text-gray-900">Subtotal: {fmt(subtotal)}</span>
                        </div>
                    </div>

                    <div>
                        <label className="text-xs text-gray-500 font-medium">Notes</label>
                        <textarea value={form.data.notes} onChange={e => form.setData('notes', e.target.value)} rows={2}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 resize-none" />
                    </div>

                    <div className="flex justify-end gap-2 pt-1">
                        <Button type="button" onClick={onClose} variant="ghost" >Cancel</Button>
                        <Button type="submit" disabled={form.processing || !form.data.title || !form.data.client_id}
                            className="disabled:opacity-50" >
                            {form.processing ? 'Creating…' : 'Create Proposal'}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export default function ProposalsIndex({ proposals, stats, clients }: Props) {
    const [modalOpen, setModalOpen] = useState(false);
    const confirm = useConfirm();

    return (
        <div>
            
            <div className="max-w-5xl space-y-5">
                <div className="flex items-center justify-between">
                    <h1 className="text-lg font-bold text-gray-900">Proposals</h1>
                    <Button onClick={() => setModalOpen(true)}
                        className="flex items-center gap-2 px-3 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                        <Plus size={16} /> New Proposal
                    </Button>
                </div>

                <div className="grid grid-cols-3 gap-4">
                    {[
                        { label: 'Sent', value: stats.total_sent.toString(), sub: 'total proposals sent' },
                        { label: 'Accepted Value', value: fmt(stats.accepted_value), sub: 'total accepted' },
                        { label: 'Conversion Rate', value: `${stats.conversion_rate}%`, sub: 'sent → accepted' },
                    ].map(({ label, value, sub }) => (
                        <div key={label} className="bg-white rounded-xl border border-gray-200 p-4">
                            <p className="text-xs text-gray-500">{label}</p>
                            <p className="text-2xl font-bold text-gray-900 mt-1">{value}</p>
                            <p className="text-xs text-gray-400 mt-0.5">{sub}</p>
                        </div>
                    ))}
                </div>

                <div className="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
                    {proposals.length === 0 ? (
                        <div className="px-5 py-10 text-center text-sm text-gray-400">No proposals yet.</div>
                    ) : proposals.map(p => (
                        <div key={p.id} className="px-5 py-4 flex items-center gap-4">
                            <div className="flex-1 min-w-0">
                                <div className="flex items-center gap-2">
                                    <p className="text-sm font-semibold text-gray-900">{p.title}</p>
                                    <span className={cn('px-2 py-0.5 rounded-full text-[10px] font-semibold capitalize', STATUS_STYLES[p.status] ?? STATUS_STYLES.draft)}>
                                        {p.status}
                                    </span>
                                </div>
                                <p className="text-xs text-gray-500 mt-0.5">
                                    {p.client?.name ?? '—'}
                                    {p.valid_until ? ` · Valid until ${new Date(p.valid_until).toLocaleDateString('en-IN')}` : ''}
                                </p>
                            </div>
                            <p className="text-sm font-semibold text-gray-900">{fmt(p.total_value)}</p>
                            <div className="flex items-center gap-1">
                                <Link href={`/proposals/${p.id}`}
                                    className="p-1.5 text-gray-400 hover:text-indigo-600 rounded hover:bg-indigo-50 transition-colors">
                                    <Eye size={16} />
                                </Link>
                                {p.status === 'draft' && (
                                    <Button onClick={() => router.post(`/proposals/${p.id}/send`)}
                                        className="p-1.5 text-gray-400 hover:text-blue-600 rounded hover:bg-blue-50 transition-colors">
                                        <Send size={16} />
                                    </Button>
                                )}
                                {p.status === 'sent' && (
                                    <>
                                        <Button onClick={() => router.post(`/proposals/${p.id}/accept`)}
                                            className="p-1.5 text-gray-400 hover:text-emerald-600 rounded hover:bg-emerald-50 transition-colors">
                                            <CheckCircle size={16} />
                                        </Button>
                                        <Button onClick={() => router.post(`/proposals/${p.id}/reject`)}
                                            className="p-1.5 text-gray-400 hover:text-rose-600 rounded hover:bg-rose-50 transition-colors">
                                            <XCircle size={16} />
                                        </Button>
                                    </>
                                )}
                                <Button onClick={async () => {
                                    const ok = await confirm({
                                        title: 'Delete proposal?',
                                        description: 'This action cannot be undone.',
                                        confirmText: 'Delete',
                                        variant: 'destructive',
                                    });
                                    if (!ok) return;
                                    router.delete(`/proposals/${p.id}`);
                                }}
                                    className="p-1.5 text-gray-400 hover:text-rose-500 rounded hover:bg-rose-50 transition-colors">
                                    <Trash2 size={16} />
                                </Button>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
            {modalOpen && <ProposalModal clients={clients} onClose={() => setModalOpen(false)} />}
        </div>
    );
}

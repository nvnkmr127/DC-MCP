import React, { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Plus, X, FileText, Trash2, ChevronDown, ChevronUp } from 'lucide-react';
import { cn } from '@/lib/utils';

interface Deliverable {
    id: string; title: string; service_type: string; frequency: string;
    quantity_per_period: number; notes: string | null;
}
interface Sow {
    id: string; title: string; description: string | null; status: string;
    start_date: string | null; end_date: string | null;
    deliverables: Deliverable[];
    client: { id: string; name: string } | null;
    retainer: { id: string; name: string } | null;
}
interface Client { id: string; name: string; company: string; }
interface Retainer { id: string; name: string; client_id: string; }
interface Props { sows: Sow[]; clients: Client[]; retainers: Retainer[]; }

const STATUS_STYLES: Record<string, string> = {
    draft:   'bg-gray-100 text-gray-600',
    active:  'bg-emerald-100 text-emerald-700',
    expired: 'bg-amber-100 text-amber-700',
};

const SERVICE_LABELS: Record<string, string> = {
    seo: 'SEO', ads: 'Ads', social: 'Social', content: 'Content',
    design: 'Design', dev: 'Dev', email: 'Email', other: 'Other',
};

const SERVICE_COLORS: Record<string, string> = {
    seo:     'bg-blue-100 text-blue-700',
    ads:     'bg-violet-100 text-violet-700',
    social:  'bg-pink-100 text-pink-700',
    content: 'bg-teal-100 text-teal-700',
    design:  'bg-orange-100 text-orange-700',
    dev:     'bg-indigo-100 text-indigo-700',
    email:   'bg-cyan-100 text-cyan-700',
    other:   'bg-gray-100 text-gray-600',
};

type DeliverableForm = { title: string; service_type: string; frequency: string; quantity_per_period: string; notes: string };

function SowCard({ sow }: { sow: Sow }) {
    const [expanded, setExpanded] = useState(false);

    return (
        <div className="bg-white rounded-xl border border-gray-200">
            <div className="px-5 py-4 flex items-start justify-between gap-3">
                <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 flex-wrap">
                        <h3 className="font-semibold text-gray-900">{sow.title}</h3>
                        <span className={cn('px-2 py-0.5 rounded-full text-xs font-medium', STATUS_STYLES[sow.status] ?? STATUS_STYLES.draft)}>
                            {sow.status}
                        </span>
                    </div>
                    <div className="flex items-center gap-3 mt-1 text-xs text-gray-500">
                        {sow.client && <span>{sow.client.name}</span>}
                        {sow.retainer && <span>· {sow.retainer.name}</span>}
                        {sow.start_date && <span>· {sow.start_date}{sow.end_date ? ` → ${sow.end_date}` : ''}</span>}
                    </div>
                    {sow.description && <p className="text-sm text-gray-600 mt-1.5">{sow.description}</p>}
                </div>
                <div className="flex items-center gap-2 shrink-0">
                    <span className="text-xs text-gray-400">{sow.deliverables.length} deliverable{sow.deliverables.length !== 1 ? 's' : ''}</span>
                    <button
                        onClick={() => setExpanded(!expanded)}
                        className="p-1 rounded hover:bg-gray-100 text-gray-500"
                    >
                        {expanded ? <ChevronUp className="w-4 h-4" /> : <ChevronDown className="w-4 h-4" />}
                    </button>
                    <button
                        onClick={() => { if (confirm('Delete this SOW?')) router.delete(`/sow/${sow.id}`); }}
                        className="p-1 rounded hover:bg-rose-50 text-gray-400 hover:text-rose-500"
                    >
                        <Trash2 className="w-4 h-4" />
                    </button>
                </div>
            </div>

            {expanded && sow.deliverables.length > 0 && (
                <div className="border-t border-gray-100 px-5 py-3">
                    <div className="space-y-2">
                        {sow.deliverables.map(d => (
                            <div key={d.id} className="flex items-center gap-3 text-sm">
                                <span className={cn('px-2 py-0.5 rounded text-xs font-medium', SERVICE_COLORS[d.service_type] ?? SERVICE_COLORS.other)}>
                                    {SERVICE_LABELS[d.service_type] ?? d.service_type}
                                </span>
                                <span className="text-gray-700 flex-1">{d.title}</span>
                                <span className="text-xs text-gray-400 capitalize">{d.frequency.replace('_', ' ')} × {d.quantity_per_period}</span>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}

export default function SowIndex({ sows, clients, retainers }: Props) {
    const [showCreate, setShowCreate] = useState(false);
    const [deliverables, setDeliverables] = useState<DeliverableForm[]>([
        { title: '', service_type: 'seo', frequency: 'monthly', quantity_per_period: '1', notes: '' }
    ]);

    const form = useForm({
        client_id: '', retainer_id: '', title: '', description: '',
        start_date: '', end_date: '',
    });

    const filteredRetainers = form.data.client_id
        ? retainers.filter(r => r.client_id === form.data.client_id)
        : retainers;

    const addDeliverable = () => setDeliverables(prev => [...prev, { title: '', service_type: 'other', frequency: 'monthly', quantity_per_period: '1', notes: '' }]);
    const removeDeliverable = (idx: number) => setDeliverables(prev => prev.filter((_, i) => i !== idx));
    const updateDeliverable = (idx: number, field: keyof DeliverableForm, value: string) =>
        setDeliverables(prev => prev.map((d, i) => i === idx ? { ...d, [field]: value } : d));

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        router.post('/sow', { ...form.data, deliverables }, {
            onSuccess: () => { setShowCreate(false); form.reset(); setDeliverables([{ title: '', service_type: 'seo', frequency: 'monthly', quantity_per_period: '1', notes: '' }]); }
        });
    };

    return (
        <AppLayout>
            <Head title="SOW Tracker" />

            <div className="max-w-5xl mx-auto px-4 py-6 space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">SOW Tracker</h1>
                        <p className="text-sm text-gray-500 mt-0.5">Statement of Work & deliverables per client</p>
                    </div>
                    <button
                        onClick={() => setShowCreate(true)}
                        className="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors"
                    >
                        <Plus className="w-4 h-4" /> New SOW
                    </button>
                </div>

                {/* SOW List */}
                <div className="space-y-3">
                    {sows.length === 0 && (
                        <div className="bg-white rounded-xl border border-gray-200 px-5 py-10 text-center">
                            <FileText className="w-10 h-10 text-gray-300 mx-auto mb-3" />
                            <p className="text-gray-500 text-sm">No SOWs yet. Create your first Statement of Work.</p>
                        </div>
                    )}
                    {sows.map(sow => <SowCard key={sow.id} sow={sow} />)}
                </div>
            </div>

            {/* Create Modal */}
            {showCreate && (
                <div className="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
                    <div className="bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                        <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100 sticky top-0 bg-white">
                            <h2 className="text-base font-semibold text-gray-900">New SOW</h2>
                            <button onClick={() => setShowCreate(false)} className="text-gray-400 hover:text-gray-600">
                                <X className="w-5 h-5" />
                            </button>
                        </div>
                        <form onSubmit={submit} className="px-6 py-4 space-y-5">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-medium text-gray-700 mb-1">Client *</label>
                                    <select value={form.data.client_id} onChange={e => { form.setData('client_id', e.target.value); form.setData('retainer_id', ''); }}
                                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                        <option value="">Select…</option>
                                        {clients.map(c => <option key={c.id} value={c.id}>{c.company || c.name}</option>)}
                                    </select>
                                    {form.errors.client_id && <p className="text-xs text-rose-500 mt-1">{form.errors.client_id}</p>}
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-gray-700 mb-1">Linked Retainer</label>
                                    <select value={form.data.retainer_id} onChange={e => form.setData('retainer_id', e.target.value)}
                                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                        <option value="">None</option>
                                        {filteredRetainers.map(r => <option key={r.id} value={r.id}>{r.name}</option>)}
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-gray-700 mb-1">SOW Title *</label>
                                <input type="text" value={form.data.title} onChange={e => form.setData('title', e.target.value)}
                                    placeholder="e.g. Q1 2026 Digital Marketing SOW"
                                    className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                                {form.errors.title && <p className="text-xs text-rose-500 mt-1">{form.errors.title}</p>}
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-gray-700 mb-1">Description</label>
                                <textarea rows={2} value={form.data.description} onChange={e => form.setData('description', e.target.value)}
                                    className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 resize-none" />
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-medium text-gray-700 mb-1">Start Date *</label>
                                    <input type="date" value={form.data.start_date} onChange={e => form.setData('start_date', e.target.value)}
                                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-gray-700 mb-1">End Date</label>
                                    <input type="date" value={form.data.end_date} onChange={e => form.setData('end_date', e.target.value)}
                                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                                </div>
                            </div>

                            {/* Deliverables */}
                            <div>
                                <div className="flex items-center justify-between mb-2">
                                    <label className="text-xs font-semibold text-gray-700">Deliverables</label>
                                    <button type="button" onClick={addDeliverable}
                                        className="flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                                        <Plus className="w-3 h-3" /> Add
                                    </button>
                                </div>
                                <div className="space-y-2">
                                    {deliverables.map((d, idx) => (
                                        <div key={idx} className="grid grid-cols-12 gap-2 items-center bg-gray-50 rounded-lg p-2">
                                            <input type="text" value={d.title} onChange={e => updateDeliverable(idx, 'title', e.target.value)}
                                                placeholder="Deliverable title"
                                                className="col-span-4 border border-gray-300 rounded px-2 py-1.5 text-xs focus:ring-1 focus:ring-indigo-500" />
                                            <select value={d.service_type} onChange={e => updateDeliverable(idx, 'service_type', e.target.value)}
                                                className="col-span-2 border border-gray-300 rounded px-2 py-1.5 text-xs focus:ring-1 focus:ring-indigo-500">
                                                {Object.entries(SERVICE_LABELS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                                            </select>
                                            <select value={d.frequency} onChange={e => updateDeliverable(idx, 'frequency', e.target.value)}
                                                className="col-span-2 border border-gray-300 rounded px-2 py-1.5 text-xs focus:ring-1 focus:ring-indigo-500">
                                                <option value="one_time">One-time</option>
                                                <option value="weekly">Weekly</option>
                                                <option value="monthly">Monthly</option>
                                                <option value="quarterly">Quarterly</option>
                                            </select>
                                            <input type="number" min="1" value={d.quantity_per_period} onChange={e => updateDeliverable(idx, 'quantity_per_period', e.target.value)}
                                                className="col-span-2 border border-gray-300 rounded px-2 py-1.5 text-xs focus:ring-1 focus:ring-indigo-500" />
                                            <button type="button" onClick={() => removeDeliverable(idx)}
                                                disabled={deliverables.length === 1}
                                                className="col-span-1 flex items-center justify-center text-gray-400 hover:text-rose-500 disabled:opacity-30">
                                                <Trash2 className="w-3.5 h-3.5" />
                                            </button>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            <div className="flex justify-end gap-3 pt-2">
                                <button type="button" onClick={() => setShowCreate(false)} className="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancel</button>
                                <button type="submit"
                                    className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                                    Create SOW
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}

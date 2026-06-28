import React, { useState } from 'react';
import { Button } from '@/Components/ui/Button';
import { router, useForm } from '@inertiajs/react';

import { useConfirm } from '@/hooks/useConfirm';
import { Plus, X, FileText, Trash2, ChevronDown, ChevronUp, Upload, CheckCircle2, RotateCcw, Clock } from 'lucide-react';
import { cn } from '@/lib/utils';

interface DeliverableSubmission {
    id: string; status: 'submitted' | 'approved' | 'revision_requested';
    file_url: string | null; external_link: string | null;
    notes: string | null; reviewer_notes: string | null;
    revision_number: number; reviewed_at: string | null;
    submitter: { id: string; name: string } | null;
}

interface Deliverable {
    id: string; title: string; service_type: string; frequency: string;
    quantity_per_period: number; notes: string | null;
    latest_submission: DeliverableSubmission | null;
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
interface Props { sows: Sow[]; clients: Client[]; retainers: Retainer[]; canReview?: boolean; }

const SUBMISSION_CONFIG: Record<string, { label: string; badge: string; icon: React.ReactNode }> = {
    submitted:          { label: 'Submitted',         badge: 'bg-blue-100 text-blue-700',   icon: <Clock className="w-3 h-3" /> },
    approved:           { label: 'Approved',          badge: 'bg-emerald-100 text-emerald-700', icon: <CheckCircle2 className="w-3 h-3" /> },
    revision_requested: { label: 'Revision Needed',   badge: 'bg--100 text--800', icon: <RotateCcw className="w-3 h-3" /> },
};

function SubmitDeliverableModal({ deliverable, onClose }: { deliverable: Deliverable; onClose: () => void }) {
    const form = useForm({ file_url: '', external_link: '', notes: '' });
    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-[15px] font-bold text-gray-900">Submit Deliverable</h2>
                    <Button onClick={onClose}><X className="w-4 h-4 text-gray-400" /></Button>
                </div>
                <p className="text-sm text-gray-600">{deliverable.title}</p>
                <form onSubmit={e => {
                    e.preventDefault();
                    form.post(`/sow/deliverables/${deliverable.id}/submit`, { onSuccess: onClose });
                }} className="space-y-3">
                    <div>
                        <label className="text-xs text-gray-500 font-medium">File URL</label>
                        <input type="url" value={form.data.file_url} onChange={e => form.setData('file_url', e.target.value)}
                            placeholder="https://drive.google.com/..."
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label className="text-xs text-gray-500 font-medium">External Link</label>
                        <input type="url" value={form.data.external_link} onChange={e => form.setData('external_link', e.target.value)}
                            placeholder="Loom / Notion / Figma link…"
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Notes</label>
                        <textarea value={form.data.notes} onChange={e => form.setData('notes', e.target.value)} rows={3}
                            placeholder="Context, instructions for reviewer…"
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 resize-none" />
                    </div>
                    <div className="flex justify-end gap-2 pt-2">
                        <Button type="button" onClick={onClose} variant="ghost" >Cancel</Button>
                        <Button type="submit" disabled={form.processing}
                            className="disabled:opacity-50" >
                            {form.processing ? 'Submitting…' : 'Submit'}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}



const SERVICE_LABELS: Record<string, string> = {
    seo: 'SEO', ads: 'Ads', social: 'Social', content: 'Content',
    design: 'Design', dev: 'Dev', email: 'Email', other: 'Other',
};

const SERVICE_COLORS: Record<string, string> = {
    seo:     'bg-blue-100 text-blue-700',
    ads:     'bg-violet-100 text-violet-700',
    social:  'bg-pink-100 text-pink-700',
    content: 'bg-teal-100 text-teal-700',
    design:  'bg--100 text--800',
    dev:     'bg-indigo-100 text-indigo-700',
    email:   'bg-cyan-100 text-cyan-700',
    other:   'bg-gray-100 text-gray-700',
};

type DeliverableForm = { title: string; service_type: string; frequency: string; quantity_per_period: string; notes: string };

function SowCard({ sow, canReview }: { sow: Sow; canReview?: boolean }) {
    const [expanded, setExpanded] = useState(false);
    const confirm = useConfirm();
    const [submitting, setSubmitting] = useState<Deliverable | null>(null);
    const [revNotes, setRevNotes] = useState<Record<string, string>>({});

    return (
        <div className="bg-white rounded-xl border border-gray-200">
            <div className="px-5 py-4 flex items-start justify-between gap-3">
                <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 flex-wrap">
                        <h3 className="font-semibold text-gray-900">{sow.title}</h3>
                        <StatusBadge value={sow.status} />
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
                    <Button
                        onClick={() => setExpanded(!expanded)}
                        className="p-1 rounded hover:bg-gray-100 text-gray-700"
                    >
                        {expanded ? <ChevronUp className="w-4 h-4" /> : <ChevronDown className="w-4 h-4" />}
                    </Button>
                    <Button
                        onClick={async () => {
                            const ok = await confirm({
                                title: 'Delete this SOW?',
                                description: 'This cannot be undone.',
                                confirmText: 'Delete',
                                variant: 'destructive',
                            });
                            if (!ok) return;
                            router.delete(`/sow/${sow.id}`);
                        }}
                        className="p-1 rounded hover:bg-rose-50 text-gray-400 hover:text-rose-500"
                    >
                        <Trash2 className="w-4 h-4" />
                    </Button>
                </div>
            </div>

            {expanded && sow.deliverables.length > 0 && (
                <div className="border-t border-gray-100 px-5 py-3 space-y-3">
                    {sow.deliverables.map(d => {
                        const sub = d.latest_submission;
                        const subCfg = sub ? SUBMISSION_CONFIG[sub.status] : null;
                        return (
                            <div key={d.id} className="space-y-2">
                                <div className="flex items-center gap-3 text-sm">
                                    <span className={cn('px-2 py-0.5 rounded text-xs font-medium shrink-0', SERVICE_COLORS[d.service_type] ?? SERVICE_COLORS.other)}>
                                        {SERVICE_LABELS[d.service_type] ?? d.service_type}
                                    </span>
                                    <span className="text-gray-700 flex-1">{d.title}</span>
                                    <span className="text-xs text-gray-400 capitalize shrink-0">{d.frequency.replace('_', ' ')} × {d.quantity_per_period}</span>
                                    {subCfg && (
                                        <span className={cn('flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium shrink-0', subCfg.badge)}>
                                            {subCfg.icon} {subCfg.label}
                                        </span>
                                    )}
                                    <Button onClick={() => setSubmitting(d)}
                                        className="flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-800 font-medium shrink-0 px-2 py-1 rounded hover:bg-indigo-50">
                                        <Upload className="w-3 h-3" />
                                        {sub ? 'Re-submit' : 'Submit'}
                                    </Button>
                                </div>

                                {/* Reviewer actions — only for CEO/PM when submission is pending */}
                                {canReview && sub && sub.status === 'submitted' && (
                                    <div className="ml-4 p-3 bg-amber-50 border border-amber-100 rounded-lg space-y-2">
                                        <p className="text-xs font-medium text-amber-700">Submitted by {sub.submitter?.name} · Rev #{sub.revision_number}</p>
                                        {sub.notes && <p className="text-xs text-gray-600">{sub.notes}</p>}
                                        <input type="text" placeholder="Reviewer notes (optional)…"
                                            value={revNotes[sub.id] ?? ''}
                                            onChange={e => setRevNotes(prev => ({ ...prev, [sub.id]: e.target.value }))}
                                            className="w-full border border-amber-200 rounded px-2 py-1 text-xs focus:ring-1 focus:ring-amber-400" />
                                        <div className="flex gap-2">
                                            <Button
                                                onClick={() => router.post(`/deliverables/${sub.id}/approve`, { reviewer_notes: revNotes[sub.id] ?? '' })}
                                                className="flex items-center gap-1 px-3 py-1.5 bg-emerald-600 text-white text-xs font-medium rounded-lg hover:bg-emerald-700">
                                                <CheckCircle2 className="w-3 h-3" /> Approve
                                            </Button>
                                            <Button
                                                onClick={() => router.post(`/deliverables/${sub.id}/revision`, { reviewer_notes: revNotes[sub.id] ?? '' })}
                                                className="flex items-center gap-1 px-3 py-1.5 bg-amber-500 text-white text-xs font-medium rounded-lg hover:bg-amber-600">
                                                <RotateCcw className="w-3 h-3" /> Request Revision
                                            </Button>
                                        </div>
                                    </div>
                                )}

                                {/* Approved banner */}
                                {sub && sub.status === 'approved' && sub.reviewer_notes && (
                                    <div className="ml-4 p-2.5 bg-emerald-50 border border-emerald-100 rounded-lg">
                                        <p className="text-xs text-emerald-700">{sub.reviewer_notes}</p>
                                    </div>
                                )}

                                {/* Revision notes */}
                                {sub && sub.status === 'revision_requested' && sub.reviewer_notes && (
                                    <div className="ml-4 p-2.5 bg-amber-50 border border-amber-100 rounded-lg">
                                        <p className="text-xs font-medium text-amber-700 mb-0.5">Revision requested:</p>
                                        <p className="text-xs text-amber-800">{sub.reviewer_notes}</p>
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
            )}

            {submitting && <SubmitDeliverableModal deliverable={submitting} onClose={() => setSubmitting(null)} />}
        </div>
    );
}

export default function SowTab({ sows, clients, retainers, canReview }: Props) {
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
        <div>
            

            <div className="max-w-5xl mx-auto px-4 py-6 space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">SOW Tracker</h1>
                        <p className="text-sm text-gray-500 mt-0.5">Statement of Work & deliverables per client</p>
                    </div>
                    <Button
                        onClick={() => setShowCreate(true)}
                        className="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors"
                    >
                        <Plus className="w-4 h-4" /> New SOW
                    </Button>
                </div>

                {/* SOW List */}
                <div className="space-y-3">
                    {sows.length === 0 && (
                        <div className="bg-white rounded-xl border border-gray-200 px-5 py-10 text-center">
                            <FileText className="w-10 h-10 text-gray-300 mx-auto mb-3" />
                            <p className="text-gray-500 text-sm">No SOWs yet. Create your first Statement of Work.</p>
                        </div>
                    )}
                    {sows.map(sow => <SowCard key={sow.id} sow={sow} canReview={canReview} />)}
                </div>
            </div>

            {/* Create Modal */}
            {showCreate && (
                <div className="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
                    <div className="bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                        <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100 sticky top-0 bg-white">
                            <h2 className="text-base font-semibold text-gray-900">New SOW</h2>
                            <Button onClick={() => setShowCreate(false)} className="text-gray-400 hover:text-gray-600">
                                <X className="w-5 h-5" />
                            </Button>
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
                                    <Button type="button" onClick={addDeliverable}
                                        className="flex items-center gap-1" variant="ghost" size="sm" >
                                        <Plus className="w-3 h-3" /> Add
                                    </Button>
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
                                            <Button type="button" onClick={() => removeDeliverable(idx)}
                                                disabled={deliverables.length === 1}
                                                className="col-span-1 flex items-center justify-center text-gray-400 hover:text-rose-500 disabled:opacity-30">
                                                <Trash2 className="w-3.5 h-3.5" />
                                            </Button>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            <div className="flex justify-end gap-3 pt-2">
                                <Button type="button" onClick={() => setShowCreate(false)} className="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancel</Button>
                                <Button type="submit"
                                    >
                                    Create SOW
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}

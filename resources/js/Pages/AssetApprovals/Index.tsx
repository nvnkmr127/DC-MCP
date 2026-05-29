import React, { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { cn } from '@/lib/utils';
import { Plus, X, CheckCircle, RotateCcw, XCircle, ExternalLink, ChevronDown } from 'lucide-react';

interface Approval {
    id: string; title: string; description: string | null; type: string; asset_url: string | null;
    feedback: string | null; version: number; status: string; reviewed_at: string | null;
    created_at: string;
    client: { id: string; name: string } | null;
    submitter: { id: string; name: string } | null;
}
interface Client { id: string; name: string; }
interface Props { approvals: Approval[]; clients: Client[]; filters: Record<string, string>; }

const STATUS_STYLES: Record<string, string> = {
    pending: 'bg-blue-100 text-blue-700', approved: 'bg-emerald-100 text-emerald-700',
    revision_requested: 'bg-amber-100 text-amber-700', rejected: 'bg-rose-100 text-rose-600',
};
const TYPE_LABELS: Record<string, string> = {
    social_post: 'Social Post', ad_creative: 'Ad Creative', blog: 'Blog', video: 'Video', email: 'Email', other: 'Other',
};

function SubmitModal({ clients, onClose }: { clients: Client[]; onClose: () => void }) {
    const form = useForm({ title: '', type: 'other', client_id: '', asset_url: '', description: '' });
    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-3">
                <div className="flex items-center justify-between">
                    <h2 className="text-[15px] font-bold text-gray-900">Submit Asset for Approval</h2>
                    <button onClick={onClose}><X size={16} className="text-gray-400" /></button>
                </div>
                <form onSubmit={e => { e.preventDefault(); form.post('/asset-approvals', { onSuccess: onClose }); }} className="space-y-3">
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Title *</label>
                        <input type="text" value={form.data.title} onChange={e => form.setData('title', e.target.value)}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Type</label>
                            <select value={form.data.type} onChange={e => form.setData('type', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                {Object.entries(TYPE_LABELS).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Client *</label>
                            <select value={form.data.client_id} onChange={e => form.setData('client_id', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                <option value="">Select…</option>
                                {clients.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                            </select>
                        </div>
                    </div>
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Asset URL</label>
                        <input type="text" value={form.data.asset_url} onChange={e => form.setData('asset_url', e.target.value)}
                            placeholder="https://drive.google.com/…"
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Description</label>
                        <textarea value={form.data.description} onChange={e => form.setData('description', e.target.value)} rows={3}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 resize-none" />
                    </div>
                    <div className="flex justify-end gap-2 pt-1">
                        <button type="button" onClick={onClose} className="px-4 py-2 text-sm text-gray-600">Cancel</button>
                        <button type="submit" disabled={form.processing || !form.data.title || !form.data.client_id}
                            className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                            {form.processing ? 'Submitting…' : 'Submit'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export default function AssetApprovalsIndex({ approvals, clients, filters }: Props) {
    const [submitOpen, setSubmitOpen] = useState(false);
    const [expandedId, setExpandedId] = useState<string | null>(null);
    const [statusFilter, setStatusFilter] = useState(filters.status ?? '');
    const [feedbacks, setFeedbacks] = useState<Record<string, string>>({});

    const filtered = statusFilter ? approvals.filter(a => a.status === statusFilter) : approvals;

    return (
        <AppLayout title="Asset Approvals">
            <Head title="Asset Approvals" />
            <div className="max-w-4xl space-y-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-lg font-bold text-gray-900">Asset Approval Workflow</h1>
                    <button onClick={() => setSubmitOpen(true)}
                        className="flex items-center gap-2 px-3 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                        <Plus size={14} /> Submit Asset
                    </button>
                </div>

                <div className="flex items-center gap-2">
                    {['', 'pending', 'approved', 'revision_requested', 'rejected'].map(s => (
                        <button key={s} onClick={() => setStatusFilter(s)}
                            className={cn('px-3 py-1.5 text-xs rounded-lg font-medium transition-colors',
                                statusFilter === s ? 'bg-indigo-600 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50')}>
                            {s === '' ? 'All' : s.replace('_', ' ')}
                        </button>
                    ))}
                </div>

                <div className="space-y-2">
                    {filtered.length === 0 && (
                        <div className="bg-white rounded-xl border border-dashed border-gray-200 px-5 py-10 text-center text-sm text-gray-400">
                            No assets found.
                        </div>
                    )}
                    {filtered.map(a => (
                        <div key={a.id} className="bg-white rounded-xl border border-gray-200">
                            <div className="px-4 py-3.5 flex items-center gap-3 cursor-pointer" onClick={() => setExpandedId(expandedId === a.id ? null : a.id)}>
                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center gap-2">
                                        <p className="text-sm font-semibold text-gray-900">{a.title}</p>
                                        <span className="text-xs text-gray-400">v{a.version}</span>
                                        <span className={cn('px-2 py-0.5 rounded text-[10px] font-semibold', STATUS_STYLES[a.status] ?? STATUS_STYLES.pending)}>
                                            {a.status.replace('_', ' ')}
                                        </span>
                                    </div>
                                    <p className="text-xs text-gray-500 mt-0.5">
                                        {TYPE_LABELS[a.type] ?? a.type} · {a.client?.name ?? '—'} · {a.submitter?.name ?? '—'}
                                    </p>
                                </div>
                                <ChevronDown size={13} className={cn('text-gray-400 transition-transform shrink-0', expandedId === a.id && 'rotate-180')} />
                            </div>
                            {expandedId === a.id && (
                                <div className="border-t border-gray-100 px-4 pb-4 pt-3 space-y-3">
                                    {a.description && <p className="text-sm text-gray-600">{a.description}</p>}
                                    {a.asset_url && (
                                        <a href={a.asset_url} target="_blank" rel="noreferrer"
                                            className="flex items-center gap-1.5 text-sm text-indigo-600 font-medium">
                                            <ExternalLink size={13} /> View Asset
                                        </a>
                                    )}
                                    {a.feedback && (
                                        <div className="bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                                            <p className="text-xs font-semibold text-amber-700 mb-0.5">Feedback</p>
                                            <p className="text-sm text-amber-800">{a.feedback}</p>
                                        </div>
                                    )}
                                    {a.status === 'pending' && (
                                        <div className="space-y-2">
                                            <textarea value={feedbacks[a.id] ?? ''} onChange={e => setFeedbacks(f => ({ ...f, [a.id]: e.target.value }))}
                                                rows={2} placeholder="Feedback (required for revision/rejection)…"
                                                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 resize-none" />
                                            <div className="flex gap-2">
                                                <button onClick={() => router.patch(`/asset-approvals/${a.id}`, { status: 'approved' })}
                                                    className="flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 text-white text-xs font-medium rounded-lg hover:bg-emerald-700">
                                                    <CheckCircle size={12} /> Approve
                                                </button>
                                                <button onClick={() => router.patch(`/asset-approvals/${a.id}`, { status: 'revision_requested', feedback: feedbacks[a.id] ?? '' })}
                                                    className="flex items-center gap-1.5 px-3 py-1.5 bg-amber-500 text-white text-xs font-medium rounded-lg hover:bg-amber-600">
                                                    <RotateCcw size={12} /> Request Revision
                                                </button>
                                                <button onClick={() => router.patch(`/asset-approvals/${a.id}`, { status: 'rejected', feedback: feedbacks[a.id] ?? '' })}
                                                    className="flex items-center gap-1.5 px-3 py-1.5 bg-rose-600 text-white text-xs font-medium rounded-lg hover:bg-rose-700">
                                                    <XCircle size={12} /> Reject
                                                </button>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            </div>
            {submitOpen && <SubmitModal clients={clients} onClose={() => setSubmitOpen(false)} />}
        </AppLayout>
    );
}

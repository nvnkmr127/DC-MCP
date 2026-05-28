import React, { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import { LogOut, Plus, X, FolderOpen, CheckSquare, FileText, Send } from 'lucide-react';

interface PortalShare {
    id: string; shareable_type: string; shareable_id: string;
    permissions: string[]; expires_at: string | null;
}
interface PortalRequest {
    id: string; title: string; description: string | null;
    status: string; created_at: string;
}
interface Props {
    portalUser: { id: string; name: string; email: string };
    client: { id: string };
    shares: PortalShare[];
    requests: PortalRequest[];
}

const STATUS_STYLES: Record<string, string> = {
    open:        'bg-blue-100 text-blue-700',
    in_progress: 'bg-amber-100 text-amber-700',
    closed:      'bg-gray-100 text-gray-500',
    converted:   'bg-emerald-100 text-emerald-700',
};

const TYPE_ICONS: Record<string, React.ReactNode> = {
    project: <FolderOpen size={14} />,
    task:    <CheckSquare size={14} />,
    invoice: <FileText size={14} />,
};

function RequestModal({ onClose }: { onClose: () => void }) {
    const form = useForm({ title: '', description: '' });
    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-[15px] font-bold text-gray-900">Submit a Request</h2>
                    <button onClick={onClose}><X size={16} className="text-gray-400" /></button>
                </div>
                <form onSubmit={e => {
                    e.preventDefault();
                    form.post('/portal/requests', { onSuccess: onClose });
                }} className="space-y-3">
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Title *</label>
                        <input type="text" value={form.data.title} onChange={e => form.setData('title', e.target.value)}
                            placeholder="What do you need?"
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Details</label>
                        <textarea value={form.data.description} onChange={e => form.setData('description', e.target.value)} rows={4}
                            placeholder="Describe your request in detail…"
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 resize-none" />
                    </div>
                    <div className="flex justify-end gap-2 pt-2">
                        <button type="button" onClick={onClose} className="px-4 py-2 text-sm text-gray-600">Cancel</button>
                        <button type="submit" disabled={form.processing || !form.data.title}
                            className="flex items-center gap-1.5 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                            <Send size={13} /> {form.processing ? 'Submitting…' : 'Submit Request'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export default function PortalDashboard({ portalUser, shares, requests }: Props) {
    const [reqOpen, setReqOpen] = useState(false);

    return (
        <div className="min-h-screen bg-gray-50">
            <Head title="Client Portal" />

            {/* Header */}
            <div className="bg-white border-b border-gray-200">
                <div className="max-w-5xl mx-auto px-4 py-4 flex items-center justify-between">
                    <div>
                        <h1 className="text-base font-bold text-gray-900">Client Portal</h1>
                        <p className="text-xs text-gray-500">Welcome back, {portalUser.name}</p>
                    </div>
                    <button onClick={() => router.post('/portal/logout')}
                        className="flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700">
                        <LogOut size={14} /> Sign out
                    </button>
                </div>
            </div>

            <div className="max-w-5xl mx-auto px-4 py-6 space-y-6">
                {/* Shared items */}
                <div>
                    <h2 className="text-sm font-semibold text-gray-700 mb-3">Shared with You</h2>
                    {shares.length === 0 ? (
                        <div className="bg-white rounded-xl border border-dashed border-gray-200 px-5 py-10 text-center">
                            <p className="text-sm text-gray-400">Nothing has been shared yet. Your account manager will share project updates here.</p>
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            {shares.map(s => (
                                <div key={s.id} className="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-3">
                                    <div className="w-9 h-9 rounded-lg bg-indigo-50 flex items-center justify-center text-indigo-500 shrink-0">
                                        {TYPE_ICONS[s.shareable_type] ?? <FileText size={14} />}
                                    </div>
                                    <div className="min-w-0">
                                        <p className="text-sm font-medium text-gray-900 capitalize">{s.shareable_type}</p>
                                        <p className="text-xs text-gray-400">{s.permissions.join(', ')} access</p>
                                        {s.expires_at && <p className="text-[11px] text-gray-400">Expires {new Date(s.expires_at).toLocaleDateString('en-IN')}</p>}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* Requests */}
                <div>
                    <div className="flex items-center justify-between mb-3">
                        <h2 className="text-sm font-semibold text-gray-700">My Requests</h2>
                        <button onClick={() => setReqOpen(true)}
                            className="flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-lg hover:bg-indigo-700">
                            <Plus size={12} /> New Request
                        </button>
                    </div>
                    {requests.length === 0 ? (
                        <div className="bg-white rounded-xl border border-dashed border-gray-200 px-5 py-10 text-center">
                            <p className="text-sm text-gray-400">No requests yet.</p>
                            <button onClick={() => setReqOpen(true)} className="mt-2 text-sm text-indigo-600 font-medium">
                                Submit your first request →
                            </button>
                        </div>
                    ) : (
                        <div className="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
                            {requests.map(r => (
                                <div key={r.id} className="px-5 py-3.5 flex items-start justify-between gap-3">
                                    <div className="flex-1 min-w-0">
                                        <p className="text-sm font-medium text-gray-900">{r.title}</p>
                                        {r.description && <p className="text-xs text-gray-500 mt-0.5 truncate">{r.description}</p>}
                                        <p className="text-[11px] text-gray-400 mt-1">
                                            {new Date(r.created_at).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })}
                                        </p>
                                    </div>
                                    <span className={cn('px-2 py-0.5 rounded-full text-[10px] font-semibold capitalize shrink-0', STATUS_STYLES[r.status] ?? STATUS_STYLES.open)}>
                                        {r.status.replace('_', ' ')}
                                    </span>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>

            {reqOpen && <RequestModal onClose={() => setReqOpen(false)} />}
        </div>
    );
}

import React, { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { cn, timeAgo } from '@/lib/utils';
import { Plus, X, UserCheck, UserX, RotateCcw, CheckCircle2, ArrowRight, ExternalLink } from 'lucide-react';

interface PortalUser {
    id: string; name: string; email: string; is_active: boolean;
    last_login_at: string | null; invite_sent_at: string | null;
    client: { id: string; name: string } | null;
}
interface PortalRequest {
    id: string; title: string; description: string | null; status: string;
    created_at: string;
    client: { id: string; name: string } | null;
    portal_user: { name: string; email: string } | null;
}
interface Client { id: string; name: string; company: string; }
interface Props {
    portalUsers: PortalUser[];
    requests: PortalRequest[];
    clients: Client[];
}

const STATUS_STYLES: Record<string, string> = {
    open:        'bg-blue-100 text-blue-700',
    in_progress: 'bg-amber-100 text-amber-700',
    closed:      'bg-gray-100 text-gray-500',
    converted:   'bg-emerald-100 text-emerald-700',
};

function InviteModal({ clients, onClose }: { clients: Client[]; onClose: () => void }) {
    const form = useForm({ client_id: '', name: '', email: '' });
    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-[15px] font-bold text-gray-900">Invite Client Contact</h2>
                    <button onClick={onClose}><X size={16} className="text-gray-400" /></button>
                </div>
                <form onSubmit={e => {
                    e.preventDefault();
                    form.post(`/settings/client-portal/${form.data.client_id}/invite`, { onSuccess: onClose });
                }} className="space-y-3">
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Client *</label>
                        <select value={form.data.client_id} onChange={e => form.setData('client_id', e.target.value)}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                            <option value="">Select client…</option>
                            {clients.map(c => <option key={c.id} value={c.id}>{c.company || c.name}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Contact Name *</label>
                        <input type="text" value={form.data.name} onChange={e => form.setData('name', e.target.value)}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Email *</label>
                        <input type="email" value={form.data.email} onChange={e => form.setData('email', e.target.value)}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div className="flex justify-end gap-2 pt-2">
                        <button type="button" onClick={onClose} className="px-4 py-2 text-sm text-gray-600">Cancel</button>
                        <button type="submit" disabled={form.processing || !form.data.client_id || !form.data.name || !form.data.email}
                            className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                            {form.processing ? 'Sending…' : 'Send Invite'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export default function ClientPortalSettings({ portalUsers, requests, clients }: Props) {
    const [inviteOpen, setInviteOpen] = useState(false);

    return (
        <AppLayout title="Client Portal">
            <Head title="Client Portal" />

            <div className="max-w-5xl space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-lg font-bold text-gray-900">Client Portal</h1>
                        <p className="text-sm text-gray-500 mt-0.5">Give clients a secure view into their projects and reports</p>
                    </div>
                    <button onClick={() => setInviteOpen(true)}
                        className="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                        <Plus size={14} /> Invite Contact
                    </button>
                </div>

                {/* Open requests */}
                {requests.length > 0 && (
                    <div>
                        <h2 className="text-sm font-semibold text-gray-700 mb-2">Open Client Requests ({requests.length})</h2>
                        <div className="space-y-2">
                            {requests.map(r => (
                                <div key={r.id} className="bg-white rounded-xl border border-gray-200 px-4 py-3 flex items-start justify-between gap-3">
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center gap-2 flex-wrap">
                                            <p className="text-sm font-medium text-gray-900">{r.title}</p>
                                            <span className={cn('px-2 py-0.5 rounded-full text-[10px] font-semibold capitalize', STATUS_STYLES[r.status] ?? STATUS_STYLES.open)}>
                                                {r.status.replace('_', ' ')}
                                            </span>
                                        </div>
                                        {r.description && <p className="text-xs text-gray-500 mt-0.5 truncate">{r.description}</p>}
                                        <p className="text-[11px] text-gray-400 mt-1">
                                            From {r.portal_user?.name ?? 'Unknown'} ({r.client?.name}) · {timeAgo(r.created_at)}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-1.5 shrink-0">
                                        <button onClick={() => router.post(`/settings/client-portal/requests/${r.id}/to-task`, {})}
                                            className="flex items-center gap-1 px-2.5 py-1.5 text-xs border border-indigo-200 text-indigo-600 rounded-lg hover:bg-indigo-50">
                                            <ArrowRight size={11} /> To Task
                                        </button>
                                        <button onClick={() => router.post(`/settings/client-portal/requests/${r.id}/close`, {})}
                                            className="flex items-center gap-1 px-2.5 py-1.5 text-xs border border-gray-200 text-gray-500 rounded-lg hover:bg-gray-50">
                                            <CheckCircle2 size={11} /> Close
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Portal users */}
                <div>
                    <h2 className="text-sm font-semibold text-gray-700 mb-2">Portal Users ({portalUsers.length})</h2>
                    {portalUsers.length === 0 ? (
                        <div className="bg-white rounded-xl border border-dashed border-gray-200 px-5 py-10 text-center">
                            <p className="text-sm text-gray-400">No client contacts invited yet.</p>
                            <button onClick={() => setInviteOpen(true)} className="mt-2 text-sm text-indigo-600 font-medium">
                                Invite the first contact →
                            </button>
                        </div>
                    ) : (
                        <div className="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
                            {portalUsers.map(u => (
                                <div key={u.id} className="px-5 py-3.5 flex items-center justify-between gap-3">
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center gap-2">
                                            <p className="text-sm font-medium text-gray-900">{u.name}</p>
                                            {!u.is_active && <span className="px-1.5 py-0.5 rounded text-[10px] bg-gray-100 text-gray-500 font-medium">Disabled</span>}
                                        </div>
                                        <p className="text-xs text-gray-400">{u.email} · {u.client?.name}</p>
                                        <p className="text-[11px] text-gray-400 mt-0.5">
                                            {u.last_login_at ? `Last login ${timeAgo(u.last_login_at)}` : u.invite_sent_at ? `Invited ${timeAgo(u.invite_sent_at)}` : 'Not yet invited'}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-1.5 shrink-0">
                                        <button onClick={() => router.post(`/settings/client-portal/users/${u.id}/resend`, {})}
                                            className="flex items-center gap-1 px-2.5 py-1.5 text-xs border border-gray-200 text-gray-600 rounded-lg hover:bg-gray-50">
                                            <RotateCcw size={11} /> Resend
                                        </button>
                                        <button onClick={() => router.post(`/settings/client-portal/users/${u.id}/toggle`, {})}
                                            className={cn('flex items-center gap-1 px-2.5 py-1.5 text-xs border rounded-lg transition-colors',
                                                u.is_active
                                                    ? 'border-rose-200 text-rose-600 hover:bg-rose-50'
                                                    : 'border-emerald-200 text-emerald-600 hover:bg-emerald-50'
                                            )}>
                                            {u.is_active ? <><UserX size={11} /> Disable</> : <><UserCheck size={11} /> Enable</>}
                                        </button>
                                        {u.client && (
                                            <Link href={`/settings/client-portal/${u.client.id}`}
                                                className="p-1.5 text-gray-400 hover:text-indigo-600 rounded hover:bg-indigo-50 transition-colors">
                                                <ExternalLink size={13} />
                                            </Link>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>

            {inviteOpen && <InviteModal clients={clients} onClose={() => setInviteOpen(false)} />}
        </AppLayout>
    );
}

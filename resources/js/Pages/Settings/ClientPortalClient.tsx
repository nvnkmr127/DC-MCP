import React, { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useConfirm } from '@/hooks/useConfirm';
import { cn } from '@/lib/utils';
import { ArrowLeft, Plus, X, UserCheck, UserX, RotateCcw, Trash2, Share2 } from 'lucide-react';

interface PortalUser {
    id: string; name: string; email: string; is_active: boolean;
    last_login_at: string | null; invite_sent_at: string | null;
}
interface PortalShare {
    id: string; shareable_type: string; shareable_id: string;
    permissions: string[]; expires_at: string | null;
}
interface Project { id: string; name: string; }
interface Props {
    client: { id: string; name: string };
    users: PortalUser[];
    shares: PortalShare[];
    projects: Project[];
}

function InviteModal({ clientId, onClose }: { clientId: string; onClose: () => void }) {
    const form = useForm({ name: '', email: '' });
    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 space-y-3">
                <div className="flex items-center justify-between">
                    <h2 className="text-[15px] font-bold text-gray-900">Invite Contact</h2>
                    <button onClick={onClose}><X size={16} className="text-gray-400" /></button>
                </div>
                <form onSubmit={e => { e.preventDefault(); form.post(`/settings/client-portal/${clientId}/invite`, { onSuccess: onClose }); }} className="space-y-3">
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Name *</label>
                        <input type="text" value={form.data.name} onChange={e => form.setData('name', e.target.value)}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Email *</label>
                        <input type="email" value={form.data.email} onChange={e => form.setData('email', e.target.value)}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div className="flex justify-end gap-2 pt-1">
                        <button type="button" onClick={onClose} className="px-4 py-2 text-sm text-gray-600">Cancel</button>
                        <button type="submit" disabled={form.processing || !form.data.name || !form.data.email}
                            className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                            {form.processing ? 'Sending…' : 'Send Invite'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

function ShareModal({ clientId, projects, onClose }: { clientId: string; projects: Project[]; onClose: () => void }) {
    const form = useForm({ client_id: clientId, shareable_type: 'project', shareable_id: '', permissions: ['view'], expires_at: '' });
    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 space-y-3">
                <div className="flex items-center justify-between">
                    <h2 className="text-[15px] font-bold text-gray-900">Share with Client</h2>
                    <button onClick={onClose}><X size={16} className="text-gray-400" /></button>
                </div>
                <form onSubmit={e => { e.preventDefault(); form.post('/settings/client-portal/share', { onSuccess: onClose }); }} className="space-y-3">
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Type</label>
                        <select value={form.data.shareable_type} onChange={e => form.setData('shareable_type', e.target.value)}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                            <option value="project">Project</option>
                            <option value="task">Task</option>
                            <option value="invoice">Invoice</option>
                        </select>
                    </div>
                    {form.data.shareable_type === 'project' && (
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Project</label>
                            <select value={form.data.shareable_id} onChange={e => form.setData('shareable_id', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                <option value="">Select…</option>
                                {projects.map(p => <option key={p.id} value={p.id}>{p.name}</option>)}
                            </select>
                        </div>
                    )}
                    {form.data.shareable_type !== 'project' && (
                        <div>
                            <label className="text-xs text-gray-500 font-medium">ID</label>
                            <input type="text" value={form.data.shareable_id} onChange={e => form.setData('shareable_id', e.target.value)}
                                placeholder="UUID of the item"
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                        </div>
                    )}
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Expires (optional)</label>
                        <input type="date" value={form.data.expires_at} onChange={e => form.setData('expires_at', e.target.value)}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div className="flex justify-end gap-2 pt-1">
                        <button type="button" onClick={onClose} className="px-4 py-2 text-sm text-gray-600">Cancel</button>
                        <button type="submit" disabled={form.processing || !form.data.shareable_id}
                            className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                            {form.processing ? 'Sharing…' : 'Share'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export default function ClientPortalClientPage({ client, users, shares, projects }: Props) {
    const [inviteOpen, setInviteOpen] = useState(false);
    const [shareOpen, setShareOpen] = useState(false);
    const confirm = useConfirm();

    return (
        <AppLayout title={`Portal — ${client.name}`}>
            <Head title={`Portal — ${client.name}`} />

            <div className="mb-5">
                <Link href="/settings/client-portal" className="flex items-center gap-1.5 text-sm text-gray-500 hover:text-indigo-600 w-fit">
                    <ArrowLeft size={14} /> Back to Portal Management
                </Link>
            </div>

            <div className="max-w-3xl space-y-5">
                <div className="flex items-center justify-between">
                    <h1 className="text-lg font-bold text-gray-900">{client.name} — Portal</h1>
                    <div className="flex gap-2">
                        <button onClick={() => setShareOpen(true)}
                            className="flex items-center gap-1.5 px-3 py-2 text-sm border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50">
                            <Share2 size={13} /> Share Item
                        </button>
                        <button onClick={() => setInviteOpen(true)}
                            className="flex items-center gap-2 px-3 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                            <Plus size={14} /> Invite Contact
                        </button>
                    </div>
                </div>

                {/* Users */}
                <div>
                    <h2 className="text-sm font-semibold text-gray-700 mb-2">Portal Users ({users.length})</h2>
                    {users.length === 0 ? (
                        <div className="bg-white rounded-xl border border-dashed border-gray-200 px-5 py-8 text-center text-sm text-gray-400">
                            No contacts invited yet.
                        </div>
                    ) : (
                        <div className="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
                            {users.map(u => (
                                <div key={u.id} className="px-4 py-3 flex items-center justify-between gap-3">
                                    <div>
                                        <p className="text-sm font-medium text-gray-900">{u.name}</p>
                                        <p className="text-xs text-gray-400">{u.email}</p>
                                    </div>
                                    <div className="flex items-center gap-1.5">
                                        <button onClick={() => router.post(`/settings/client-portal/users/${u.id}/resend`, {})}
                                            className="flex items-center gap-1 px-2 py-1 text-xs border border-gray-200 text-gray-500 rounded-lg hover:bg-gray-50">
                                            <RotateCcw size={10} /> Resend
                                        </button>
                                        <button onClick={() => router.post(`/settings/client-portal/users/${u.id}/toggle`, {})}
                                            className={cn('flex items-center gap-1 px-2 py-1 text-xs border rounded-lg transition-colors',
                                                u.is_active
                                                    ? 'border-rose-200 text-rose-600 hover:bg-rose-50'
                                                    : 'border-emerald-200 text-emerald-600 hover:bg-emerald-50'
                                            )}>
                                            {u.is_active ? <><UserX size={10} /> Disable</> : <><UserCheck size={10} /> Enable</>}
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* Shares */}
                <div>
                    <h2 className="text-sm font-semibold text-gray-700 mb-2">Shared Items ({shares.length})</h2>
                    {shares.length === 0 ? (
                        <div className="bg-white rounded-xl border border-dashed border-gray-200 px-5 py-8 text-center text-sm text-gray-400">
                            Nothing shared yet.
                        </div>
                    ) : (
                        <div className="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
                            {shares.map(s => (
                                <div key={s.id} className="px-4 py-3 flex items-center justify-between gap-3">
                                    <div>
                                        <p className="text-sm font-medium text-gray-900 capitalize">{s.shareable_type}</p>
                                        <p className="text-xs text-gray-400">{s.permissions.join(', ')} access{s.expires_at ? ` · expires ${new Date(s.expires_at).toLocaleDateString('en-IN')}` : ''}</p>
                                    </div>
                                    <button onClick={async () => {
                                        const ok = await confirm({
                                            title: 'Revoke this share?',
                                            description: 'The client will lose access immediately.',
                                            confirmText: 'Revoke',
                                            variant: 'destructive',
                                        });
                                        if (!ok) return;
                                        router.delete(`/settings/client-portal/shares/${s.id}`);
                                    }}
                                        className="p-1.5 text-gray-400 hover:text-rose-500 rounded hover:bg-rose-50 transition-colors">
                                        <Trash2 size={13} />
                                    </button>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>

            {inviteOpen && <InviteModal clientId={client.id} onClose={() => setInviteOpen(false)} />}
            {shareOpen && <ShareModal clientId={client.id} projects={projects} onClose={() => setShareOpen(false)} />}
        </AppLayout>
    );
}

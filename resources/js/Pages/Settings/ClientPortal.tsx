import React, { useState } from 'react';
import { Breadcrumbs } from '@/Components/Shared/Breadcrumbs';
import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import {
    Globe, Users, Plus, Mail, CheckCircle2, Clock, X,
    AlertTriangle, ArrowRight, RefreshCw, ToggleLeft, ToggleRight,
    MessageSquare, Link2, Trash2,
} from 'lucide-react';
import { cn } from '@/lib/utils';

interface PortalUser {
    id: string;
    name: string;
    email: string;
    is_active: boolean;
    last_login_at: string | null;
}

interface ClientRow {
    id: string;
    name: string;
    company: string | null;
    portal_users: PortalUser[];
    pending_requests: number;
}

interface PortalRequestRow {
    id: string;
    title: string;
    description: string | null;
    type: string;
    status: string;
    priority: string;
    created_at: string;
    task_id: string | null;
    client: { id: string; name: string } | null;
    portal_user: { name: string; email: string } | null;
}

interface Props {
    clients: ClientRow[];
    pendingRequests: PortalRequestRow[];
}

function InviteModal({ client, onClose }: { client: ClientRow; onClose: () => void }) {
    const { data, setData, post, processing } = useForm({ name: '', email: '' });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/settings/client-portal/${client.id}/invite`, { onSuccess: onClose });
    };

    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-2xl shadow-2xl w-full max-w-md">
                <div className="flex items-center justify-between px-6 py-4 border-b">
                    <h2 className="font-semibold text-gray-900">Invite Client Contact</h2>
                    <button onClick={onClose} className="p-1 rounded hover:bg-gray-100"><X className="w-4 h-4" /></button>
                </div>
                <form onSubmit={submit} className="p-6 space-y-4">
                    <p className="text-sm text-gray-500">Sending portal access to a contact at <strong>{client.company || client.name}</strong>.</p>
                    <div>
                        <label className="text-xs font-medium text-gray-500 mb-1 block">Contact Name</label>
                        <input className="w-full text-sm border rounded-lg p-2" placeholder="John Smith" value={data.name} onChange={e => setData('name', e.target.value)} required />
                    </div>
                    <div>
                        <label className="text-xs font-medium text-gray-500 mb-1 block">Email Address</label>
                        <input type="email" className="w-full text-sm border rounded-lg p-2" placeholder="john@client.com" value={data.email} onChange={e => setData('email', e.target.value)} required />
                    </div>
                    <p className="text-xs text-gray-400">A magic link will be emailed. Valid for 24 hours. Client can see their projects and submit requests.</p>
                    <div className="flex gap-2 pt-1">
                        <button type="submit" disabled={processing} className="flex-1 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                            Send Portal Invite
                        </button>
                        <button type="button" onClick={onClose} className="px-4 py-2 text-sm text-gray-500 border rounded-lg hover:bg-gray-50">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    );
}

function ClientPortalCard({ client }: { client: ClientRow }) {
    const [inviteOpen, setInviteOpen] = useState(false);

    const toggle = (userId: string) => {
        router.post(`/settings/client-portal/users/${userId}/toggle`, {}, { preserveScroll: true });
    };

    const resend = (userId: string) => {
        router.post(`/settings/client-portal/users/${userId}/resend`, {}, { preserveScroll: true });
    };

    return (
        <>
            {inviteOpen && <InviteModal client={client} onClose={() => setInviteOpen(false)} />}
            <div className="bg-white rounded-xl border border-gray-200 p-4">
                <div className="flex items-center justify-between mb-3">
                    <div>
                        <p className="font-semibold text-gray-900 text-sm">{client.company || client.name}</p>
                        {client.pending_requests > 0 && (
                            <span className="text-xs font-medium text-amber-600 flex items-center gap-1 mt-0.5">
                                <AlertTriangle className="w-3 h-3" />
                                {client.pending_requests} pending request{client.pending_requests > 1 ? 's' : ''}
                            </span>
                        )}
                    </div>
                    <button
                        onClick={() => setInviteOpen(true)}
                        className="flex items-center gap-1.5 text-xs px-2.5 py-1.5 bg-indigo-50 text-indigo-700 rounded-lg hover:bg-indigo-100"
                    >
                        <Plus className="w-3 h-3" /> Invite
                    </button>
                </div>

                {client.portal_users.length === 0 ? (
                    <p className="text-xs text-gray-400 italic">No portal users yet.</p>
                ) : (
                    <div className="space-y-2">
                        {client.portal_users.map(u => (
                            <div key={u.id} className="flex items-center justify-between py-2 border-t border-gray-100">
                                <div>
                                    <p className="text-xs font-medium text-gray-800">{u.name}</p>
                                    <p className="text-xs text-gray-400">{u.email}</p>
                                    {u.last_login_at && (
                                        <p className="text-xs text-gray-300">
                                            Last login: {new Date(u.last_login_at).toLocaleDateString()}
                                        </p>
                                    )}
                                </div>
                                <div className="flex items-center gap-1">
                                    <button onClick={() => resend(u.id)} title="Resend magic link" className="p-1.5 text-gray-400 hover:text-indigo-600 rounded">
                                        <Mail className="w-3.5 h-3.5" />
                                    </button>
                                    <button onClick={() => toggle(u.id)} title={u.is_active ? 'Disable access' : 'Enable access'} className={cn('p-1.5 rounded', u.is_active ? 'text-emerald-500 hover:text-red-500' : 'text-gray-400 hover:text-emerald-500')}>
                                        {u.is_active ? <ToggleRight className="w-4 h-4" /> : <ToggleLeft className="w-4 h-4" />}
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

export default function ClientPortalSettings({ clients, pendingRequests }: Props) {
    const convertToTask = (requestId: string) => {
        router.post(`/settings/client-portal/requests/${requestId}/to-task`, {}, { preserveScroll: true });
    };

    const closeRequest = (requestId: string) => {
        router.post(`/settings/client-portal/requests/${requestId}/close`, {}, { preserveScroll: true });
    };

    const TYPE_STYLES: Record<string, string> = {
        new_request: 'bg-blue-100 text-blue-700',
        feedback:    'bg-purple-100 text-purple-700',
        bug:         'bg-red-100 text-red-700',
        question:    'bg-gray-100 text-gray-700',
    };

    return (
        <AppLayout title="Client Portal">
            <Head title="Client Portal Management" />
            <div className="mb-6">
                <Breadcrumbs items={[
                    { label: 'Settings', href: '/settings' },
                    { label: 'Client Portal Management' }
                ]} />
            </div>

            <div className="max-w-6xl mx-auto px-4 py-6 space-y-6">

                {/* Header */}
                <div className="flex items-center gap-2">
                    <Globe className="w-5 h-5 text-indigo-500" />
                    <div>
                        <h1 className="text-xl font-bold text-gray-900">Client Portal</h1>
                        <p className="text-sm text-gray-500">Manage client access and incoming requests. Nothing is visible to clients without your explicit sharing.</p>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-5 gap-6">

                    {/* Client Cards */}
                    <div className="lg:col-span-3 space-y-3">
                        <h2 className="text-sm font-semibold text-gray-700 flex items-center gap-2">
                            <Users className="w-4 h-4 text-gray-400" /> Client Portal Access
                        </h2>
                        {clients.length === 0 ? (
                            <div className="bg-white rounded-xl border border-gray-200 p-8 text-center">
                                <p className="text-sm text-gray-400">No active clients yet. Add clients first.</p>
                            </div>
                        ) : (
                            clients.map(c => <ClientPortalCard key={c.id} client={c} />)
                        )}
                    </div>

                    {/* Pending Requests */}
                    <div className="lg:col-span-2 space-y-3">
                        <h2 className="text-sm font-semibold text-gray-700 flex items-center gap-2">
                            <MessageSquare className="w-4 h-4 text-gray-400" /> Pending Requests
                            {pendingRequests.length > 0 && (
                                <span className="bg--100 text--800 text-xs font-bold px-2 py-0.5 rounded-full">
                                    {pendingRequests.length}
                                </span>
                            )}
                        </h2>

                        <div className="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
                            {pendingRequests.length === 0 ? (
                                <div className="p-6 text-center">
                                    <CheckCircle2 className="w-8 h-8 text-emerald-400 mx-auto mb-2" />
                                    <p className="text-xs text-gray-400">No pending client requests.</p>
                                </div>
                            ) : (
                                pendingRequests.map(r => (
                                    <div key={r.id} className="p-3 space-y-2">
                                        <div className="flex items-start justify-between gap-2">
                                            <div className="flex-1 min-w-0">
                                                <p className="text-xs font-semibold text-gray-900 truncate">{r.title}</p>
                                                <div className="flex items-center gap-1.5 mt-0.5">
                                                    <span className={cn('text-xs px-1.5 py-0.5 rounded font-medium', TYPE_STYLES[r.type])}>
                                                        {r.type.replace('_', ' ')}
                                                    </span>
                                                    {r.client && <span className="text-xs text-gray-400">{r.client.name}</span>}
                                                </div>
                                                {r.portal_user && (
                                                    <p className="text-xs text-gray-400 mt-0.5">by {r.portal_user.name}</p>
                                                )}
                                            </div>
                                        </div>
                                        {r.description && (
                                            <p className="text-xs text-gray-500 line-clamp-2">{r.description}</p>
                                        )}
                                        <div className="flex gap-1.5">
                                            <button
                                                onClick={() => convertToTask(r.id)}
                                                className="flex items-center gap-1 text-xs px-2 py-1 bg-indigo-50 text-indigo-700 rounded-lg hover:bg-indigo-100"
                                            >
                                                <ArrowRight className="w-3 h-3" /> Create Task
                                            </button>
                                            <button
                                                onClick={() => closeRequest(r.id)}
                                                className="flex items-center gap-1 text-xs px-2 py-1 text-gray-400 hover:text-red-500"
                                            >
                                                <X className="w-3 h-3" /> Close
                                            </button>
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>

                </div>
            </div>
        </AppLayout>
    );
}

import React, { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { cn, timeAgo } from '@/lib/utils';
import { ArrowLeft, RefreshCw, Trash2, CheckCircle, XCircle, Save, Zap } from 'lucide-react';

interface McpConnection {
    id: string;
    provider: string;
    label: string;
    status: string;
    is_active: boolean;
    settings: Record<string, any>;
    last_synced_at: string | null;
}

interface Props { connection: McpConnection; }

const STATUS_STYLES: Record<string, string> = {
    active:   'bg-green-100 text-green-700',
    error:    'bg-red-100 text-red-600',
    inactive: 'bg-gray-100 text-gray-500',
    syncing:  'bg-blue-100 text-blue-600',
};

const PROVIDER_ICONS: Record<string, string> = {
    gmail:           '📧',
    google_calendar: '📅',
    notion:          '📓',
    zoho_cliq:       '💬',
    meta_ads:        '📢',
    make:            '⚙️',
};

export default function MCPDetail({ connection }: Props) {
    const [editing, setEditing] = useState(false);
    const form = useForm({
        label:        connection.label ?? '',
        is_active:    connection.is_active,
        access_token: '',
        api_key:      '',
        settings:     connection.settings ?? {},
    });

    function save(e: React.FormEvent) {
        e.preventDefault();
        form.patch(`/settings/mcp/${connection.id}`, { onSuccess: () => setEditing(false) });
    }

    return (
        <AppLayout title={connection.label}>
            <Head title={connection.label} />

            <div className="mb-5">
                <Link href="/settings/mcp" className="flex items-center gap-1.5 text-sm text-gray-500 hover:text-indigo-600 w-fit">
                    <ArrowLeft size={14} /> Back to Integrations
                </Link>
            </div>

            <div className="max-w-2xl space-y-4">
                {/* Header card */}
                <div className="bg-white rounded-xl border border-gray-200 p-5">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <span className="text-2xl">{PROVIDER_ICONS[connection.provider] ?? '🔌'}</span>
                            <div>
                                <h1 className="text-lg font-bold text-gray-900">{connection.label}</h1>
                                <p className="text-xs text-gray-400 capitalize">{connection.provider.replace(/_/g, ' ')}</p>
                            </div>
                        </div>
                        <span className={cn('px-2.5 py-1 rounded-full text-xs font-semibold capitalize', STATUS_STYLES[connection.status] ?? STATUS_STYLES.inactive)}>
                            {connection.status}
                        </span>
                    </div>

                    {connection.last_synced_at && (
                        <p className="text-xs text-gray-400 mt-3">Last synced {timeAgo(connection.last_synced_at)}</p>
                    )}

                    <div className="flex items-center gap-2 mt-4">
                        <button onClick={() => router.post(`/settings/mcp/${connection.id}/sync`, {}, { preserveScroll: true })}
                            className="flex items-center gap-1.5 px-3 py-1.5 text-xs border border-gray-200 rounded-lg hover:bg-gray-50 text-gray-600">
                            <RefreshCw size={12} /> Sync Now
                        </button>
                        <button onClick={() => router.post(`/settings/mcp/${connection.id}/test`, {}, { preserveScroll: true })}
                            className="flex items-center gap-1.5 px-3 py-1.5 text-xs border border-indigo-200 rounded-lg hover:bg-indigo-50 text-indigo-600">
                            <Zap size={12} /> Test Connection
                        </button>
                        <button onClick={() => setEditing(!editing)}
                            className={cn('flex items-center gap-1.5 px-3 py-1.5 text-xs border rounded-lg transition-colors',
                                editing ? 'bg-indigo-600 text-white border-indigo-600' : 'border-gray-200 text-gray-600 hover:bg-gray-50'
                            )}>
                            {editing ? 'Cancel Edit' : 'Edit'}
                        </button>
                        <button onClick={() => { if (confirm('Remove this connection?')) router.delete(`/settings/mcp/${connection.id}`); }}
                            className="ml-auto p-1.5 text-gray-400 hover:text-red-500 rounded hover:bg-red-50 transition-colors">
                            <Trash2 size={14} />
                        </button>
                    </div>
                </div>

                {/* Edit form */}
                {editing && (
                    <div className="bg-white rounded-xl border border-gray-200 p-5">
                        <h2 className="text-sm font-semibold text-gray-900 mb-4">Edit Connection</h2>
                        <form onSubmit={save} className="space-y-4">
                            <div>
                                <label className="text-xs font-medium text-gray-600">Display Label</label>
                                <input type="text" value={form.data.label} onChange={e => form.setData('label', e.target.value)}
                                    className="w-full mt-1 px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            </div>
                            <div>
                                <label className="text-xs font-medium text-gray-600">New Access Token (leave blank to keep existing)</label>
                                <input type="password" value={form.data.access_token} onChange={e => form.setData('access_token', e.target.value)}
                                    placeholder="••••••••"
                                    className="w-full mt-1 px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            </div>
                            <div>
                                <label className="text-xs font-medium text-gray-600">API Key (leave blank to keep existing)</label>
                                <input type="password" value={form.data.api_key} onChange={e => form.setData('api_key', e.target.value)}
                                    placeholder="••••••••"
                                    className="w-full mt-1 px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            </div>
                            <div className="flex items-center gap-3">
                                <label className="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" checked={form.data.is_active} onChange={e => form.setData('is_active', e.target.checked)}
                                        className="w-4 h-4 accent-indigo-600 rounded" />
                                    <span className="text-sm text-gray-700">Active</span>
                                </label>
                            </div>
                            <div className="flex gap-2 pt-1">
                                <button type="submit" disabled={form.processing}
                                    className="flex items-center gap-1.5 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                                    <Save size={13} /> {form.processing ? 'Saving…' : 'Save Changes'}
                                </button>
                                <button type="button" onClick={() => setEditing(false)} className="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                )}

                {/* Settings display */}
                {Object.keys(connection.settings).length > 0 && (
                    <div className="bg-white rounded-xl border border-gray-200 p-5">
                        <h2 className="text-sm font-semibold text-gray-900 mb-3">Current Settings</h2>
                        <dl className="space-y-2">
                            {Object.entries(connection.settings).map(([k, v]) => (
                                <div key={k} className="flex items-start gap-3 text-sm">
                                    <dt className="text-xs text-gray-400 capitalize w-32 shrink-0 pt-0.5">{k.replace(/_/g, ' ')}</dt>
                                    <dd className="text-gray-700 font-mono text-xs break-all">{String(v)}</dd>
                                </div>
                            ))}
                        </dl>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

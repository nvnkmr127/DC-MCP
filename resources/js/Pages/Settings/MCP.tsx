import React, { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useConfirm } from '@/hooks/useConfirm';
import { cn, timeAgo } from '@/lib/utils';
import { Plus, Trash2, RefreshCw, CheckCircle, XCircle, Settings, Globe, Key } from 'lucide-react';

interface McpConnection {
    id: string;
    provider: string;
    label: string;
    status: string;
    is_active: boolean;
    is_builtin: boolean;
    last_synced_at: string | null;
    settings: Record<string, any>;
}

interface Props {
    connections: McpConnection[];
    builtin_providers: string[];
}

const PROVIDER_ICONS: Record<string, string> = {
    gmail:           '📧',
    google_calendar: '📅',
    notion:          '📓',
    zoho_cliq:       '💬',
    meta_ads:        '📢',
    make:            '⚙️',
};

const STATUS_STYLES: Record<string, string> = {
    active:   'bg-green-100 text-green-700',
    error:    'bg-red-100 text-red-600',
    inactive: 'bg-gray-100 text-gray-500',
    syncing:  'bg-blue-100 text-blue-600',
};

export default function MCPSettings({ connections, builtin_providers }: Props) {
    const [showForm, setShowForm] = useState(false);
    const [isCustom, setIsCustom] = useState(false);
    const confirm = useConfirm();

    const form = useForm({
        provider:          '',
        label:             '',
        access_token:      '',
        api_key:           '',
        username:          '',
        password:          '',
        settings: {
            base_url:      '',
            auth_type:     'bearer' as string,
            sync_endpoint: '',
            test_endpoint: '',
            webhook_secret:'',
        },
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.post('/settings/mcp', {
            onSuccess: () => {
                form.reset();
                setShowForm(false);
            },
        });
    }

    async function remove(id: string) {
        const ok = await confirm({
            title: 'Remove this connection?',
            description: 'This will disconnect the integration.',
            confirmText: 'Remove',
            variant: 'destructive',
        });
        if (!ok) return;
        router.delete(`/settings/mcp/${id}`, { preserveScroll: true });
    }

    function sync(id: string) {
        router.post(`/settings/mcp/${id}/sync`, {}, { preserveScroll: true });
    }

    function toggleActive(conn: McpConnection) {
        router.patch(`/settings/mcp/${conn.id}`, { is_active: !conn.is_active }, { preserveScroll: true });
    }

    return (
        <AppLayout title="Integrations & MCP">
            <Head title="Integrations & MCP" />

            <div className="flex items-center justify-between mb-6">
                <div>
                    <h1 className="text-lg font-bold text-gray-900">Integrations</h1>
                    <p className="text-sm text-gray-500 mt-0.5">Connect external services via MCP</p>
                </div>
                <button
                    onClick={() => setShowForm(!showForm)}
                    className="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700"
                >
                    <Plus size={15} /> Add Connection
                </button>
            </div>

            {/* Add Connection Form */}
            {showForm && (
                <div className="bg-white rounded-xl border border-gray-200 p-6 mb-6">
                    <h3 className="text-sm font-semibold text-gray-900 mb-4">New Connection</h3>

                    {/* Toggle builtin vs custom */}
                    <div className="flex gap-2 mb-4">
                        <button
                            type="button"
                            onClick={() => setIsCustom(false)}
                            className={cn('px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors', !isCustom ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-200')}
                        >
                            Built-in Provider
                        </button>
                        <button
                            type="button"
                            onClick={() => setIsCustom(true)}
                            className={cn('flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors', isCustom ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-200')}
                        >
                            <Globe size={12} /> Custom HTTP Provider
                        </button>
                    </div>

                    <form onSubmit={submit} className="space-y-4">
                        {!isCustom ? (
                            <div>
                                <label className="block text-xs font-medium text-gray-600 mb-1">Provider</label>
                                <select
                                    value={form.data.provider}
                                    onChange={e => form.setData('provider', e.target.value)}
                                    className="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    required
                                >
                                    <option value="">Select provider…</option>
                                    {builtin_providers.map(p => (
                                        <option key={p} value={p}>{PROVIDER_ICONS[p] ?? '🔌'} {p.replace('_', ' ')}</option>
                                    ))}
                                </select>
                            </div>
                        ) : (
                            <>
                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="block text-xs font-medium text-gray-600 mb-1">Provider ID</label>
                                        <input
                                            type="text"
                                            value={form.data.provider}
                                            onChange={e => form.setData('provider', e.target.value)}
                                            placeholder="my_crm"
                                            className="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                            required
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-xs font-medium text-gray-600 mb-1">Display Label</label>
                                        <input
                                            type="text"
                                            value={form.data.label}
                                            onChange={e => form.setData('label', e.target.value)}
                                            placeholder="My CRM"
                                            className="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                        />
                                    </div>
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-gray-600 mb-1">Base URL *</label>
                                    <input
                                        type="url"
                                        value={form.data.settings.base_url}
                                        onChange={e => form.setData('settings', { ...form.data.settings, base_url: e.target.value })}
                                        placeholder="https://api.mycrm.com/v1"
                                        className="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                        required={isCustom}
                                    />
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="block text-xs font-medium text-gray-600 mb-1">Auth Type</label>
                                        <select
                                            value={form.data.settings.auth_type}
                                            onChange={e => form.setData('settings', { ...form.data.settings, auth_type: e.target.value })}
                                            className="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                        >
                                            <option value="bearer">Bearer Token</option>
                                            <option value="api_key">API Key Header</option>
                                            <option value="basic">Basic Auth</option>
                                            <option value="none">No Auth</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label className="block text-xs font-medium text-gray-600 mb-1">Sync Endpoint</label>
                                        <input
                                            type="text"
                                            value={form.data.settings.sync_endpoint}
                                            onChange={e => form.setData('settings', { ...form.data.settings, sync_endpoint: e.target.value })}
                                            placeholder="/sync (optional)"
                                            className="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                        />
                                    </div>
                                </div>
                            </>
                        )}

                        <div>
                            <label className="block text-xs font-medium text-gray-600 mb-1">
                                {!isCustom || form.data.settings.auth_type === 'bearer' ? 'Access Token / OAuth Token' : form.data.settings.auth_type === 'api_key' ? 'API Key' : 'Username'}
                            </label>
                            {form.data.settings.auth_type === 'basic' ? (
                                <div className="grid grid-cols-2 gap-3">
                                    <input
                                        type="text"
                                        value={form.data.username}
                                        onChange={e => form.setData('username', e.target.value)}
                                        placeholder="Username"
                                        className="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    />
                                    <input
                                        type="password"
                                        value={form.data.password}
                                        onChange={e => form.setData('password', e.target.value)}
                                        placeholder="Password"
                                        className="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    />
                                </div>
                            ) : (
                                <input
                                    type="password"
                                    value={form.data.access_token || form.data.api_key}
                                    onChange={e => {
                                        if (form.data.settings.auth_type === 'api_key') {
                                            form.setData('api_key', e.target.value);
                                        } else {
                                            form.setData('access_token', e.target.value);
                                        }
                                    }}
                                    placeholder="••••••••"
                                    className="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                />
                            )}
                        </div>

                        <div className="flex gap-3">
                            <button
                                type="submit"
                                disabled={form.processing}
                                className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50"
                            >
                                Create Connection
                            </button>
                            <button
                                type="button"
                                onClick={() => setShowForm(false)}
                                className="px-4 py-2 text-sm text-gray-600 rounded-lg hover:bg-gray-100"
                            >
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            )}

            {/* Connections list */}
            {connections.length === 0 ? (
                <div className="bg-white rounded-xl border border-gray-200 p-16 text-center">
                    <Settings size={32} className="text-gray-300 mx-auto mb-3" />
                    <p className="text-gray-500 text-sm mb-3">No integrations connected yet.</p>
                    <button onClick={() => setShowForm(true)} className="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700">
                        Add First Connection
                    </button>
                </div>
            ) : (
                <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    {connections.map(conn => (
                        <div key={conn.id} className="bg-white rounded-xl border border-gray-200 p-5">
                            <div className="flex items-start justify-between mb-3">
                                <div className="flex items-center gap-2.5">
                                    <span className="text-xl">{PROVIDER_ICONS[conn.provider] ?? '🔌'}</span>
                                    <div>
                                        <p className="font-semibold text-gray-900 text-sm">{conn.label}</p>
                                        <p className="text-xs text-gray-400 capitalize">{conn.provider.replace('_', ' ')}</p>
                                    </div>
                                </div>
                                <div className="flex items-center gap-1">
                                    <span className={cn('px-2 py-0.5 rounded-full text-xs font-medium capitalize', STATUS_STYLES[conn.status] ?? STATUS_STYLES.inactive)}>
                                        {conn.status}
                                    </span>
                                </div>
                            </div>

                            {!conn.is_builtin && conn.settings.base_url && (
                                <p className="text-xs text-gray-400 mb-3 truncate">{conn.settings.base_url}</p>
                            )}

                            {conn.last_synced_at && (
                                <p className="text-xs text-gray-400 mb-3">Last synced {timeAgo(conn.last_synced_at)}</p>
                            )}

                            <div className="flex items-center gap-2 mt-3">
                                <button
                                    onClick={() => sync(conn.id)}
                                    className="flex items-center gap-1 px-3 py-1.5 text-xs border border-gray-200 rounded-lg hover:bg-gray-50 text-gray-600"
                                >
                                    <RefreshCw size={12} /> Sync
                                </button>
                                <button
                                    onClick={() => toggleActive(conn)}
                                    className={cn(
                                        'flex items-center gap-1 px-3 py-1.5 text-xs border rounded-lg transition-colors',
                                        conn.is_active
                                            ? 'border-green-200 text-green-700 bg-green-50 hover:bg-green-100'
                                            : 'border-gray-200 text-gray-500 hover:bg-gray-50',
                                    )}
                                >
                                    {conn.is_active ? <><CheckCircle size={12} /> Active</> : <><XCircle size={12} /> Inactive</>}
                                </button>
                                <button
                                    onClick={() => remove(conn.id)}
                                    className="ml-auto p-1.5 text-gray-400 hover:text-red-500 rounded hover:bg-red-50 transition-colors"
                                >
                                    <Trash2 size={13} />
                                </button>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </AppLayout>
    );
}

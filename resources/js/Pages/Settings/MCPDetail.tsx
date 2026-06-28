import React, { useState } from 'react';
import { Button } from '@/Components/ui/Button';
import { Breadcrumbs } from '@/Components/Shared/Breadcrumbs';
import { Head, Link, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useConfirm } from '@/hooks/useConfirm';
import { cn, timeAgo } from '@/lib/utils';
import { ArrowLeft, RefreshCw, Trash2, CheckCircle, XCircle, Save, Zap } from 'lucide-react';
import axios from 'axios';

interface McpConnection {
    id: string;
    provider: string;
    label: string;
    status: string;
    is_active: boolean;
    settings: Record<string, any>;
    last_synced_at: string | null;
}

interface OutboundAction {
    id: string;
    name: string;
    description: string;
    entity_type: string;
}

interface Props { 
    connection: McpConnection; 
    outboundActions?: OutboundAction[];
}

const STATUS_STYLES: Record<string, string> = {
    active:               'bg-green-100 text-green-700',
    error:                'bg-red-100 text-red-700',
    inactive:             'bg-gray-100 text-gray-700',
    syncing:              'bg-blue-100 text-blue-700',
    pending:              'bg-yellow-100 text-yellow-700',
    disconnected:         'bg-gray-200 text-gray-600',
    pending_verification: 'bg-purple-100 text-purple-700',
    token_expired:        'bg-orange-100 text-orange-700',
    rate_limited:         'bg-orange-100 text-orange-700',
    partially_active:     'bg-teal-100 text-teal-700',
    suspended:            'bg-red-200 text-red-800',
    quota_exceeded:       'bg-red-100 text-red-700',
    pending_reauth:       'bg-yellow-200 text-yellow-800',
    degraded:             'bg-yellow-100 text-yellow-700',
};

const PROVIDER_ICONS: Record<string, string> = {
    gmail:           '📧',
    google_calendar: '📅',
    notion:          '📓',
    zoho_cliq:       '💬',
    meta_ads:        '📢',
    make:            '⚙️',
};

const MAPPING_TEMPLATES: Record<string, { label: string, mappings: Record<string, string> }[]> = {
    notion: [
        {
            label: "Standard Project Tasks",
            mappings: { title: "Name", status: "Status", priority: "Priority", due_date: "Due Date", assignee: "Assignee" }
        },
        {
            label: "Content Calendar",
            mappings: { title: "Article Title", status: "Publish Status", due_date: "Publish Date", assignee: "Author" }
        }
    ],
    gmail: [
        {
            label: "Standard Task",
            mappings: { title: "{subject}", description: "From: {sender}\n\n{snippet}" }
        },
        {
            label: "Detailed Review",
            mappings: { title: "[Review] {subject}", description: "Date: {date}\nFrom: {sender}\n\n{snippet}" }
        }
    ]
};

export default function MCPDetail({ connection, outboundActions = [] }: Props) {
    const [editing, setEditing] = useState(false);
    const [isSyncing, setIsSyncing] = useState(false);
    const [isPreviewing, setIsPreviewing] = useState(false);
    const [previewResult, setPreviewResult] = useState<{ raw: any, mapped: any, warnings: string[] } | null>(null);
    const [previewError, setPreviewError] = useState('');
    const [outboundPreview, setOutboundPreview] = useState<{ isOpen: boolean, action: OutboundAction | null, payloadStr: string, result: any, error: string, isLoading: boolean }>({
        isOpen: false, action: null, payloadStr: '{\n  "entity_id": 1,\n  "user_id": 1\n}', result: null, error: '', isLoading: false
    });
    const confirm = useConfirm();
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

    async function handlePreview() {
        setIsPreviewing(true);
        setPreviewError('');
        setPreviewResult(null);
        try {
            const res = await fetch(`/api/v1/mcp/connections/${connection.id}/mapping-preview`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ field_mappings: form.data.settings?.field_mappings || {} })
            });
            const data = await res.json();
            if (res.ok && data.success !== false) {
                // Determine structure based on wrapper API response format
                setPreviewResult(data.data || data);
            } else {
                setPreviewError(data.message || data.error || 'Failed to preview');
            }
        } catch (e: any) {
            setPreviewError(e.response?.data?.message || e.message);
        } finally {
            setIsPreviewing(false);
        }
    }

    async function handleOutboundPreview() {
        if (!outboundPreview.action) return;
        
        let payload = {};
        try {
            payload = JSON.parse(outboundPreview.payloadStr);
        } catch (e) {
            setOutboundPreview(prev => ({ ...prev, error: 'Invalid JSON payload format.', result: null }));
            return;
        }

        setOutboundPreview(prev => ({ ...prev, isLoading: true, error: '', result: null }));
        try {
            const res = await axios.post(`/api/v1/mcp/connections/${connection.id}/outbound-preview`, {
                action_id: outboundPreview.action.id,
                payload
            });
            setOutboundPreview(prev => ({ ...prev, result: res.data.data }));
        } catch (e: any) {
            setOutboundPreview(prev => ({ ...prev, error: e.response?.data?.message || e.message }));
        } finally {
            setOutboundPreview(prev => ({ ...prev, isLoading: false }));
        }
    }

    return (
        <AppLayout title={connection.label}>
            <Head title={connection.label} />
            <div className="mb-6">
                <Breadcrumbs items={[
                    { label: 'Settings', href: '/settings' },
                    { label: connection.label }
                ]} />
            </div>

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
                        <Button onClick={() => router.post(`/settings/mcp/${connection.id}/sync`, {}, { 
                                preserveScroll: true,
                                onStart: () => setIsSyncing(true),
                                onFinish: () => setIsSyncing(false)
                            })}
                            disabled={isSyncing}
                            className="flex items-center gap-1.5 px-3 py-1.5 text-xs border border-gray-200 rounded-lg hover:bg-gray-50 text-gray-700 disabled:opacity-50">
                            <RefreshCw size={12} className={cn(isSyncing && "animate-spin")} /> {isSyncing ? 'Syncing...' : 'Sync Now'}
                        </Button>
                        <Button onClick={() => router.post(`/settings/mcp/${connection.id}/test`, {}, { preserveScroll: true })}
                            className="flex items-center gap-1.5 px-3 py-1.5 text-xs border border-indigo-200 rounded-lg hover:bg-indigo-50 text-indigo-600">
                            <Zap size={12} /> Test Connection
                        </Button>
                        <Button onClick={() => setEditing(!editing)}
                            className={cn('flex items-center gap-1.5 px-3 py-1.5 text-xs border rounded-lg transition-colors',
                                editing ? 'bg-indigo-600 text-white border-indigo-600' : 'border-gray-200 text-gray-600 hover:bg-gray-50'
                            )}>
                            {editing ? 'Cancel Edit' : 'Edit'}
                        </Button>
                        <Button onClick={async () => {
                            const ok = await confirm({
                                title: 'Remove this connection?',
                                description: 'This will disconnect the integration.',
                                confirmText: 'Remove',
                                variant: 'destructive',
                            });
                            if (!ok) return;
                            router.delete(`/settings/mcp/${connection.id}`);
                        }}
                            className="ml-auto p-1.5 text-gray-400 hover:text-red-500 rounded hover:bg-red-50 transition-colors">
                            <Trash2 size={14} />
                        </Button>
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
                            <div className="pt-4 border-t border-gray-200">
                                <h3 className="text-sm font-semibold text-gray-900 mb-2">Field Mappings & Computed Fields</h3>
                                <p className="text-xs text-gray-500 mb-3">Map internal fields to external ones. Support computed fields by wrapping external field names in brackets (e.g. <code>{'{First Name} {Last Name}'}</code>).</p>
                                
                                <div className="space-y-2">
                                    {Object.entries(form.data.settings?.field_mappings || {}).map(([internalKey, externalKey], idx) => (
                                        <div key={idx} className="flex gap-2 items-center">
                                            <input type="text" value={internalKey}
                                                onChange={e => {
                                                    const newMappings = { ...form.data.settings?.field_mappings };
                                                    const val = newMappings[internalKey];
                                                    delete newMappings[internalKey];
                                                    newMappings[e.target.value] = val;
                                                    form.setData('settings', { ...form.data.settings, field_mappings: newMappings });
                                                }}
                                                placeholder="Internal (e.g. title)"
                                                className="w-1/3 px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono" />
                                            <span className="text-gray-400">→</span>
                                            <input type="text" value={externalKey as string}
                                                onChange={e => {
                                                    const newMappings = { ...form.data.settings?.field_mappings };
                                                    newMappings[internalKey] = e.target.value;
                                                    form.setData('settings', { ...form.data.settings, field_mappings: newMappings });
                                                }}
                                                placeholder="External (e.g. Name, Title)"
                                                className="flex-1 px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono" />
                                            <Button type="button" onClick={() => {
                                                const newMappings = { ...form.data.settings?.field_mappings };
                                                delete newMappings[internalKey];
                                                form.setData('settings', { ...form.data.settings, field_mappings: newMappings });
                                            }} className="p-1.5 text-gray-400 hover:text-red-500 rounded hover:bg-red-50">
                                                <XCircle size={16} />
                                            </Button>
                                        </div>
                                    ))}
                                    
                                    <div className="flex items-center gap-3 pt-2">
                                        <Button type="button" onClick={() => {
                                            const newMappings = { ...form.data.settings?.field_mappings };
                                            newMappings['new_field_' + Object.keys(newMappings).length] = '';
                                            form.setData('settings', { ...form.data.settings, field_mappings: newMappings });
                                        }} className="text-xs font-medium text-indigo-600 hover:text-indigo-800">
                                            + Add Mapping
                                        </Button>
                                        {MAPPING_TEMPLATES[connection.provider] && (
                                            <select
                                                className="text-xs border border-gray-200 rounded-lg px-2 py-1 focus:outline-none focus:ring-2 focus:ring-indigo-500 text-gray-600"
                                                onChange={(e) => {
                                                    if (!e.target.value) return;
                                                    const template = MAPPING_TEMPLATES[connection.provider][parseInt(e.target.value)];
                                                    if (template) {
                                                        form.setData('settings', { ...form.data.settings, field_mappings: { ...template.mappings } });
                                                    }
                                                    e.target.value = ""; // Reset
                                                }}
                                            >
                                                <option value="">Load template...</option>
                                                {MAPPING_TEMPLATES[connection.provider].map((tpl, i) => (
                                                    <option key={i} value={i}>{tpl.label}</option>
                                                ))}
                                            </select>
                                        )}
                                        <div className="ml-auto">
                                            <Button type="button" onClick={handlePreview} disabled={isPreviewing}
                                                className="flex items-center gap-1.5 disabled:opacity-50" size="sm" >
                                                <Zap size={13} /> {isPreviewing ? 'Running Preview...' : 'Test Mapping Preview'}
                                            </Button>
                                        </div>
                                    </div>
                                    
                                    {previewError && <div className="mt-3 text-xs text-red-600 bg-red-50 p-3 rounded-lg border border-red-100">{previewError}</div>}
                                    
                                    {previewResult && (
                                        <div className="mt-4 border border-indigo-200 bg-indigo-50/30 rounded-xl overflow-hidden shadow-sm">
                                            <div className="bg-indigo-50 border-b border-indigo-100 px-4 py-2 flex justify-between items-center">
                                                <h4 className="text-xs font-semibold text-indigo-900">Live Mapping Preview (1 Record)</h4>
                                                <Button type="button" onClick={() => setPreviewResult(null)} className="text-indigo-400 hover:text-indigo-600 transition-colors">
                                                    <XCircle size={14} />
                                                </Button>
                                            </div>
                                            <div className="p-4 grid grid-cols-2 gap-4">
                                                <div>
                                                    <h5 className="text-[10px] uppercase font-bold text-gray-500 mb-2 tracking-wider">Raw External Payload</h5>
                                                    <pre className="text-[10px] bg-white border border-gray-200 rounded-lg p-3 overflow-auto max-h-64 text-gray-700 shadow-inner">
                                                        {JSON.stringify(previewResult.raw, null, 2)}
                                                    </pre>
                                                </div>
                                                <div>
                                                    <h5 className="text-[10px] uppercase font-bold text-indigo-500 mb-2 tracking-wider">Computed Internal Mapping</h5>
                                                    <pre className="text-[10px] bg-white border border-indigo-200 rounded-lg p-3 overflow-auto max-h-64 text-indigo-900 shadow-inner">
                                                        {JSON.stringify(previewResult.mapped, null, 2)}
                                                    </pre>
                                                </div>
                                            </div>
                                            {previewResult.warnings && previewResult.warnings.length > 0 && (
                                                <div className="px-4 pb-4">
                                                    <div className="bg-orange-50 border border-orange-100 rounded-lg p-3">
                                                        <h5 className="text-[10px] uppercase font-bold text-orange-600 mb-1 tracking-wider">Coercion / Drift Warnings</h5>
                                                        <ul className="list-disc pl-4 text-xs text-orange-700 space-y-1">
                                                            {previewResult.warnings.map((w, i) => <li key={i}>{w}</li>)}
                                                        </ul>
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    )}
                                </div>
                            </div>

                            <div className="pt-4 border-t border-gray-200">
                                <h3 className="text-sm font-semibold text-gray-900 mb-2">Outbound Operations</h3>
                                <p className="text-xs text-gray-500 mb-3">Configure which outbound actions are permitted for this integration.</p>
                                
                                {outboundActions && outboundActions.length > 0 ? (
                                    <div className="space-y-3">
                                        {outboundActions.map(action => (
                                            <div key={action.id} className="flex flex-col sm:flex-row sm:items-center gap-3 p-3 border border-gray-100 rounded-lg hover:bg-gray-50">
                                                <label className="flex items-start gap-3 flex-1 cursor-pointer">
                                                    <input 
                                                        type="checkbox" 
                                                        className="mt-1 w-4 h-4 accent-indigo-600 rounded" 
                                                        checked={form.data.settings?.enabled_outbound_actions?.[action.id] !== false}
                                                        onChange={e => {
                                                            const newActions = { ...(form.data.settings?.enabled_outbound_actions || {}) };
                                                            newActions[action.id] = e.target.checked;
                                                            form.setData('settings', { ...form.data.settings, enabled_outbound_actions: newActions });
                                                        }}
                                                    />
                                                    <div>
                                                        <div className="text-sm font-medium text-gray-900">{action.name}</div>
                                                        <div className="text-xs text-gray-500 mt-0.5">{action.description}</div>
                                                        <div className="text-[10px] mt-1 bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded w-fit capitalize">{action.entity_type}</div>
                                                    </div>
                                                </label>
                                                <Button 
                                                    type="button" 
                                                    onClick={() => setOutboundPreview(prev => ({ ...prev, isOpen: true, action, result: null, error: '' }))}
                                                    className="shrink-0 text-xs text-indigo-600 font-medium px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 rounded-md transition-colors"
                                                >
                                                    Test / Preview
                                                </Button>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <div className="text-sm text-gray-500 bg-gray-50 p-3 rounded-lg border border-gray-100">
                                        No outbound operations are available for this provider.
                                    </div>
                                )}
                            </div>

                            <div className="flex gap-2 pt-1 border-t border-gray-200 pt-4">
                                <Button type="submit" disabled={form.processing}
                                    className="flex items-center gap-1.5 disabled:opacity-50" >
                                    <Save size={13} /> {form.processing ? 'Saving…' : 'Save Changes'}
                                </Button>
                                <Button type="button" onClick={() => setEditing(false)} className="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg">
                                    Cancel
                                </Button>
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

            {/* Outbound Preview Modal */}
            {outboundPreview.isOpen && outboundPreview.action && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-sm">
                    <div className="bg-white w-full max-w-2xl rounded-2xl shadow-xl flex flex-col max-h-[90vh]">
                        <div className="p-5 border-b border-gray-100 flex justify-between items-center bg-gray-50/50 rounded-t-2xl">
                            <div>
                                <h3 className="text-lg font-bold text-gray-900">Preview: {outboundPreview.action.name}</h3>
                                <p className="text-xs text-gray-500 mt-0.5">Test payload rendering without sending data.</p>
                            </div>
                            <Button onClick={() => setOutboundPreview(prev => ({ ...prev, isOpen: false }))} className="text-gray-400 hover:text-gray-600 p-2">
                                <XCircle size={20} />
                            </Button>
                        </div>
                        
                        <div className="p-5 overflow-y-auto flex-1 space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-gray-700 mb-2">Test Payload (JSON)</label>
                                <textarea
                                    className="w-full h-32 p-3 text-sm font-mono border-gray-200 rounded-lg shadow-inner bg-gray-50 focus:ring-indigo-500 focus:border-indigo-500"
                                    value={outboundPreview.payloadStr}
                                    onChange={e => setOutboundPreview(prev => ({ ...prev, payloadStr: e.target.value }))}
                                    placeholder='{"entity_id": 1}'
                                />
                            </div>

                            <Button
                                onClick={handleOutboundPreview}
                                disabled={outboundPreview.isLoading}
                                className="w-full disabled:opacity-50" 
                            >
                                {outboundPreview.isLoading ? 'Generating...' : 'Run Preview'}
                            </Button>

                            {outboundPreview.error && (
                                <div className="p-3 bg-red-50 text-red-700 border border-red-100 rounded-lg text-sm">
                                    {outboundPreview.error}
                                </div>
                            )}

                            {outboundPreview.result && (
                                <div>
                                    <label className="block text-xs font-semibold text-indigo-900 mb-2 mt-2 uppercase tracking-wide">Generated Provider Payload</label>
                                    <pre className="text-xs bg-gray-900 text-green-400 border border-gray-800 rounded-lg p-4 overflow-auto max-h-80 shadow-inner">
                                        {JSON.stringify(outboundPreview.result, null, 2)}
                                    </pre>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}

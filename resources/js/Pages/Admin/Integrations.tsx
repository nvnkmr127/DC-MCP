import React, { useState, useEffect } from 'react';
import { Head, router, useForm, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useConfirm } from '@/hooks/useConfirm';
import { cn, timeAgo } from '@/lib/utils';
import { Input, Label } from '@/Components/ui/Input';
import { MonitorPlay, Activity } from 'lucide-react';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/Components/ui/Tooltip';
import Modal from '@/Components/ui/Modal';
import { 
    Plus, Trash2, RefreshCw, CheckCircle, XCircle, Settings, Globe, Key, 
    AlertTriangle, HelpCircle, ExternalLink, PlayCircle,
    Plug, CheckCircle2, ServerCrash, Building, ArrowRightLeft, Search
} from 'lucide-react';

interface McpConnection {
    id: string;
    provider: string;
    label: string;
    status: string;
    is_active: boolean;
    is_builtin: boolean;
    last_synced_at: string | null;
    updated_at?: string;
    sync_progress?: number | null;
    sync_error?: string | null;
    troubleshooting_guide?: string[];
    last_sync_summary?: string | null;
    settings: Record<string, any>;
}

interface Organization {
    id: number;
    name: string;
}

interface User {
    id: number;
    name: string;
    email: string;
}

interface GlobalConnection {
    id: number;
    provider: string;
    name: string;
    status: string;
    is_active: boolean;
    organization: Organization | null;
    user: User | null;
    created_at: string;
    last_synced_at: string | null;
    sync_error: string | null;
    settings?: any;
}

interface Props {
    connections: McpConnection[];
    builtin_providers: string[];
    global_connections?: {
        data: GlobalConnection[];
        current_page: number;
        last_page: number;
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    metrics?: {
        total: number;
        active: number;
        error: number;
        degraded: number;
    };
    filters?: {
        search?: string;
    };
    organizations?: Organization[];
    diagnostics?: {
        queues: Record<string, number>;
        activeSyncs: number;
        recentErrors: any[];
    };
    providers_list?: any[];
}

const PROVIDER_ICONS: Record<string, string> = {
    gmail:           '📧',
    google_calendar: '📅',
    notion:          '📓',
    zoho_cliq:       '💬',
    meta_ads:        '📢',
    make:            '⚙️',
};

const PROVIDER_DETAILS: Record<string, { desc: string; scopes: string[]; synced_data: string[] }> = {
    gmail: { 
        desc: 'Connect to Gmail to read, sync, and send emails.',
        scopes: ['Read incoming emails and metadata', 'Send emails on your behalf', 'Manage email labels and threads'],
        synced_data: ['Email Subjects & Snippets', 'Sender Details', 'Timestamps', 'Attachments (if configured)']
    },
    google_calendar: { 
        desc: 'Connect to Google Calendar to manage events and availability.',
        scopes: ['View your calendars', 'Create, edit, and delete events', 'Check availability'],
        synced_data: ['Event Titles & Descriptions', 'Start/End Times', 'Attendees', 'Meeting Links']
    },
    notion: { 
        desc: 'Connect to Notion to sync databases, pages, and tasks.',
        scopes: ['Read content from selected pages/databases', 'Insert new pages and blocks', 'Update properties on existing pages'],
        synced_data: ['Database Items (Tasks, Notes)', 'Page Properties', 'Content Blocks']
    },
    zoho_cliq: { 
        desc: 'Connect to Zoho Cliq for messaging and notifications.',
        scopes: ['Send messages to channels and users', 'Read messages in channels', 'Manage bots'],
        synced_data: ['Channel Messages', 'User Mentions', 'Files Shared in Monitored Channels']
    },
    meta_ads: { 
        desc: 'Connect to Meta Ads to sync campaign data and metrics.',
        scopes: ['Read ad campaigns and ad sets', 'Read ad performance insights', 'Manage ad campaigns'],
        synced_data: ['Campaign Budgets & Status', 'Ad Impressions & Clicks', 'Spend Metrics']
    },
    make: { 
        desc: 'Connect to Make.com to trigger automation scenarios.',
        scopes: ['Trigger specified webhooks', 'Read scenario status'],
        synced_data: ['Webhook Execution Results', 'Scenario Status Updates']
    },
};

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

const getFreshnessColor = (dateStr: string) => {
    const diffHours = (Date.now() - new Date(dateStr).getTime()) / (1000 * 60 * 60);
    if (diffHours > 24) return 'bg-red-400';
    if (diffHours > 1) return 'bg-yellow-400';
    return 'bg-green-400';
};

export default function AdminIntegrationsSettings({ connections, builtin_providers, global_connections, metrics, filters, organizations, diagnostics, providers_list }: Props) {
    const [activeTab, setActiveTab] = useState<'workspace' | 'global'>('workspace');
    
    // User Workspace state
    const [showForm, setShowForm] = useState(false);
    const [isCustom, setIsCustom] = useState(false);
    const [wizardStep, setWizardStep] = useState(1);
    const [syncingMap, setSyncingMap] = useState<Record<string, number>>({});
    const [editingId, setEditingId] = useState<string | null>(null);
    const confirm = useConfirm();

    // Admin Hub state
    const [globalTab, setGlobalTab] = useState<'connections' | 'providers' | 'diagnostics'>('connections');
    const [search, setSearch] = useState(filters?.search || '');
    const [migratingConnection, setMigratingConnection] = useState<GlobalConnection | null>(null);
    const [targetOrgId, setTargetOrgId] = useState<string>('');
    const [isAddProviderOpen, setIsAddProviderOpen] = useState(false);
    const [isEditProviderOpen, setIsEditProviderOpen] = useState(false);
    const [currentProvider, setCurrentProvider] = useState<any>(null);
    const [providerForm, setProviderForm] = useState({
        name: '', slug: '', description: '', adapter_class: '', is_active: true
    });

    const openAddProvider = () => {
        setProviderForm({ name: '', slug: '', description: '', adapter_class: '', is_active: true });
        setIsAddProviderOpen(true);
    };

    const openEditProvider = (provider: any) => {
        setCurrentProvider(provider);
        setProviderForm({
            name: provider.name, slug: provider.slug, description: provider.description || '',
            adapter_class: provider.adapter_class || '', is_active: provider.is_active
        });
        setIsEditProviderOpen(true);
    };

    const handleAddProvider = (e: React.FormEvent) => {
        e.preventDefault();
        router.post('/admin/mcp/providers', providerForm, {
            onSuccess: () => setIsAddProviderOpen(false)
        });
    };

    const handleEditProvider = (e: React.FormEvent) => {
        e.preventDefault();
        router.put(`/admin/mcp/providers/${currentProvider.slug}`, providerForm, {
            onSuccess: () => setIsEditProviderOpen(false)
        });
    };

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

    const isValidCredentials = () => {
        if (isCustom) {
            if (!form.data.provider || !form.data.settings.base_url) return false;
        }
        const authType = form.data.settings.auth_type;
        if (authType === 'basic') return form.data.username.length >= 3 && form.data.password.length >= 3;
        if (authType === 'api_key') return form.data.api_key.length > 5;
        if (authType === 'bearer') return form.data.access_token.length > 10;
        return true;
    };

    function submit(e: React.FormEvent) {
        e.preventDefault();
        if (editingId) {
            form.patch(`/settings/mcp/${editingId}`, {
                onSuccess: () => {
                    form.reset();
                    setShowForm(false);
                    setWizardStep(1);
                    setEditingId(null);
                },
            });
        } else {
            form.post('/settings/mcp', {
                onSuccess: () => {
                    form.reset();
                    setShowForm(false);
                    setWizardStep(1);
                },
            });
        }
    }

    function handleReauthorize(conn: McpConnection) {
        setEditingId(conn.id);
        setIsCustom(!conn.is_builtin);
        form.setData({
            provider: conn.provider,
            label: conn.label || '',
            access_token: '',
            api_key: '',
            username: '',
            password: '',
            settings: {
                base_url: '',
                auth_type: 'bearer',
                sync_endpoint: '',
                test_endpoint: '',
                webhook_secret: '',
                ...((conn.settings as any) || {}),
            },
        });
        setWizardStep(3);
        setShowForm(true);
        window.scrollTo({ top: 0, behavior: 'smooth' });
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
        setSyncingMap(prev => ({ ...prev, [id]: Date.now() }));
        router.post(`/settings/mcp/${id}/sync`, {}, { preserveScroll: true });
    }

    useEffect(() => {
        let interval: ReturnType<typeof setInterval>;
        const activeSyncs = Object.keys(syncingMap);
        if (activeSyncs.length > 0) {
            interval = setInterval(() => {
                router.reload({ 
                    only: ['connections'], 
                    onSuccess: (page) => {
                        const conns = page.props.connections as McpConnection[];
                        setSyncingMap(prev => {
                            const next = { ...prev };
                            let changed = false;
                            for (const id of Object.keys(next)) {
                                const conn = conns.find(c => c.id === id);
                                if (conn && conn.updated_at && new Date(conn.updated_at).getTime() > next[id]) {
                                    delete next[id];
                                    changed = true;
                                }
                            }
                            return changed ? next : prev;
                        });
                    }
                });
            }, 2500);
        }
        return () => clearInterval(interval);
    }, [syncingMap]);

    // Admin Hub search effect
    useEffect(() => {
        if (!global_connections) return;
        const handler = setTimeout(() => {
            if (search !== (filters?.search || '')) {
                router.get(
                    '/settings/mcp',
                    { search },
                    { preserveState: true, replace: true }
                );
            }
        }, 300);
        return () => clearTimeout(handler);
    }, [search, filters, global_connections]);

    function toggleActive(conn: McpConnection) {
        router.patch(`/settings/mcp/${conn.id}`, { is_active: !conn.is_active }, { preserveScroll: true });
    }

    return (
        <AppLayout title="Integrations & MCP">
            <Head title="Integrations & MCP" />
            <TooltipProvider delayDuration={200}>

            <div className="flex flex-col md:flex-row md:items-center justify-between mb-6 gap-4">
                <div>
                    <h1 className="text-lg font-bold text-gray-900">Integrations</h1>
                    <p className="text-sm text-gray-500 mt-0.5">Connect external services via MCP</p>
                </div>
                
                {activeTab === 'workspace' && (
                    <button
                        onClick={() => {
                            if (!showForm) setWizardStep(1);
                            setShowForm(!showForm);
                        }}
                        className="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 w-fit"
                    >
                        <Plus size={15} /> Add Connection
                    </button>
                )}
            </div>

            {/* Tabs for Super Admins */}
            {global_connections && (
                <div className="mb-6 flex gap-1 bg-gray-100 p-1 rounded-xl w-fit">
                    <button
                        onClick={() => setActiveTab('workspace')}
                        className={cn(
                            "px-4 py-2 text-sm font-medium rounded-lg transition-colors flex items-center gap-2",
                            activeTab === 'workspace' ? "bg-white text-indigo-700 shadow-sm" : "text-gray-500 hover:text-gray-700"
                        )}
                    >
                        My Workspace
                    </button>
                    <button
                        onClick={() => setActiveTab('global')}
                        className={cn(
                            "px-4 py-2 text-sm font-medium rounded-lg transition-colors flex items-center gap-2",
                            activeTab === 'global' ? "bg-white text-indigo-700 shadow-sm" : "text-gray-500 hover:text-gray-700"
                        )}
                    >
                        Global Hub <span className="px-1.5 py-0.5 rounded text-[10px] font-bold bg-gray-200 text-gray-600">Admin</span>
                    </button>
                </div>
            )}

            {/* Workspace Content */}
            {activeTab === 'workspace' && (
                <>
                    {/* Add Connection Form */}
                    {showForm && (
                        <div className="bg-white rounded-xl border border-gray-200 p-6 mb-6">
                            <div className="flex items-center justify-between mb-4">
                                <h3 className="text-sm font-semibold text-gray-900">New Connection Setup</h3>
                                <div className="flex items-center gap-1.5">
                                    {[1, 2, 3].map(step => (
                                        <div key={step} className={cn("h-2 rounded-full transition-all", wizardStep >= step ? "w-6 bg-indigo-600" : "w-2 bg-gray-200")} />
                                    ))}
                                </div>
                            </div>

                            {wizardStep === 1 && (
                                <div>
                                    <h4 className="text-sm font-medium text-gray-700 mb-3">Select a Provider</h4>
                                    <div className="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                                        {builtin_providers.map(p => (
                                            <button
                                                key={p}
                                                type="button"
                                                onClick={() => {
                                                    form.setData('provider', p);
                                                    setIsCustom(false);
                                                    setWizardStep(2);
                                                }}
                                                className={cn(
                                                    "flex flex-col items-center justify-center p-4 border rounded-xl hover:border-indigo-500 hover:bg-indigo-50 transition-colors",
                                                    form.data.provider === p && !isCustom ? "border-indigo-600 bg-indigo-50" : "border-gray-200 bg-white"
                                                )}
                                            >
                                                <span className="text-3xl mb-2">{PROVIDER_ICONS[p] ?? '🔌'}</span>
                                                <span className="text-xs font-semibold capitalize text-gray-700">{p.replace('_', ' ')}</span>
                                            </button>
                                        ))}
                                        <button
                                            type="button"
                                            onClick={() => {
                                                form.setData('provider', '');
                                                setIsCustom(true);
                                                setWizardStep(2);
                                            }}
                                            className={cn(
                                                "flex flex-col items-center justify-center p-4 border rounded-xl hover:border-indigo-500 hover:bg-indigo-50 transition-colors",
                                                isCustom ? "border-indigo-600 bg-indigo-50" : "border-gray-200 bg-white"
                                            )}
                                        >
                                            <Globe size={30} className="mb-2 text-gray-400" />
                                            <span className="text-xs font-semibold text-gray-700">Custom HTTP</span>
                                        </button>
                                    </div>
                                    <div className="flex justify-end pt-2 border-t border-gray-100">
                                        <button
                                            type="button"
                                            onClick={() => setShowForm(false)}
                                            className="px-4 py-2 text-sm text-gray-600 rounded-lg hover:bg-gray-100"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                </div>
                            )}

                            {wizardStep === 2 && (
                                <div>
                                    <div className="mb-5 flex items-start gap-4 p-5 bg-gray-50 border border-gray-100 rounded-xl">
                                        <div className="text-4xl shrink-0 mt-1">
                                            {isCustom ? <Globe size={40} className="text-gray-400" /> : (PROVIDER_ICONS[form.data.provider] ?? '🔌')}
                                        </div>
                                        <div>
                                            <h4 className="text-base font-bold text-gray-900 capitalize mb-1">
                                                {isCustom ? 'Custom HTTP Provider' : form.data.provider.replace('_', ' ')}
                                            </h4>
                                            <p className="text-sm text-gray-600 mb-4">
                                                {isCustom 
                                                    ? 'Connect to any external system using standard HTTP/REST APIs. You will need to provide endpoints and credentials.' 
                                                    : (PROVIDER_DETAILS[form.data.provider]?.desc || 'Connect this provider to sync data.')}
                                            </p>
                                            {!isCustom && PROVIDER_DETAILS[form.data.provider]?.scopes && (
                                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                    <div className="bg-white border border-gray-200 rounded-lg p-3">
                                                        <h5 className="text-xs font-semibold text-gray-900 mb-2 flex items-center gap-1.5"><Key size={13} className="text-indigo-600" /> Required Permissions:</h5>
                                                        <ul className="text-xs text-gray-600 list-disc pl-4 space-y-1">
                                                            {PROVIDER_DETAILS[form.data.provider].scopes.map((scope, idx) => (
                                                                <li key={idx}>{scope}</li>
                                                            ))}
                                                        </ul>
                                                    </div>
                                                    <div className="bg-white border border-gray-200 rounded-lg p-3">
                                                        <h5 className="text-xs font-semibold text-gray-900 mb-2 flex items-center gap-1.5"><RefreshCw size={13} className="text-blue-500" /> What will be synced:</h5>
                                                        <ul className="text-xs text-gray-600 list-disc pl-4 space-y-1">
                                                            {PROVIDER_DETAILS[form.data.provider].synced_data?.map((data, idx) => (
                                                                <li key={idx}>{data}</li>
                                                            ))}
                                                        </ul>
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    </div>

                                    <div className="flex gap-3 justify-between pt-2 mt-4 border-t border-gray-100">
                                        <button
                                            type="button"
                                            onClick={() => setWizardStep(1)}
                                            className="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50"
                                        >
                                            Back
                                        </button>
                                        <div className="flex gap-2">
                                            <a
                                                href={`https://docs.example.com/integrations/${form.data.provider}`}
                                                target="_blank" rel="noopener noreferrer"
                                                className="px-4 py-2 text-sm text-indigo-600 bg-indigo-50 hover:bg-indigo-100 rounded-lg font-medium flex items-center gap-1.5 transition-colors hidden sm:flex"
                                            >
                                                <ExternalLink size={14} /> Setup Guide
                                            </a>
                                            <button
                                                type="button"
                                                onClick={() => setWizardStep(3)}
                                                className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700"
                                            >
                                                Continue to Credentials
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            )}

                            {wizardStep === 3 && (
                                <div className="grid grid-cols-1 lg:grid-cols-5 gap-6">
                                    <form onSubmit={submit} className="space-y-4 lg:col-span-3">
                                    {!isCustom && (
                                        <div className="mb-4 bg-indigo-50/50 p-3 rounded-lg border border-indigo-100 flex items-center gap-3">
                                            <span className="text-xl">{PROVIDER_ICONS[form.data.provider] ?? '🔌'}</span>
                                            <div>
                                                <div className="text-sm font-semibold capitalize text-gray-900">{form.data.provider.replace('_', ' ')}</div>
                                                <div className="text-xs text-gray-500">Provide authentication credentials to complete setup.</div>
                                            </div>
                                        </div>
                                    )}
                                    <div className={cn("grid gap-3", isCustom ? "grid-cols-2" : "grid-cols-1")}>
                                        {isCustom && (
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
                                                {form.errors.provider && <p className="text-red-500 text-xs mt-1">{form.errors.provider}</p>}
                                            </div>
                                        )}
                                        <div>
                                            <label className="block text-xs font-medium text-gray-600 mb-1">Connection Label</label>
                                            <input
                                                type="text"
                                                value={form.data.label}
                                                onChange={e => form.setData('label', e.target.value)}
                                                placeholder={isCustom ? "My CRM" : `${form.data.provider.replace('_', ' ')} (e.g. Work, Personal)`}
                                                className="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                            />
                                            {!isCustom && <p className="text-[10px] text-gray-500 mt-1">Useful to differentiate multiple connections.</p>}
                                        </div>
                                    </div>
                                    
                                    {isCustom && (
                                        <>
                                            <div>
                                                <label className="block text-xs font-medium text-gray-600 mb-1 flex items-center gap-1">
                                                    Base URL *
                                                    <Tooltip>
                                                        <TooltipTrigger type="button"><HelpCircle size={12} className="text-gray-400 hover:text-gray-600" /></TooltipTrigger>
                                                        <TooltipContent><p className="w-48 font-normal">The root endpoint URL for this API (e.g., https://api.example.com/v1).</p></TooltipContent>
                                                    </Tooltip>
                                                </label>
                                                <input
                                                    type="url"
                                                    value={form.data.settings.base_url}
                                                    onChange={e => form.setData('settings', { ...form.data.settings, base_url: e.target.value })}
                                                    placeholder="https://api.mycrm.com/v1"
                                                    className="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                                    required={isCustom}
                                                />
                                                {form.errors['settings.base_url'] && <p className="text-red-500 text-xs mt-1">{form.errors['settings.base_url']}</p>}
                                            </div>
                                            <div className="grid grid-cols-2 gap-3">
                                                <div>
                                                    <label className="block text-xs font-medium text-gray-600 mb-1 flex items-center gap-1">
                                                        Auth Type
                                                        <Tooltip>
                                                            <TooltipTrigger type="button"><HelpCircle size={12} className="text-gray-400 hover:text-gray-600" /></TooltipTrigger>
                                                            <TooltipContent><p className="w-48 font-normal">How this API handles authentication. Usually Bearer Token for modern REST APIs.</p></TooltipContent>
                                                        </Tooltip>
                                                    </label>
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
                                                    <label className="block text-xs font-medium text-gray-600 mb-1 flex items-center gap-1">
                                                        Sync Endpoint
                                                        <Tooltip>
                                                            <TooltipTrigger type="button"><HelpCircle size={12} className="text-gray-400 hover:text-gray-600" /></TooltipTrigger>
                                                            <TooltipContent><p className="w-48 font-normal">The specific path appended to the Base URL to fetch records (e.g., /sync or /users).</p></TooltipContent>
                                                        </Tooltip>
                                                    </label>
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
                                                    className={cn(
                                                        "w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2",
                                                        form.data.username.length > 0
                                                            ? form.data.username.length >= 3 ? "border-green-300 focus:ring-green-500 bg-green-50" : "border-red-300 focus:ring-red-500 bg-red-50"
                                                            : "border-gray-200 focus:ring-indigo-500"
                                                    )}
                                                />
                                                <input
                                                    type="password"
                                                    value={form.data.password}
                                                    onChange={e => form.setData('password', e.target.value)}
                                                    placeholder="Password"
                                                    className={cn(
                                                        "w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2",
                                                        form.data.password.length > 0
                                                            ? form.data.password.length >= 3 ? "border-green-300 focus:ring-green-500 bg-green-50" : "border-red-300 focus:ring-red-500 bg-red-50"
                                                            : "border-gray-200 focus:ring-indigo-500"
                                                    )}
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
                                                className={cn(
                                                    "w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2",
                                                    ((form.data.settings.auth_type === 'api_key' ? form.data.api_key.length : form.data.access_token.length) > 0)
                                                        ? isValidCredentials() ? "border-green-300 focus:ring-green-500 bg-green-50" : "border-red-300 focus:ring-red-500 bg-red-50"
                                                        : "border-gray-200 focus:ring-indigo-500"
                                                )}
                                            />
                                        )}
                                        {((form.data.settings.auth_type === 'api_key' ? form.data.api_key.length : form.data.settings.auth_type === 'bearer' ? form.data.access_token.length : Math.max(form.data.username.length, form.data.password.length)) > 0) && !isValidCredentials() && (
                                            <p className="text-red-500 text-xs mt-1">Credentials seem too short or invalid.</p>
                                        )}
                                        {(form.errors.access_token || form.errors.api_key) && (
                                            <p className="text-red-500 text-xs mt-1">{form.errors.access_token || form.errors.api_key}</p>
                                        )}
                                    </div>

                                    <div className="flex gap-3 justify-between mt-6 pt-2 border-t border-gray-100">
                                        <button
                                            type="button"
                                            onClick={() => setWizardStep(2)}
                                            className="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50"
                                        >
                                            Back
                                        </button>
                                        <button
                                            type="submit"
                                            disabled={form.processing || !isValidCredentials()}
                                            className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50"
                                        >
                                            Connect {isCustom ? 'Provider' : form.data.provider.replace('_', ' ')}
                                        </button>
                                    </div>
                                </form>
                                {!isCustom && (
                                    <div className="lg:col-span-2 bg-indigo-50/40 rounded-xl p-5 border border-indigo-100 hidden md:block">
                                        <h5 className="text-xs font-bold text-gray-800 mb-3 uppercase tracking-wider flex items-center gap-2">
                                            <PlayCircle size={14} className="text-indigo-600" /> Connection Walkthrough
                                        </h5>
                                        <div className="aspect-video bg-gray-200 rounded-lg mb-3 relative overflow-hidden group cursor-pointer border border-gray-300 shadow-sm flex items-center justify-center">
                                            <div className="absolute inset-0 bg-gradient-to-br from-indigo-200/50 to-purple-200/50 opacity-80 group-hover:opacity-100 transition-opacity"></div>
                                            <div className="w-12 h-12 rounded-full bg-white/90 flex items-center justify-center z-10 group-hover:scale-110 transition-transform shadow-md">
                                                <PlayCircle size={24} className="text-indigo-600 ml-1" />
                                            </div>
                                            <div className="absolute bottom-2 right-2 bg-black/60 text-white text-[10px] px-1.5 py-0.5 rounded font-mono">0:45</div>
                                        </div>
                                        <p className="text-xs text-gray-600 mb-3">Not sure where to find your API key or OAuth credentials? Watch this quick 45-second guide.</p>
                                        <a href={`https://docs.example.com/integrations/${form.data.provider}#credentials`} target="_blank" rel="noopener noreferrer" className="text-xs text-indigo-600 font-medium hover:text-indigo-800 flex items-center gap-1">
                                            Read detailed instructions <ExternalLink size={10} />
                                        </a>
                                    </div>
                                )}
                                </div>
                            )}
                        </div>
                    )}

                    {/* Connections list */}
                    {connections.length === 0 ? (
                        <div className="bg-white rounded-xl border border-gray-200 p-8 md:p-12 mb-6">
                            <div className="max-w-4xl mx-auto flex flex-col md:flex-row items-center gap-8 md:gap-12">
                                <div className="flex-1 text-center md:text-left">
                                    <div className="w-16 h-16 bg-indigo-50 rounded-2xl flex items-center justify-center mx-auto md:mx-0 mb-6 border border-indigo-100">
                                        <Settings size={32} className="text-indigo-600" />
                                    </div>
                                    <h2 className="text-2xl font-bold text-gray-900 mb-3">Centralize Your Data</h2>
                                    <p className="text-gray-600 mb-8 text-sm leading-relaxed">
                                        Connect your favorite external services and platforms to sync data seamlessly. The onboarding process takes just a few minutes.
                                    </p>
                                    <button onClick={() => setShowForm(true)} className="px-5 py-2.5 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 shadow-sm transition-colors w-full md:w-auto flex items-center justify-center md:justify-start gap-2">
                                        <Plus size={18} /> Add First Connection
                                    </button>
                                </div>
                                <div className="flex-1 w-full bg-gray-50 rounded-2xl p-6 md:p-8 border border-gray-100 relative overflow-hidden">
                                    <div className="absolute top-0 right-0 w-32 h-32 bg-indigo-100 rounded-bl-full opacity-50 -mr-16 -mt-16"></div>
                                    <h3 className="text-sm font-bold text-gray-900 mb-5 uppercase tracking-wider relative z-10">Quick Start Guide</h3>
                                    <ul className="space-y-5 relative z-10">
                                        <li className="flex gap-4 items-start">
                                            <div className="flex-shrink-0 w-7 h-7 rounded-full bg-white border-2 border-indigo-200 text-indigo-700 flex items-center justify-center font-bold text-xs mt-0.5 shadow-sm">1</div>
                                            <div>
                                                <strong className="text-gray-900 text-sm">Select Provider</strong>
                                                <p className="text-xs text-gray-600 mt-0.5">Choose from our built-in integrations or set up a custom HTTP connection.</p>
                                            </div>
                                        </li>
                                        <li className="flex gap-4 items-start">
                                            <div className="flex-shrink-0 w-7 h-7 rounded-full bg-white border-2 border-indigo-200 text-indigo-700 flex items-center justify-center font-bold text-xs mt-0.5 shadow-sm">2</div>
                                            <div>
                                                <strong className="text-gray-900 text-sm">Authorize Access</strong>
                                                <p className="text-xs text-gray-600 mt-0.5">Securely grant permission using OAuth, API keys, or basic auth.</p>
                                            </div>
                                        </li>
                                        <li className="flex gap-4 items-start">
                                            <div className="flex-shrink-0 w-7 h-7 rounded-full bg-white border-2 border-indigo-200 text-indigo-700 flex items-center justify-center font-bold text-xs mt-0.5 shadow-sm">3</div>
                                            <div>
                                                <strong className="text-gray-900 text-sm">Data Syncs Automatically</strong>
                                                <p className="text-xs text-gray-600 mt-0.5">Your data starts syncing immediately in the background.</p>
                                            </div>
                                        </li>
                                    </ul>
                                </div>
                            </div>
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

                                    {syncingMap[conn.id] ? (
                                        <div className="mb-3">
                                            <div className="flex justify-between items-center mb-1">
                                                <span className="text-xs font-medium text-indigo-600 animate-pulse">Sync in progress...</span>
                                                {conn.sync_progress !== null && conn.sync_progress !== undefined && <span className="text-xs font-semibold text-indigo-600">{conn.sync_progress}%</span>}
                                            </div>
                                            <div className="w-full bg-gray-100 rounded-full h-1.5 overflow-hidden relative">
                                                {conn.sync_progress !== null && conn.sync_progress !== undefined ? (
                                                    <div className="bg-indigo-600 h-full rounded-full transition-all duration-500" style={{ width: `${conn.sync_progress}%` }}></div>
                                                ) : (
                                                    <div className="absolute top-0 bottom-0 left-0 bg-indigo-600 w-full animate-pulse"></div>
                                                )}
                                            </div>
                                        </div>
                                    ) : (
                                        <>
                                            {conn.last_sync_summary && (
                                                <p className="text-xs font-medium text-emerald-600 mb-1">{conn.last_sync_summary}</p>
                                            )}
                                            {conn.last_synced_at ? (
                                                <div className="flex items-center gap-1.5 text-xs text-gray-400 mb-3">
                                                    <span className={cn("w-2 h-2 rounded-full shrink-0", getFreshnessColor(conn.last_synced_at))} title="Data Freshness" />
                                                    <span>Last synced {timeAgo(conn.last_synced_at)}</span>
                                                </div>
                                            ) : (
                                                <div className="text-xs text-gray-500 mb-3 bg-gray-50 p-2 rounded border border-gray-100 flex items-center gap-2">
                                                    <div className="w-2 h-2 rounded-full bg-gray-300 shrink-0" />
                                                    Never synced. Click "Sync" below to fetch data.
                                                </div>
                                            )}
                                        </>
                                    )}

                                    {conn.sync_error && (() => {
                                        const isPlatformIssue = ['degraded'].includes(conn.status) || (conn.status === 'error' && (!conn.troubleshooting_guide || conn.troubleshooting_guide.length === 0));
                                        return (
                                            <div className="mt-2 mb-3 bg-red-50 border border-red-100 rounded-lg p-3">
                                                <div className="flex gap-2">
                                                    <AlertTriangle size={14} className="text-red-500 shrink-0 mt-0.5" />
                                                    <div>
                                                        <div className="flex items-center gap-2 mb-1">
                                                            <p className="text-xs font-semibold text-red-800">Sync Issue Detected</p>
                                                            <span className={cn("text-[9px] px-1.5 py-0.5 rounded font-semibold", isPlatformIssue ? "bg-red-200 text-red-800" : "bg-orange-200 text-orange-800")}>
                                                                {isPlatformIssue ? 'Platform Issue' : 'User Action Required'}
                                                            </span>
                                                        </div>
                                                        {conn.troubleshooting_guide && conn.troubleshooting_guide.length > 0 ? (
                                                            <ul className="text-xs text-red-700 list-disc pl-3 space-y-0.5">
                                                                {conn.troubleshooting_guide.map((tip, i) => (
                                                                    <li key={i}>{tip}</li>
                                                                ))}
                                                            </ul>
                                                        ) : (
                                                            <p className="text-[10px] text-red-600 font-mono break-words">{conn.sync_error}</p>
                                                        )}
                                                    </div>
                                                </div>
                                                <div className="mt-2.5 pt-2 border-t border-red-100 flex flex-wrap items-center gap-2">
                                                    {['token_expired', 'pending_reauth', 'error'].includes(conn.status) && !isPlatformIssue && (
                                                        <button
                                                            onClick={() => handleReauthorize(conn)}
                                                            className="px-2.5 py-1 bg-red-100 hover:bg-red-200 text-red-700 rounded text-[10px] font-semibold transition-colors"
                                                        >
                                                            Update Credentials
                                                        </button>
                                                    )}
                                                    {['rate_limited', 'quota_exceeded', 'suspended'].includes(conn.status) && conn.settings?.base_url && (
                                                        <a
                                                            href={conn.settings.base_url}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="px-2.5 py-1 bg-red-100 hover:bg-red-200 text-red-700 rounded text-[10px] font-semibold transition-colors flex items-center gap-1"
                                                        >
                                                            <Globe size={10} /> View Dashboard
                                                        </a>
                                                    )}
                                                    <a
                                                        href={`mailto:support@example.com?subject=${encodeURIComponent('Sync Issue with ' + conn.provider)}&body=${encodeURIComponent('Hello Support,\n\nI am experiencing an issue with my ' + conn.provider + ' integration.\n\nConnection ID: ' + conn.id + '\nStatus: ' + conn.status + '\nError Details:\n' + (conn.sync_error || 'N/A') + '\n\nPlease help me resolve this.\n')}`}
                                                        className="px-2.5 py-1 bg-white border border-red-200 text-red-700 hover:bg-red-50 rounded text-[10px] font-medium transition-colors"
                                                    >
                                                        Contact Support
                                                    </a>
                                                    <button
                                                        onClick={() => remove(conn.id)}
                                                        className="px-2.5 py-1 border border-transparent text-red-600 hover:bg-red-100 rounded text-[10px] font-medium transition-colors ml-auto"
                                                    >
                                                        Remove
                                                    </button>
                                                </div>
                                            </div>
                                        );
                                    })()}

                                    <div className="flex items-center gap-2 mt-3">
                                        <button
                                            onClick={() => sync(conn.id)}
                                            disabled={!!syncingMap[conn.id]}
                                            className="flex items-center gap-1 px-3 py-1.5 text-xs border border-gray-200 rounded-lg hover:bg-gray-50 text-gray-700 disabled:opacity-50"
                                        >
                                            <RefreshCw size={12} className={cn(!!syncingMap[conn.id] && "animate-spin")} /> {syncingMap[conn.id] ? 'Syncing...' : 'Sync'}
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
                </>
            )}

            {/* Global Hub Content (Admins only) */}
            {activeTab === 'global' && global_connections && metrics && (
                <div className="space-y-6">

                <div className="mb-6 flex gap-4 border-b border-gray-200">
                    <button onClick={() => setGlobalTab('connections')} className={cn("pb-3 text-sm font-medium transition-colors border-b-2", globalTab === 'connections' ? "border-indigo-600 text-indigo-600" : "border-transparent text-gray-500 hover:text-gray-700")}>Connections</button>
                    <button onClick={() => setGlobalTab('providers')} className={cn("pb-3 text-sm font-medium transition-colors border-b-2", globalTab === 'providers' ? "border-indigo-600 text-indigo-600" : "border-transparent text-gray-500 hover:text-gray-700")}>Providers</button>
                    <button onClick={() => setGlobalTab('diagnostics')} className={cn("pb-3 text-sm font-medium transition-colors border-b-2", globalTab === 'diagnostics' ? "border-indigo-600 text-indigo-600" : "border-transparent text-gray-500 hover:text-gray-700")}>Diagnostics</button>
                </div>
{globalTab === 'connections' && (
                    <>
                    <div className="flex flex-col md:flex-row justify-between items-start md:items-center bg-white border border-gray-200 p-6 rounded-2xl shadow-sm gap-4 mb-6">
                        <div>
                            <h2 className="text-xl font-bold text-gray-900 flex items-center gap-2">
                                <Plug className="w-5 h-5 text-indigo-500" />
                                Global MCP Hub
                            </h2>
                            <p className="mt-1 text-sm text-gray-500">
                                Centralized view of all Model Context Protocol connections across all organizations.
                            </p>
                        </div>
                        <div className="relative w-full md:w-64">
                            <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <Search className="h-4 w-4 text-gray-400" />
                            </div>
                            <input
                                type="text"
                                placeholder="Search connections..."
                                value={search}
                                onChange={onSearchChange}
                                className="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-xl leading-5 bg-white text-gray-900 placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition-colors"
                            />
                        </div>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <div className="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
                            <div className="flex items-center justify-between">
                                <h3 className="text-sm font-medium text-gray-500">Total Connections</h3>
                                <Plug className="w-5 h-5 text-indigo-500" />
                            </div>
                            <p className="mt-2 text-3xl font-bold text-gray-900">{metrics.total}</p>
                        </div>
                        <div className="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
                            <div className="flex items-center justify-between">
                                <h3 className="text-sm font-medium text-gray-500">Active</h3>
                                <CheckCircle2 className="w-5 h-5 text-emerald-500" />
                            </div>
                            <p className="mt-2 text-3xl font-bold text-gray-900">{metrics.active}</p>
                        </div>
                        <div className="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
                            <div className="flex items-center justify-between">
                                <h3 className="text-sm font-medium text-gray-500">Degraded</h3>
                                <AlertTriangle className="w-5 h-5 text-amber-500" />
                            </div>
                            <p className="mt-2 text-3xl font-bold text-gray-900">{metrics.degraded}</p>
                        </div>
                        <div className="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
                            <div className="flex items-center justify-between">
                                <h3 className="text-sm font-medium text-gray-500">Errors</h3>
                                <ServerCrash className="w-5 h-5 text-rose-500" />
                            </div>
                            <p className="mt-2 text-3xl font-bold text-gray-900">{metrics.error}</p>
                        </div>
                    </div>

                    <div className="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Connection</th>
                                        <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Organization</th>
                                        <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                        <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rate Limit</th>
                                        <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Synced</th>
                                        <th scope="col" className="relative px-6 py-3"><span className="sr-only">Actions</span></th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200">
                                    {global_connections.data.length === 0 ? (
                                        <tr><td colSpan={7} className="px-6 py-12 text-center text-gray-500">No connections found.</td></tr>
                                    ) : (
                                        global_connections.data.map((conn) => (
                                            <tr key={conn.id} className="hover:bg-gray-50">
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="flex items-center">
                                                        <div className="flex-shrink-0 h-10 w-10 flex items-center justify-center bg-indigo-100 text-indigo-500 rounded-lg">
                                                            <Plug className="h-5 w-5" />
                                                        </div>
                                                        <div className="ml-4">
                                                            <div className="text-sm font-medium text-gray-900">{conn.name}</div>
                                                            <div className="text-xs text-gray-500">{conn.provider}</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="flex items-center text-sm text-gray-900">
                                                        <Building className="w-4 h-4 mr-1 text-gray-400" />
                                                        {conn.organization?.name || 'Unknown'}
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="text-sm text-gray-900">{conn.user?.name || 'Unknown'}</div>
                                                    <div className="text-xs text-gray-500">{conn.user?.email || ''}</div>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${
                                                        conn.status === 'active' ? 'bg-green-100 text-green-800' :
                                                        conn.status === 'error' ? 'bg-red-100 text-red-800' :
                                                        'bg-yellow-100 text-yellow-800'
                                                    }`}>
                                                        {conn.status}
                                                    </span>
                                                    {conn.sync_error && (
                                                        <div className="text-[10px] text-red-500 mt-1 max-w-[150px] truncate" title={conn.sync_error}>
                                                            {conn.sync_error}
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    {(() => {
                                                        const rl = conn.settings?.rate_limits;
                                                        if (!rl || rl.limit === null) return <span className="text-sm text-gray-500">-</span>;
                                                        const percent = rl.limit > 0 ? (rl.remaining / rl.limit) : 0;
                                                        const isLow = percent < 0.2;
                                                        return (
                                                            <div className="flex flex-col">
                                                                <span className={`text-sm font-medium ${isLow ? 'text-red-500' : 'text-gray-900'}`}>
                                                                    {rl.remaining} / {rl.limit}
                                                                </span>
                                                                {isLow && (
                                                                    <span className="text-xs text-red-500 mt-1 flex items-center">
                                                                        <AlertTriangle className="w-3 h-3 mr-1" /> Approaching Limit
                                                                    </span>
                                                                )}
                                                            </div>
                                                        );
                                                    })()}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {conn.last_synced_at ? new Date(conn.last_synced_at).toLocaleString() : 'Never'}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                                    <Link href={`/admin/mcp/${conn.id}/history`} className="text-indigo-600 hover:text-indigo-900">
                                                        History
                                                    </Link>
                                                    <button onClick={() => setMigratingConnection(conn)} className="text-emerald-600 hover:text-emerald-900 ml-2">
                                                        Migrate
                                                    </button>
                                                    {conn.user && (
                                                        <Link href={`/admin/impersonate/${conn.user.id}`} method="post" as="button" className="text-amber-600 hover:text-amber-900 border border-amber-600 rounded px-2 py-1 ml-2 transition-colors">
                                                            Impersonate
                                                        </Link>
                                                    )}
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                        {global_connections.last_page > 1 && (
                            <div className="px-6 py-3 border-t border-gray-200 bg-gray-50 flex items-center justify-between">
                                <div className="text-sm text-gray-500">
                                    Showing page {global_connections.current_page} of {global_connections.last_page}
                                </div>
                                <div className="flex space-x-2">
                                    {global_connections.links.map((link, i) => (
                                        <button
                                            key={i}
                                            onClick={() => link.url && router.get(link.url, { search: search }, { preserveState: true })}
                                            disabled={!link.url}
                                            className={`px-3 py-1 rounded text-sm ${
                                                link.active 
                                                ? 'bg-indigo-600 text-white' 
                                                : link.url 
                                                    ? 'bg-white text-gray-700 hover:bg-gray-50' 
                                                    : 'bg-transparent text-gray-400 cursor-not-allowed'
                                            }`}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>

                    </>
                    )}

                    {globalTab === 'providers' && providers_list && (
                        <div className="space-y-6">
                            <div className="flex justify-between items-center mb-4">
                                <h2 className="text-xl font-bold text-gray-900">Manage Providers</h2>
                                <button onClick={openAddProvider} className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 flex items-center gap-2">
                                    <Plus size={15} /> Add Provider
                                </button>
                            </div>
                            <div className="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Provider</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Slug</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                            <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-200">
                                        {providers_list.map(p => (
                                            <tr key={p.id}>
                                                <td className="px-6 py-4">
                                                    <div className="text-sm font-medium text-gray-900">{p.name}</div>
                                                    <div className="text-xs text-gray-500">{p.description}</div>
                                                </td>
                                                <td className="px-6 py-4 text-sm text-gray-500 font-mono">{p.slug}</td>
                                                <td className="px-6 py-4">
                                                    {p.is_active ? <span className="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">Active</span> : <span className="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">Disabled</span>}
                                                </td>
                                                <td className="px-6 py-4 text-right">
                                                    <button onClick={() => openEditProvider(p)} className="text-indigo-600 hover:text-indigo-900"><Settings size={16}/></button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}

                    {globalTab === 'diagnostics' && diagnostics && (
                        <div className="space-y-6">
                            <h2 className="text-xl font-bold text-gray-900">System Diagnostics</h2>
                            <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
                                <div className="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
                                    <div className="flex items-center justify-between"><h3 className="text-sm text-gray-500">Active Syncs</h3><MonitorPlay className="w-5 h-5 text-blue-500" /></div>
                                    <p className="mt-2 text-3xl font-bold">{diagnostics.activeSyncs}</p>
                                </div>
                                <div className="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
                                    <div className="flex items-center justify-between"><h3 className="text-sm text-gray-500">High Queue</h3><Activity className="w-5 h-5 text-indigo-500" /></div>
                                    <p className="mt-2 text-3xl font-bold">{diagnostics.queues.high}</p>
                                </div>
                                <div className="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
                                    <div className="flex items-center justify-between"><h3 className="text-sm text-gray-500">Default Queue</h3><Activity className="w-5 h-5 text-gray-500" /></div>
                                    <p className="mt-2 text-3xl font-bold">{diagnostics.queues.default}</p>
                                </div>
                                <div className="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
                                    <div className="flex items-center justify-between"><h3 className="text-sm text-gray-500">Low Queue</h3><Activity className="w-5 h-5 text-rose-500" /></div>
                                    <p className="mt-2 text-3xl font-bold">{diagnostics.queues.low}</p>
                                </div>
                            </div>
                            <div className="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
                                <div className="px-6 py-4 border-b border-gray-200"><h3 className="font-semibold text-gray-900">Recent Errors</h3></div>
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Provider</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Connection</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Error</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-200">
                                        {diagnostics.recentErrors.length === 0 ? (
                                            <tr><td colSpan={4} className="px-6 py-8 text-center text-gray-500">No recent errors</td></tr>
                                        ) : diagnostics.recentErrors.map(e => (
                                            <tr key={e.id}>
                                                <td className="px-6 py-4 text-sm">{e.provider}</td>
                                                <td className="px-6 py-4 text-sm">{e.connection_name}</td>
                                                <td className="px-6 py-4 text-sm text-red-600 font-mono text-xs">{e.error_message}</td>
                                                <td className="px-6 py-4 text-sm text-gray-500">{e.created_at}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}

                </div>
            )}
            
            </TooltipProvider>

            <Modal show={!!migratingConnection} onClose={() => setMigratingConnection(null)} maxWidth="md">
                <div className="p-6 bg-white">
                    <h2 className="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <ArrowRightLeft className="w-5 h-5 text-indigo-500" />
                        Migrate Connection
                    </h2>
                    <p className="text-sm text-gray-500 mb-4">
                        Move the <strong>{migratingConnection?.name}</strong> connection to a different organization.
                    </p>
                    <div className="space-y-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Target Organization</label>
                            <select
                                value={targetOrgId}
                                onChange={(e) => setTargetOrgId(e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            >
                                <option value="">Select Organization</option>
                                {organizations?.map((org) => (
                                    <option key={org.id} value={org.id} disabled={org.id === migratingConnection?.organization?.id}>
                                        {org.name} {org.id === migratingConnection?.organization?.id ? '(Current)' : ''}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>
                    <div className="mt-6 flex justify-end gap-3">
                        <button
                            onClick={() => setMigratingConnection(null)}
                            className="px-4 py-2 text-sm text-gray-700 hover:text-gray-900"
                        >
                            Cancel
                        </button>
                        <button
                            disabled={!targetOrgId}
                            onClick={() => {
                                if (migratingConnection && targetOrgId) {
                                    router.post(`/admin/mcp/${migratingConnection.id}/migrate`, {
                                        organization_id: targetOrgId
                                    }, {
                                        onSuccess: () => setMigratingConnection(null)
                                    });
                                }
                            }}
                            className="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium disabled:opacity-50"
                        >
                            Migrate
                        </button>
                    </div>
                </div>
            </Modal>

            <Modal show={isAddProviderOpen || isEditProviderOpen} onClose={() => { setIsAddProviderOpen(false); setIsEditProviderOpen(false); }} maxWidth="md">
                <div className="p-6 bg-white">
                    <h2 className="text-lg font-bold mb-4">{isAddProviderOpen ? 'Add Provider' : 'Edit Provider'}</h2>
                    <form onSubmit={isAddProviderOpen ? handleAddProvider : handleEditProvider} className="space-y-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Name</label>
                            <input value={providerForm.name} onChange={e => setProviderForm({...providerForm, name: e.target.value})} className="w-full border-gray-300 rounded-md shadow-sm" required />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Slug</label>
                            <input value={providerForm.slug} onChange={e => setProviderForm({...providerForm, slug: e.target.value})} className="w-full border-gray-300 rounded-md shadow-sm" required disabled={isEditProviderOpen} />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea value={providerForm.description} onChange={e => setProviderForm({...providerForm, description: e.target.value})} className="w-full border-gray-300 rounded-md shadow-sm" rows={3} />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Adapter Class</label>
                            <input value={providerForm.adapter_class} onChange={e => setProviderForm({...providerForm, adapter_class: e.target.value})} className="w-full border-gray-300 rounded-md shadow-sm" />
                        </div>
                        <div className="flex items-center gap-2">
                            <input type="checkbox" id="is_active" checked={providerForm.is_active} onChange={e => setProviderForm({...providerForm, is_active: e.target.checked})} className="rounded border-gray-300" />
                            <label htmlFor="is_active" className="text-sm">Active</label>
                        </div>
                        <div className="flex justify-end gap-3 mt-6">
                            <button type="button" onClick={() => { setIsAddProviderOpen(false); setIsEditProviderOpen(false); }} className="px-4 py-2 text-sm">Cancel</button>
                            <button type="submit" className="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm">{isAddProviderOpen ? 'Add' : 'Save'}</button>
                        </div>
                    </form>
                </div>
            </Modal>
        </AppLayout>
    );
}

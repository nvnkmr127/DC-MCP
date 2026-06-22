import React, { useState, useEffect } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, router, Link } from '@inertiajs/react';
import Modal from '@/Components/ui/Modal';
import {
    Activity,
    Search,
    Plug,
    CheckCircle2,
    AlertTriangle,
    ServerCrash,
    Building,
    ArrowRightLeft
} from 'lucide-react';

interface Organization {
    id: number;
    name: string;
}

interface User {
    id: number;
    name: string;
    email: string;
}

interface Connection {
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
}

interface Props {
    connections: {
        data: Connection[];
        current_page: number;
        last_page: number;
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    metrics: {
        total: number;
        active: number;
        error: number;
        degraded: number;
    };
    filters: {
        search?: string;
    };
    organizations: Organization[];
}

export default function McpConnections({ connections, metrics, filters, organizations }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [migratingConnection, setMigratingConnection] = useState<Connection | null>(null);
    const [targetOrgId, setTargetOrgId] = useState<string>('');

    useEffect(() => {
        const handler = setTimeout(() => {
            if (search !== (filters.search || '')) {
                router.get(
                    '/admin/mcp',
                    { search },
                    { preserveState: true, replace: true }
                );
            }
        }, 300);

        return () => clearTimeout(handler);
    }, [search]);

    const onSearchChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        setSearch(e.target.value);
    };

    return (
        <AppLayout title="Global MCP Hub">
            <Head title="Global MCP Hub | Admin" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                    {/* Header Section */}
                    <div className="flex flex-col md:flex-row justify-between items-start md:items-center bg-white/5 backdrop-blur-xl border border-white/10 p-6 rounded-2xl shadow-xl gap-4">
                        <div>
                            <h2 className="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-blue-400 to-indigo-500 flex items-center gap-2">
                                <Plug className="w-6 h-6 text-blue-500" />
                                Global MCP Hub
                            </h2>
                            <p className="mt-1 text-sm text-gray-400">
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
                                className="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-slate-700 rounded-xl leading-5 bg-white dark:bg-slate-800 text-gray-900 dark:text-gray-100 placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition-colors"
                            />
                        </div>
                    </div>

                    {/* Quick Stats Metrics */}
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm relative overflow-hidden group">
                            <div className="absolute inset-0 bg-gradient-to-br from-blue-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                            <div className="flex items-center justify-between">
                                <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400">Total Connections</h3>
                                <Plug className="w-5 h-5 text-blue-500" />
                            </div>
                            <p className="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{metrics.total}</p>
                            <p className="mt-1 text-xs text-gray-500">Across all organizations</p>
                        </div>

                        <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm relative overflow-hidden group">
                            <div className="absolute inset-0 bg-gradient-to-br from-emerald-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                            <div className="flex items-center justify-between">
                                <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400">Active</h3>
                                <CheckCircle2 className="w-5 h-5 text-emerald-500" />
                            </div>
                            <p className="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{metrics.active}</p>
                            <p className="mt-1 text-xs text-gray-500">Currently healthy</p>
                        </div>

                        <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm relative overflow-hidden group">
                            <div className="absolute inset-0 bg-gradient-to-br from-amber-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                            <div className="flex items-center justify-between">
                                <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400">Degraded</h3>
                                <AlertTriangle className="w-5 h-5 text-amber-500" />
                            </div>
                            <p className="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{metrics.degraded}</p>
                            <p className="mt-1 text-xs text-gray-500">Experiencing issues</p>
                        </div>

                        <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm relative overflow-hidden group">
                            <div className="absolute inset-0 bg-gradient-to-br from-rose-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                            <div className="flex items-center justify-between">
                                <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400">Errors</h3>
                                <ServerCrash className="w-5 h-5 text-rose-500" />
                            </div>
                            <p className="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{metrics.error}</p>
                            <p className="mt-1 text-xs text-gray-500">Failed connections</p>
                        </div>
                    </div>

                    {/* Connections Table */}
                    <div className="bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-700 rounded-2xl shadow-sm overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
                                <thead className="bg-gray-50 dark:bg-slate-900/50">
                                    <tr>
                                        <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Connection</th>
                                        <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Organization</th>
                                        <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                        <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rate Limit</th>
                                        <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Synced</th>
                                        <th scope="col" className="relative px-6 py-3">
                                            <span className="sr-only">Actions</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-slate-700">
                                    {connections.data.length === 0 ? (
                                        <tr>
                                            <td colSpan={5} className="px-6 py-12 text-center text-gray-500">
                                                No connections found.
                                            </td>
                                        </tr>
                                    ) : (
                                        connections.data.map((conn) => (
                                            <tr key={conn.id} className="hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors">
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="flex items-center">
                                                        <div className="flex-shrink-0 h-10 w-10 flex items-center justify-center bg-indigo-100 text-indigo-500 rounded-lg">
                                                            <Plug className="h-5 w-5" />
                                                        </div>
                                                        <div className="ml-4">
                                                            <div className="text-sm font-medium text-gray-900 dark:text-white">
                                                                {conn.name}
                                                            </div>
                                                            <div className="text-xs text-gray-500">
                                                                {conn.provider}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="flex items-center text-sm text-gray-900 dark:text-white">
                                                        <Building className="w-4 h-4 mr-1 text-gray-400" />
                                                        {conn.organization?.name || 'Unknown'}
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="text-sm text-gray-900 dark:text-white">{conn.user?.name || 'Unknown'}</div>
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
                                                        const rl = (conn as any).settings?.rate_limits;
                                                        if (!rl || rl.limit === null) return <span className="text-sm text-gray-500">-</span>;
                                                        const percent = rl.limit > 0 ? (rl.remaining / rl.limit) : 0;
                                                        const isLow = percent < 0.2;
                                                        return (
                                                            <div className="flex flex-col">
                                                                <span className={`text-sm font-medium ${isLow ? 'text-red-500' : 'text-gray-900 dark:text-gray-300'}`}>
                                                                    {rl.remaining} / {rl.limit}
                                                                </span>
                                                                {isLow && (
                                                                    <span className="text-xs text-red-500 mt-1 flex items-center">
                                                                        <AlertTriangle className="w-3 h-3 mr-1" />
                                                                        Approaching Limit
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
                                                    <Link
                                                        href={`/admin/mcp/${conn.id}/history`}
                                                        className="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300"
                                                    >
                                                        History
                                                    </Link>
                                                    <button
                                                        onClick={() => setMigratingConnection(conn)}
                                                        className="text-emerald-600 hover:text-emerald-900 dark:text-emerald-500 dark:hover:text-emerald-400 ml-2"
                                                    >
                                                        Migrate
                                                    </button>
                                                    {conn.user && (
                                                        <Link
                                                            href={`/admin/impersonate/${conn.user.id}`}
                                                            method="post"
                                                            as="button"
                                                            className="text-amber-600 hover:text-amber-900 dark:text-amber-500 dark:hover:text-amber-400 border border-amber-600 rounded px-2 py-1 ml-2 transition-colors"
                                                        >
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
                        {/* Pagination Component can be integrated here if needed */}
                        {connections.last_page > 1 && (
                            <div className="px-6 py-3 border-t border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-800 flex items-center justify-between">
                                <div className="text-sm text-gray-500">
                                    Showing page {connections.current_page} of {connections.last_page}
                                </div>
                                <div className="flex space-x-2">
                                    {connections.links.map((link, i) => (
                                        <button
                                            key={i}
                                            onClick={() => link.url && router.get(link.url)}
                                            disabled={!link.url}
                                            className={`px-3 py-1 rounded text-sm ${
                                                link.active 
                                                ? 'bg-indigo-600 text-white' 
                                                : link.url 
                                                    ? 'bg-white dark:bg-slate-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-600' 
                                                    : 'bg-transparent text-gray-400 cursor-not-allowed'
                                            }`}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            <Modal show={!!migratingConnection} onClose={() => setMigratingConnection(null)} maxWidth="md">
                <div className="p-6 bg-white dark:bg-[#0f172a]">
                    <h2 className="text-lg font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                        <ArrowRightLeft className="w-5 h-5 text-indigo-500" />
                        Migrate Connection
                    </h2>
                    <p className="text-sm text-gray-500 dark:text-gray-400 mb-4">
                        Move the <strong>{migratingConnection?.name}</strong> connection to a different organization.
                    </p>
                    <div className="space-y-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Target Organization
                            </label>
                            <select
                                value={targetOrgId}
                                onChange={(e) => setTargetOrgId(e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-white/5 dark:border-white/10 dark:text-white sm:text-sm"
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
                            className="px-4 py-2 text-sm text-gray-700 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white"
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
        </AppLayout>
    );
}

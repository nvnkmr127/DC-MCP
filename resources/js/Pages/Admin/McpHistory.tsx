import React from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Clock, ServerCrash, CheckCircle2 } from 'lucide-react';

interface SyncLog {
    id: number;
    status: string;
    duration_ms: number;
    records_processed: number;
    bytes_transferred: number;
    error_message: string | null;
    metadata: any;
    created_at: string;
}

interface Connection {
    id: number;
    name: string;
    provider: string;
    organization: {
        name: string;
    };
    user: {
        name: string;
    };
}

interface Props {
    connection: Connection;
    logs: {
        data: SyncLog[];
        current_page: number;
        last_page: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
}

export default function McpHistory({ connection, logs }: Props) {
    return (
        <AppLayout title={`Sync History - ${connection.name}`}>
            <Head title={`Sync History - ${connection.name} | Admin`} />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                    {/* Header */}
                    <div className="bg-white/5 backdrop-blur-xl border border-white/10 p-6 rounded-2xl shadow-xl flex items-center justify-between">
                        <div>
                            <Link href="/admin/mcp" className="inline-flex items-center text-sm text-indigo-500 hover:text-indigo-600 mb-2 transition-colors">
                                <ArrowLeft className="w-4 h-4 mr-1" /> Back to Hub
                            </Link>
                            <h2 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                <Clock className="w-6 h-6 text-indigo-500" />
                                Sync History: {connection.name}
                            </h2>
                            <p className="mt-1 text-sm text-gray-400">
                                Provider: {connection.provider} | Org: {connection.organization?.name} | User: {connection.user?.name}
                            </p>
                        </div>
                    </div>

                    {/* Logs Table */}
                    <div className="bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-700 rounded-2xl shadow-sm overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
                                <thead className="bg-gray-50 dark:bg-slate-900/50">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Timestamp</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Processed</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Size</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Metadata</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-slate-700">
                                    {logs.data.length === 0 ? (
                                        <tr>
                                            <td colSpan={6} className="px-6 py-12 text-center text-gray-500">
                                                No sync logs found for this connection.
                                            </td>
                                        </tr>
                                    ) : (
                                        logs.data.map((log) => (
                                            <tr key={log.id} className="hover:bg-gray-50 dark:hover:bg-slate-700/50">
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-300">
                                                    {new Date(log.created_at).toLocaleString()}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <span className={`px-2 py-1 inline-flex items-center text-xs leading-5 font-semibold rounded-full ${
                                                        log.status === 'success' ? 'bg-green-100 text-green-800' :
                                                        log.status === 'running' ? 'bg-blue-100 text-blue-800' :
                                                        'bg-red-100 text-red-800'
                                                    }`}>
                                                        {log.status === 'success' && <CheckCircle2 className="w-3 h-3 mr-1" />}
                                                        {log.status === 'error' && <ServerCrash className="w-3 h-3 mr-1" />}
                                                        {log.status}
                                                    </span>
                                                    {log.error_message && (
                                                        <div className="text-xs text-red-500 mt-1 max-w-xs truncate" title={log.error_message}>
                                                            {log.error_message}
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {log.duration_ms ? `${(log.duration_ms / 1000).toFixed(2)}s` : '-'}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {log.records_processed ?? '-'}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {log.bytes_transferred ? `${(log.bytes_transferred / 1024).toFixed(2)} KB` : '-'}
                                                </td>
                                                <td className="px-6 py-4 text-sm text-gray-500 max-w-sm">
                                                    {log.metadata ? (
                                                        <details className="cursor-pointer">
                                                            <summary className="text-indigo-500 hover:text-indigo-600">View JSON</summary>
                                                            <pre className="mt-2 text-xs bg-gray-100 dark:bg-slate-900 p-2 rounded overflow-x-auto">
                                                                {JSON.stringify(log.metadata, null, 2)}
                                                            </pre>
                                                        </details>
                                                    ) : '-'}
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                        
                        {logs.last_page > 1 && (
                            <div className="px-6 py-3 border-t border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-800 flex items-center justify-between">
                                <div className="text-sm text-gray-500">
                                    Showing page {logs.current_page} of {logs.last_page}
                                </div>
                                <div className="flex space-x-2">
                                    {logs.links.map((link, i) => (
                                        <Link
                                            key={i}
                                            href={link.url || '#'}
                                            className={`px-3 py-1 border rounded text-sm ${link.active ? 'bg-indigo-50 border-indigo-500 text-indigo-600' : 'border-gray-300 text-gray-500 hover:bg-gray-50'} ${!link.url ? 'opacity-50 cursor-not-allowed' : ''}`}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

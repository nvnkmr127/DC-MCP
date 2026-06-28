import React, { useState, useEffect } from 'react';
import { Breadcrumbs } from '@/Components/Shared/Breadcrumbs';
import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import { Activity, Search, Filter, Eye, Clock, User as UserIcon } from 'lucide-react';
import Modal from '@/Components/ui/Modal';
import { Pagination } from '@/Components/ui/Pagination';

interface ActivityLog {
    id: number;
    user: { name: string; email: string } | null;
    subject_type: string | null;
    subject_id: string | number | null;
    description: string;
    changes: any;
    created_at: string;
}

interface Props {
    logs: {
        data: ActivityLog[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { search?: string; subject_type?: string; date?: string };
    subjectTypes: string[];
}

export default function AuditLogs({ logs, filters, subjectTypes }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [subjectType, setSubjectType] = useState(filters.subject_type || '');
    const [date, setDate] = useState(filters.date || '');
    const [selectedLog, setSelectedLog] = useState<ActivityLog | null>(null);

    useEffect(() => {
        const handler = setTimeout(() => {
            if (search !== (filters.search || '') || subjectType !== (filters.subject_type || '') || date !== (filters.date || '')) {
                router.get(
                    '/admin/audit-logs',
                    { search, subject_type: subjectType, date },
                    { preserveState: true, replace: true }
                );
            }
        }, 300);
        return () => clearTimeout(handler);
    }, [search, subjectType, date]);

    return (
        <AppLayout title="Audit Logs">
            <Head title="Audit Logs | Admin" />
            <div className="mb-6">
                <Breadcrumbs items={[
                    { label: 'Admin', href: '/admin' },
                    { label: 'Audit Logs | Admin' }
                ]} />
            </div>

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                    <div className="flex flex-col md:flex-row justify-between items-start md:items-center bg-white/5 backdrop-blur-xl border border-white/10 p-6 rounded-2xl shadow-xl gap-4">
                        <div>
                            <h2 className="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-blue-400 to-indigo-500 flex items-center gap-2">
                                <Activity className="w-6 h-6 text-blue-500" />
                                Audit Logs
                            </h2>
                            <p className="mt-1 text-sm text-gray-400">
                                System-wide activity and audit trail.
                            </p>
                        </div>
                        <div className="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
                            <select
                                value={subjectType}
                                onChange={(e) => setSubjectType(e.target.value)}
                                className="bg-white/5 border-white/10 text-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500"
                            >
                                <option value="">All Subjects</option>
                                {subjectTypes.map(st => (
                                    <option key={st} value={st}>{st}</option>
                                ))}
                            </select>
                            <input
                                type="date"
                                value={date}
                                onChange={e => setDate(e.target.value)}
                                className="bg-white/5 border-white/10 text-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500"
                            />
                            <div className="relative w-full md:w-64">
                                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <Search className="h-4 w-4 text-gray-400" />
                                </div>
                                <input
                                    type="text"
                                    value={search}
                                    onChange={e => setSearch(e.target.value)}
                                    placeholder="Search logs..."
                                    className="pl-10 w-full bg-white/5 border-white/10 text-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500"
                                />
                            </div>
                        </div>
                    </div>

                    <div className="bg-white/5 backdrop-blur-xl border border-white/10 rounded-2xl shadow-xl overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left border-collapse">
                                <thead>
                                    <tr className="bg-white/5 border-b border-white/10 text-gray-300 text-sm">
                                        <th className="p-4 font-medium">Timestamp</th>
                                        <th className="p-4 font-medium">User</th>
                                        <th className="p-4 font-medium">Description</th>
                                        <th className="p-4 font-medium">Subject</th>
                                        <th className="p-4 font-medium text-right">Details</th>
                                    </tr>
                                </thead>
                                <tbody className="text-sm divide-y divide-white/5">
                                    {logs.data.map((log) => (
                                        <tr key={log.id} className="hover:bg-white/5 transition-colors">
                                            <td className="p-4 whitespace-nowrap text-gray-400">
                                                <div className="flex items-center gap-1.5">
                                                    <Clock className="w-4 h-4" />
                                                    {new Date(log.created_at).toLocaleString()}
                                                </div>
                                            </td>
                                            <td className="p-4 text-gray-300">
                                                <div className="flex items-center gap-2">
                                                    <UserIcon className="w-4 h-4 text-gray-500" />
                                                    {log.user ? log.user.name : 'System'}
                                                </div>
                                            </td>
                                            <td className="p-4 text-gray-200">
                                                {log.description}
                                            </td>
                                            <td className="p-4 text-gray-400 text-xs">
                                                {log.subject_type ? (
                                                    <span>{log.subject_type.split('\\').pop()} (#{log.subject_id})</span>
                                                ) : '-'}
                                            </td>
                                            <td className="p-4 text-right">
                                                {log.changes && (
                                                    <button
                                                        onClick={() => setSelectedLog(log)}
                                                        className="p-1.5 text-gray-400 hover:text-white bg-white/5 hover:bg-white/10 rounded-lg transition-colors inline-flex items-center gap-2"
                                                        title="View Details"
                                                    >
                                                        <Eye className="w-4 h-4" />
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                    {logs.data.length === 0 && (
                                        <tr>
                                            <td colSpan={5} className="p-8 text-center text-gray-400">
                                                No audit logs found matching criteria.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <div className="bg-white/5 border-t border-white/10">
                            <Pagination 
                                meta={logs}
                                onPageChange={(page) => router.get('/admin/audit-logs', { ...filters, page }, { preserveState: true, preserveScroll: true })}
                                labelSingular="log"
                                labelPlural="logs"
                                alwaysShowCount={true}
                                className="border-none"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <Modal show={!!selectedLog} onClose={() => setSelectedLog(null)} maxWidth="2xl">
                <div className="p-6 bg-[#0f172a] text-gray-200">
                    <h2 className="text-lg font-bold mb-4 flex items-center gap-2">
                        <Activity className="w-5 h-5 text-indigo-400" />
                        Log Details
                    </h2>
                    {selectedLog && (
                        <div className="space-y-4">
                            <div className="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span className="text-gray-500 block mb-1">Timestamp</span>
                                    {new Date(selectedLog.created_at).toLocaleString()}
                                </div>
                                <div>
                                    <span className="text-gray-500 block mb-1">User</span>
                                    {selectedLog.user ? `${selectedLog.user.name} (${selectedLog.user.email})` : 'System'}
                                </div>
                                <div>
                                    <span className="text-gray-500 block mb-1">Subject</span>
                                    {selectedLog.subject_type || 'N/A'} {selectedLog.subject_id ? `#${selectedLog.subject_id}` : ''}
                                </div>
                            </div>
                            
                            <div>
                                <span className="text-gray-500 block mb-1 text-sm">Description</span>
                                <div className="bg-white/5 p-3 rounded border border-white/10">
                                    {selectedLog.description}
                                </div>
                            </div>

                            <div>
                                <span className="text-gray-500 block mb-1 text-sm">Changes / Payload</span>
                                <pre className="bg-black/50 p-4 rounded-lg overflow-x-auto text-xs font-mono border border-white/10 text-gray-300">
                                    {JSON.stringify(selectedLog.changes, null, 2)}
                                </pre>
                            </div>
                        </div>
                    )}
                    <div className="mt-6 flex justify-end">
                        <button
                            onClick={() => setSelectedLog(null)}
                            className="px-4 py-2 bg-white/5 hover:bg-white/10 border border-white/10 rounded-lg text-sm transition-colors"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </Modal>
        </AppLayout>
    );
}

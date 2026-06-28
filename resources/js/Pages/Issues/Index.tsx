import React, { useState } from 'react';
import { Button } from '@/Components/ui/Button';
import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { cn } from '@/lib/utils';
import { Plus, X, Bug, Zap, HelpCircle, MessageSquare, ChevronDown } from 'lucide-react';

interface Issue {
    id: string; title: string; description: string | null; type: string;
    priority: string; status: string; source: string; resolution: string | null;
    resolved_at: string | null; created_at: string; task_id: string | null;
    client: { id: string; name: string } | null;
    assignee: { id: string; name: string } | null;
}
interface Client { id: string; name: string; }
interface User { id: string; name: string; }
interface Props { issues: Issue[]; clients: Client[]; users: User[]; filters: Record<string, string>; }

const PRIORITY_STYLES: Record<string, string> = {
    low: 'bg-gray-100 text-gray-700', medium: 'bg--100 text--800',
    high: 'bg--100 text--800', critical: 'bg-rose-100 text-rose-700',
};
const STATUS_STYLES: Record<string, string> = {
    open:        'bg-amber-100 text-amber-800',
    in_progress: 'bg-indigo-100 text-indigo-700',
    resolved:    'bg-emerald-100 text-emerald-700',
    closed:      'bg-gray-100 text-gray-700',
};
const TYPE_ICONS: Record<string, React.ReactNode> = {
    bug: <Bug size={16} className="text-rose-500" />, enhancement: <Zap size={16} className="text-blue-500" />,
    question: <HelpCircle size={16} className="text-amber-500" />, feedback: <MessageSquare size={16} className="text-emerald-500" />,
};
const BORDER_PRIORITY: Record<string, string> = {
    low: 'border-gray-200', medium: 'border-amber-200', high: 'border-orange-300', critical: 'border-rose-400',
};

function ReportModal({ clients, users, onClose }: { clients: Client[]; users: User[]; onClose: () => void }) {
    const form = useForm({ title: '', type: 'bug', priority: 'medium', client_id: '', description: '' });
    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-3">
                <div className="flex items-center justify-between">
                    <h2 className="text-[15px] font-bold text-gray-900">Report Issue</h2>
                    <Button onClick={onClose}><X size={16} className="text-gray-400" /></Button>
                </div>
                <form onSubmit={e => { e.preventDefault(); form.post('/issues', { onSuccess: onClose }); }} className="space-y-3">
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Title *</label>
                        <input type="text" value={form.data.title} onChange={e => form.setData('title', e.target.value)}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Type</label>
                            <select value={form.data.type} onChange={e => form.setData('type', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                {['bug', 'enhancement', 'question', 'feedback'].map(t => <option key={t} value={t}>{t}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Priority</label>
                            <select value={form.data.priority} onChange={e => form.setData('priority', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                {['low', 'medium', 'high', 'critical'].map(p => <option key={p} value={p}>{p}</option>)}
                            </select>
                        </div>
                    </div>
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Client</label>
                        <select value={form.data.client_id} onChange={e => form.setData('client_id', e.target.value)}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                            <option value="">No client</option>
                            {clients.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Description</label>
                        <textarea value={form.data.description} onChange={e => form.setData('description', e.target.value)} rows={4}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 resize-none" />
                    </div>
                    <div className="flex justify-end gap-2 pt-1">
                        <Button type="button" onClick={onClose} variant="ghost" >Cancel</Button>
                        <Button type="submit" disabled={form.processing || !form.data.title}
                            className="disabled:opacity-50" >
                            {form.processing ? 'Saving…' : 'Report Issue'}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export default function IssuesIndex({ issues, clients, users, filters }: Props) {
    const [reportOpen, setReportOpen] = useState(false);
    const [expandedId, setExpandedId] = useState<string | null>(null);
    const [statusFilter, setStatusFilter] = useState(filters.status ?? '');
    const [priorityFilter, setPriorityFilter] = useState(filters.priority ?? '');
    const [resolution, setResolution] = useState<Record<string, string>>({});

    const filtered = issues.filter(i =>
        (!statusFilter || i.status === statusFilter) &&
        (!priorityFilter || i.priority === priorityFilter)
    );

    const relTime = (iso: string) => {
        const d = Math.floor((Date.now() - new Date(iso).getTime()) / 86400000);
        return d === 0 ? 'today' : `${d}d ago`;
    };

    return (
        <AppLayout title="Issues">
            <Head title="Issues" />
            <div className="max-w-4xl space-y-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-lg font-bold text-gray-900">Issue Tracker</h1>
                    <Button onClick={() => setReportOpen(true)}
                        className="flex items-center gap-2 px-3 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                        <Plus size={16} /> Report Issue
                    </Button>
                </div>

                <div className="flex items-center gap-2 flex-wrap">
                    {['', 'open', 'in_progress', 'resolved', 'closed'].map(s => (
                        <Button key={s} onClick={() => setStatusFilter(s)}
                            className={cn('px-3 py-1.5 text-xs rounded-lg font-medium transition-colors',
                                statusFilter === s ? 'bg-indigo-600 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50')}>
                            {s === '' ? 'All' : s.replace('_', ' ')}
                        </Button>
                    ))}
                    <select value={priorityFilter} onChange={e => setPriorityFilter(e.target.value)}
                        className="ml-2 border border-gray-200 rounded-lg px-2 py-1.5 text-xs text-gray-600 bg-white focus:ring-1 focus:ring-indigo-500">
                        <option value="">All priorities</option>
                        {['low', 'medium', 'high', 'critical'].map(p => <option key={p} value={p}>{p}</option>)}
                    </select>
                </div>

                <div className="space-y-2">
                    {filtered.length === 0 && (
                        <div className="bg-white rounded-xl border border-dashed border-gray-200 px-5 py-10 text-center text-sm text-gray-400">
                            No issues found.
                        </div>
                    )}
                    {filtered.map(issue => (
                        <div key={issue.id} className={cn('bg-white rounded-xl border-l-2 border-r border-t border-b cursor-pointer', BORDER_PRIORITY[issue.priority] ?? 'border-gray-200')}>
                            <div className="px-4 py-3 flex items-center gap-3" onClick={() => setExpandedId(expandedId === issue.id ? null : issue.id)}>
                                <div className="shrink-0">{TYPE_ICONS[issue.type] ?? <Bug size={16} />}</div>
                                <div className="flex-1 min-w-0">
                                    <p className="text-sm font-medium text-gray-900">{issue.title}</p>
                                    <p className="text-xs text-gray-500 mt-0.5">
                                        {issue.client?.name ?? 'Internal'} · {relTime(issue.created_at)}
                                        {issue.assignee ? ` · ${issue.assignee.name}` : ''}
                                    </p>
                                </div>
                                <div className="flex items-center gap-2 shrink-0">
                                    <span className={cn('px-2 py-0.5 rounded text-[10px] font-semibold capitalize', PRIORITY_STYLES[issue.priority])}>
                                        {issue.priority}
                                    </span>
                                    <span className={cn('px-2 py-0.5 rounded text-[10px] font-semibold', STATUS_STYLES[issue.status] ?? STATUS_STYLES.open)}>
                                        {issue.status.replace('_', ' ')}
                                    </span>
                                    <ChevronDown size={16} className={cn('text-gray-400 transition-transform', expandedId === issue.id && 'rotate-180')} />
                                </div>
                            </div>
                            {expandedId === issue.id && (
                                <div className="px-4 pb-4 border-t border-gray-100 pt-3 space-y-3">
                                    {issue.description && <p className="text-sm text-gray-600">{issue.description}</p>}
                                    {issue.status !== 'resolved' && (
                                        <div>
                                            <label className="text-xs text-gray-500 font-medium">Resolution</label>
                                            <textarea value={resolution[issue.id] ?? ''} onChange={e => setResolution(r => ({ ...r, [issue.id]: e.target.value }))}
                                                rows={2} placeholder="Describe how this was resolved…"
                                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 resize-none" />
                                        </div>
                                    )}
                                    <div className="flex gap-2">
                                        {issue.status !== 'resolved' && (
                                            <Button onClick={() => router.patch(`/issues/${issue.id}`, { status: 'resolved', resolution: resolution[issue.id] ?? '' })}
                                                className="px-3 py-1.5 bg-emerald-600 text-white text-xs font-medium rounded-lg hover:bg-emerald-700">
                                                Mark Resolved
                                            </Button>
                                        )}
                                        {!issue.task_id && (
                                            <Button onClick={() => router.post(`/issues/${issue.id}/task`)}
                                                className="px-3 py-1.5 border border-gray-200 text-gray-600 text-xs font-medium rounded-lg hover:bg-gray-50">
                                                Convert to Task
                                            </Button>
                                        )}
                                        {issue.task_id && (
                                            <span className="px-3 py-1.5 text-xs text-emerald-600 font-medium">↗ Linked to Task</span>
                                        )}
                                    </div>
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            </div>
            {reportOpen && <ReportModal clients={clients} users={users} onClose={() => setReportOpen(false)} />}
        </AppLayout>
    );
}

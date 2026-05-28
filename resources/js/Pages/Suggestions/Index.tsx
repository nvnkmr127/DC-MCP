import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import {
    Sparkles, CheckCircle2, XCircle, ChevronDown, ChevronUp,
    Clock, User, Tag, Calendar, AlertTriangle, Zap, History,
    CheckSquare, Edit3, Layers, ArrowRight,
} from 'lucide-react';
import { cn } from '@/lib/utils';

interface Suggestion {
    id: string;
    title: string;
    description: string | null;
    role_required: string | null;
    priority: 'low' | 'medium' | 'high' | 'urgent';
    due_date: string | null;
    estimated_hours: number | null;
    status: 'pending' | 'approved' | 'rejected' | 'modified';
    suggested_by: string;
    reasoning: string | null;
    rejection_reason: string | null;
    approved_at: string | null;
    created_at: string;
    project: { id: string; name: string } | null;
    client: { id: string; name: string } | null;
    approver: { id: string; name: string } | null;
    task: { id: string; title: string; status: string } | null;
}

interface Props {
    pending: Suggestion[];
    recent: Suggestion[];
    stats: { pending_count: number; approved_today: number; rejected_today: number };
    projects: { id: string; name: string; client_id: string }[];
    clients: { id: string; name: string; company: string }[];
}

const PRIORITY_STYLES: Record<string, string> = {
    urgent: 'bg-red-100 text-red-700 border border-red-200',
    high:   'bg-orange-100 text-orange-700 border border-orange-200',
    medium: 'bg-blue-100 text-blue-700 border border-blue-200',
    low:    'bg-gray-100 text-gray-600 border border-gray-200',
};

const ROLE_LABELS: Record<string, string> = {
    ceo:             'CEO',
    project_manager: 'Project Manager',
    analyst:         'Analyst',
    marketer:        'Marketer',
    developer:       'Developer',
    designer:        'Designer',
    copywriter:      'Copywriter',
};

function SuggestionCard({
    suggestion,
    projects,
}: {
    suggestion: Suggestion;
    projects: Props['projects'];
}) {
    const [expanded, setExpanded]       = useState(false);
    const [editing, setEditing]         = useState(false);
    const [rejectOpen, setRejectOpen]   = useState(false);
    const [rejectReason, setRejectReason] = useState('');
    const [processing, setProcessing]   = useState(false);

    const [form, setForm] = useState({
        title:       suggestion.title,
        description: suggestion.description ?? '',
        priority:    suggestion.priority,
        due_date:    suggestion.due_date ?? '',
        project_id:  suggestion.project?.id ?? '',
    });

    const approve = (overrides?: typeof form) => {
        setProcessing(true);
        router.post(`/suggestions/${suggestion.id}/approve`, overrides ?? {}, {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
        });
    };

    const reject = () => {
        setProcessing(true);
        router.post(`/suggestions/${suggestion.id}/reject`, { reason: rejectReason }, {
            preserveScroll: true,
            onFinish: () => { setProcessing(false); setRejectOpen(false); },
        });
    };

    const today = new Date().toISOString().split('T')[0];
    const isOverdue = suggestion.due_date && suggestion.due_date < today;

    return (
        <div className={cn(
            'bg-white rounded-xl border shadow-sm transition-all duration-200',
            suggestion.priority === 'urgent' ? 'border-red-200' : 'border-gray-200',
        )}>
            {/* Card Header */}
            <div className="p-4">
                <div className="flex items-start gap-3">
                    <div className="mt-0.5 w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center flex-shrink-0">
                        <Sparkles className="w-4 h-4 text-indigo-500" />
                    </div>

                    <div className="flex-1 min-w-0">
                        {editing ? (
                            <input
                                className="w-full text-sm font-semibold text-gray-900 border-b border-indigo-300 bg-transparent outline-none pb-0.5 mb-2"
                                value={form.title}
                                onChange={e => setForm(f => ({ ...f, title: e.target.value }))}
                            />
                        ) : (
                            <p className="text-sm font-semibold text-gray-900 leading-snug">{suggestion.title}</p>
                        )}

                        <div className="flex flex-wrap gap-1.5 mt-2">
                            <span className={cn('text-xs font-medium px-2 py-0.5 rounded-full', PRIORITY_STYLES[suggestion.priority])}>
                                {suggestion.priority.toUpperCase()}
                            </span>
                            {suggestion.role_required && (
                                <span className="text-xs font-medium px-2 py-0.5 rounded-full bg-purple-50 text-purple-700 border border-purple-200">
                                    {ROLE_LABELS[suggestion.role_required] ?? suggestion.role_required}
                                </span>
                            )}
                            {suggestion.client && (
                                <span className="text-xs font-medium px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    {suggestion.client.name}
                                </span>
                            )}
                            {suggestion.project && (
                                <span className="text-xs font-medium px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 border border-gray-200">
                                    {suggestion.project.name}
                                </span>
                            )}
                        </div>

                        <div className="flex gap-3 mt-2 text-xs text-gray-500">
                            {suggestion.due_date && (
                                <span className={cn('flex items-center gap-1', isOverdue && 'text-red-500 font-medium')}>
                                    <Calendar className="w-3 h-3" />
                                    Due {suggestion.due_date}
                                    {isOverdue && <AlertTriangle className="w-3 h-3" />}
                                </span>
                            )}
                            {suggestion.estimated_hours && (
                                <span className="flex items-center gap-1">
                                    <Clock className="w-3 h-3" />
                                    ~{suggestion.estimated_hours}h
                                </span>
                            )}
                        </div>
                    </div>

                    <button
                        onClick={() => setExpanded(e => !e)}
                        className="p-1 rounded hover:bg-gray-100 text-gray-400 flex-shrink-0"
                    >
                        {expanded ? <ChevronUp className="w-4 h-4" /> : <ChevronDown className="w-4 h-4" />}
                    </button>
                </div>
            </div>

            {/* Expanded Details */}
            {expanded && (
                <div className="px-4 pb-3 border-t border-gray-100 pt-3 space-y-3">
                    {editing ? (
                        <textarea
                            className="w-full text-sm text-gray-700 border rounded-lg p-2 bg-gray-50 resize-none outline-none focus:border-indigo-300"
                            rows={3}
                            value={form.description}
                            onChange={e => setForm(f => ({ ...f, description: e.target.value }))}
                        />
                    ) : (
                        suggestion.description && (
                            <p className="text-sm text-gray-600 leading-relaxed">{suggestion.description}</p>
                        )
                    )}

                    {suggestion.reasoning && !editing && (
                        <div className="flex gap-2 bg-indigo-50 rounded-lg p-3">
                            <Sparkles className="w-4 h-4 text-indigo-400 flex-shrink-0 mt-0.5" />
                            <p className="text-xs text-indigo-700 italic">{suggestion.reasoning}</p>
                        </div>
                    )}

                    {editing && (
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className="text-xs font-medium text-gray-500 mb-1 block">Priority</label>
                                <select
                                    className="w-full text-sm border rounded-lg p-2 bg-white outline-none focus:border-indigo-300"
                                    value={form.priority}
                                    onChange={e => setForm(f => ({ ...f, priority: e.target.value as any }))}
                                >
                                    {['low', 'medium', 'high', 'urgent'].map(p => (
                                        <option key={p} value={p}>{p.charAt(0).toUpperCase() + p.slice(1)}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="text-xs font-medium text-gray-500 mb-1 block">Due Date</label>
                                <input
                                    type="date"
                                    className="w-full text-sm border rounded-lg p-2 bg-white outline-none focus:border-indigo-300"
                                    value={form.due_date}
                                    onChange={e => setForm(f => ({ ...f, due_date: e.target.value }))}
                                />
                            </div>
                            <div className="col-span-2">
                                <label className="text-xs font-medium text-gray-500 mb-1 block">Project</label>
                                <select
                                    className="w-full text-sm border rounded-lg p-2 bg-white outline-none focus:border-indigo-300"
                                    value={form.project_id}
                                    onChange={e => setForm(f => ({ ...f, project_id: e.target.value }))}
                                >
                                    <option value="">No project</option>
                                    {projects.map(p => (
                                        <option key={p.id} value={p.id}>{p.name}</option>
                                    ))}
                                </select>
                            </div>
                        </div>
                    )}
                </div>
            )}

            {/* Reject Reason Input */}
            {rejectOpen && (
                <div className="px-4 pb-3 border-t border-gray-100 pt-3">
                    <textarea
                        className="w-full text-sm border rounded-lg p-2 bg-gray-50 resize-none outline-none focus:border-red-300"
                        rows={2}
                        placeholder="Reason for dismissing (optional)"
                        value={rejectReason}
                        onChange={e => setRejectReason(e.target.value)}
                    />
                </div>
            )}

            {/* Actions */}
            <div className="px-4 py-3 border-t border-gray-100 flex items-center gap-2">
                {editing ? (
                    <>
                        <button
                            onClick={() => approve(form)}
                            disabled={processing}
                            className="flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50"
                        >
                            <CheckCircle2 className="w-3.5 h-3.5" />
                            Save & Approve
                        </button>
                        <button
                            onClick={() => setEditing(false)}
                            className="px-3 py-1.5 text-xs text-gray-500 hover:text-gray-700 transition-colors"
                        >
                            Cancel
                        </button>
                    </>
                ) : rejectOpen ? (
                    <>
                        <button
                            onClick={reject}
                            disabled={processing}
                            className="flex items-center gap-1.5 px-3 py-1.5 bg-red-500 text-white text-xs font-medium rounded-lg hover:bg-red-600 transition-colors disabled:opacity-50"
                        >
                            <XCircle className="w-3.5 h-3.5" />
                            Confirm Dismiss
                        </button>
                        <button
                            onClick={() => setRejectOpen(false)}
                            className="px-3 py-1.5 text-xs text-gray-500 hover:text-gray-700 transition-colors"
                        >
                            Cancel
                        </button>
                    </>
                ) : (
                    <>
                        <button
                            onClick={() => approve()}
                            disabled={processing}
                            className="flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 text-white text-xs font-medium rounded-lg hover:bg-emerald-700 transition-colors disabled:opacity-50"
                        >
                            <CheckCircle2 className="w-3.5 h-3.5" />
                            Approve
                        </button>
                        <button
                            onClick={() => { setEditing(true); setExpanded(true); }}
                            disabled={processing}
                            className="flex items-center gap-1.5 px-3 py-1.5 bg-white text-gray-700 text-xs font-medium rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors disabled:opacity-50"
                        >
                            <Edit3 className="w-3.5 h-3.5" />
                            Edit & Approve
                        </button>
                        <button
                            onClick={() => setRejectOpen(true)}
                            disabled={processing}
                            className="flex items-center gap-1.5 px-3 py-1.5 text-gray-400 text-xs font-medium hover:text-red-500 transition-colors disabled:opacity-50 ml-auto"
                        >
                            <XCircle className="w-3.5 h-3.5" />
                            Dismiss
                        </button>
                    </>
                )}
            </div>
        </div>
    );
}

function HistoryCard({ suggestion }: { suggestion: Suggestion }) {
    const isApproved = suggestion.status === 'approved';

    return (
        <div className="flex items-start gap-3 py-3 border-b border-gray-100 last:border-0">
            <div className={cn(
                'w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5',
                isApproved ? 'bg-emerald-100' : 'bg-gray-100',
            )}>
                {isApproved
                    ? <CheckCircle2 className="w-3.5 h-3.5 text-emerald-600" />
                    : <XCircle className="w-3.5 h-3.5 text-gray-400" />}
            </div>
            <div className="flex-1 min-w-0">
                <p className="text-sm font-medium text-gray-900 truncate">{suggestion.title}</p>
                <div className="flex items-center gap-2 mt-0.5 text-xs text-gray-500">
                    {suggestion.role_required && (
                        <span>{ROLE_LABELS[suggestion.role_required] ?? suggestion.role_required}</span>
                    )}
                    {suggestion.approver && <span>by {suggestion.approver.name}</span>}
                    {suggestion.approved_at && (
                        <span>{new Date(suggestion.approved_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>
                    )}
                </div>
                {!isApproved && suggestion.rejection_reason && (
                    <p className="text-xs text-gray-400 mt-0.5 italic">{suggestion.rejection_reason}</p>
                )}
                {isApproved && suggestion.task && (
                    <a
                        href={`/tasks/${suggestion.task.id}`}
                        className="inline-flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-800 mt-0.5"
                    >
                        <ArrowRight className="w-3 h-3" /> View task
                    </a>
                )}
            </div>
        </div>
    );
}

export default function SuggestionsIndex({ pending, recent, stats, projects, clients }: Props) {
    const [bulkProcessing, setBulkProcessing] = useState(false);

    const approveAll = () => {
        if (!confirm(`Approve all ${stats.pending_count} pending suggestions?`)) return;
        setBulkProcessing(true);
        router.post('/suggestions/bulk-approve', {}, {
            preserveScroll: true,
            onFinish: () => setBulkProcessing(false),
        });
    };

    return (
        <AppLayout title="AI Suggestions">
            <Head title="AI Task Suggestions" />

            <div className="max-w-5xl mx-auto px-4 py-6 space-y-6">

                {/* Header */}
                <div className="flex items-start justify-between">
                    <div>
                        <div className="flex items-center gap-2 mb-1">
                            <Sparkles className="w-5 h-5 text-indigo-500" />
                            <h1 className="text-xl font-bold text-gray-900">AI Task Suggestions</h1>
                        </div>
                        <p className="text-sm text-gray-500">
                            Review and approve tasks suggested by your AI briefing. Approved tasks are auto-assigned to the right team member.
                        </p>
                    </div>
                    {stats.pending_count > 1 && (
                        <button
                            onClick={approveAll}
                            disabled={bulkProcessing}
                            className="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50"
                        >
                            <Zap className="w-4 h-4" />
                            Approve All ({stats.pending_count})
                        </button>
                    )}
                </div>

                {/* Stats */}
                <div className="grid grid-cols-3 gap-3">
                    {[
                        { label: 'Pending Review', value: stats.pending_count, color: 'text-indigo-600', bg: 'bg-indigo-50', icon: Layers },
                        { label: 'Approved Today', value: stats.approved_today, color: 'text-emerald-600', bg: 'bg-emerald-50', icon: CheckSquare },
                        { label: 'Dismissed Today', value: stats.rejected_today, color: 'text-gray-500', bg: 'bg-gray-50', icon: XCircle },
                    ].map(({ label, value, color, bg, icon: Icon }) => (
                        <div key={label} className={cn('rounded-xl border border-gray-200 p-4 flex items-center gap-3', bg)}>
                            <Icon className={cn('w-5 h-5', color)} />
                            <div>
                                <p className={cn('text-2xl font-bold', color)}>{value}</p>
                                <p className="text-xs text-gray-500">{label}</p>
                            </div>
                        </div>
                    ))}
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">

                    {/* Pending Suggestions */}
                    <div className="lg:col-span-2 space-y-3">
                        <h2 className="text-sm font-semibold text-gray-700 flex items-center gap-2">
                            <Sparkles className="w-4 h-4 text-indigo-400" />
                            Pending Review
                            {stats.pending_count > 0 && (
                                <span className="ml-1 bg-indigo-100 text-indigo-700 text-xs font-bold px-2 py-0.5 rounded-full">
                                    {stats.pending_count}
                                </span>
                            )}
                        </h2>

                        {pending.length === 0 ? (
                            <div className="bg-white rounded-xl border border-gray-200 p-8 text-center">
                                <CheckCircle2 className="w-10 h-10 text-emerald-400 mx-auto mb-3" />
                                <p className="text-sm font-medium text-gray-700">All caught up!</p>
                                <p className="text-xs text-gray-400 mt-1">No pending suggestions. Check back after the next morning briefing.</p>
                            </div>
                        ) : (
                            pending.map(s => (
                                <SuggestionCard key={s.id} suggestion={s} projects={projects} />
                            ))
                        )}
                    </div>

                    {/* History Panel */}
                    <div className="space-y-3">
                        <h2 className="text-sm font-semibold text-gray-700 flex items-center gap-2">
                            <History className="w-4 h-4 text-gray-400" />
                            Recent Activity
                        </h2>

                        <div className="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
                            {recent.length === 0 ? (
                                <div className="p-6 text-center">
                                    <p className="text-xs text-gray-400">No activity yet.</p>
                                </div>
                            ) : (
                                <div className="px-4">
                                    {recent.map(s => <HistoryCard key={s.id} suggestion={s} />)}
                                </div>
                            )}
                        </div>
                    </div>

                </div>
            </div>
        </AppLayout>
    );
}

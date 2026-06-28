import React, { useState } from 'react';
import { Button } from '@/Components/ui/Button';
import { Head, useForm } from '@inertiajs/react';
import { CheckCircle2, Clock, AlertTriangle, FolderKanban, FileText, MessageSquare, Plus, X, LogOut } from 'lucide-react';
import { cn } from '@/lib/utils';

interface Project {
    id: string;
    name: string;
    status: string;
    end_date: string | null;
    task_counts: { total: number; done: number; in_progress: number; overdue: number };
}

interface Request {
    id: string;
    title: string;
    type: string;
    status: string;
    priority: string;
    created_at: string;
}

interface Props {
    portalUser: { id: string; name: string; email: string };
    clientName: string;
    projects: Project[];
    sharedReports: { share_id: string; note: string | null; shared_at: string }[];
    myRequests: Request[];
}

const STATUS_COLORS: Record<string, string> = {
    active:    'bg-emerald-100 text-emerald-700',
    on_hold:   'bg--100 text--800',
    completed: 'bg-gray-100 text-gray-700',
    draft:     'bg-blue-100 text-blue-700',
};

function NewRequestModal({ onClose }: { onClose: () => void }) {
    const { data, setData, post, processing } = useForm({
        title:       '',
        description: '',
        type:        'new_request',
        priority:    'medium',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/portal/requests', { onSuccess: onClose });
    };

    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-2xl shadow-2xl w-full max-w-md">
                <div className="flex items-center justify-between px-6 py-4 border-b">
                    <h2 className="font-semibold text-gray-900">Submit a Request</h2>
                    <Button onClick={onClose}><X className="w-4 h-4 text-gray-400" /></Button>
                </div>
                <form onSubmit={submit} className="p-6 space-y-4">
                    <div>
                        <label className="text-xs font-medium text-gray-500 mb-1 block">Type</label>
                        <select className="w-full text-sm border rounded-lg p-2" value={data.type} onChange={e => setData('type', e.target.value)}>
                            <option value="new_request">New Request</option>
                            <option value="feedback">Feedback</option>
                            <option value="question">Question</option>
                            <option value="bug">Issue / Bug</option>
                        </select>
                    </div>
                    <div>
                        <label className="text-xs font-medium text-gray-500 mb-1 block">Subject *</label>
                        <input className="w-full text-sm border rounded-lg p-2" placeholder="Brief summary of your request" value={data.title} onChange={e => setData('title', e.target.value)} required />
                    </div>
                    <div>
                        <label className="text-xs font-medium text-gray-500 mb-1 block">Details</label>
                        <textarea className="w-full text-sm border rounded-lg p-2 resize-none" rows={4} placeholder="Describe what you need in detail..." value={data.description} onChange={e => setData('description', e.target.value)} />
                    </div>
                    <div className="flex gap-2">
                        <Button type="submit" disabled={processing} className="flex-1 disabled:opacity-50" >
                            Submit Request
                        </Button>
                        <Button type="button" onClick={onClose} variant="ghost" >Cancel</Button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export default function PortalDashboard({ portalUser, clientName, projects, sharedReports, myRequests }: Props) {
    const [requestOpen, setRequestOpen] = useState(false);

    const today = new Date().toISOString().split('T')[0];

    return (
        <div className="min-h-screen bg-[#f4f5f7]">
            <Head title={`${clientName} — Client Portal`} />

            {requestOpen && <NewRequestModal onClose={() => setRequestOpen(false)} />}

            {/* Top bar */}
            <header className="bg-white border-b border-gray-200 px-6 py-3 flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <div className="w-8 h-8 bg-indigo-600 rounded-lg flex items-center justify-center">
                        <span className="text-white font-bold text-sm">D</span>
                    </div>
                    <div>
                        <p className="text-sm font-semibold text-gray-900">{clientName}</p>
                        <p className="text-xs text-gray-400">Client Portal</p>
                    </div>
                </div>
                <div className="flex items-center gap-3">
                    <Button
                        onClick={() => setRequestOpen(true)}
                        className="flex items-center gap-1.5 text-sm px-3 py-1.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"
                    >
                        <Plus className="w-3.5 h-3.5" /> New Request
                    </Button>
                    <div className="text-right">
                        <p className="text-xs font-medium text-gray-700">{portalUser.name}</p>
                        <a href="/portal/logout" className="text-xs text-gray-400 hover:text-red-500 flex items-center gap-1 justify-end">
                            <LogOut className="w-3 h-3" /> Sign out
                        </a>
                    </div>
                </div>
            </header>

            <main className="max-w-5xl mx-auto px-4 py-6 space-y-6">

                {/* Projects */}
                <section>
                    <h2 className="text-sm font-semibold text-gray-700 flex items-center gap-2 mb-3">
                        <FolderKanban className="w-4 h-4 text-indigo-400" /> Your Projects
                    </h2>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {projects.length === 0 ? (
                            <div className="bg-white rounded-xl border border-gray-200 p-6 text-center col-span-2">
                                <p className="text-sm text-gray-400">No active projects yet.</p>
                            </div>
                        ) : (
                            projects.map(p => {
                                const progress = p.task_counts.total > 0
                                    ? Math.round((p.task_counts.done / p.task_counts.total) * 100)
                                    : 0;
                                return (
                                    <div key={p.id} className="bg-white rounded-xl border border-gray-200 p-4">
                                        <div className="flex items-start justify-between mb-3">
                                            <p className="font-semibold text-gray-900 text-sm">{p.name}</p>
                                            <span className={cn('text-xs font-medium px-2 py-0.5 rounded-full', STATUS_COLORS[p.status] ?? 'bg-gray-100 text-gray-700')}>
                                                {p.status.replace('_', ' ')}
                                            </span>
                                        </div>

                                        {/* Progress bar */}
                                        <div className="mb-3">
                                            <div className="flex justify-between text-xs text-gray-400 mb-1">
                                                <span>Progress</span>
                                                <span>{progress}%</span>
                                            </div>
                                            <div className="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                                <div className="h-full bg-indigo-500 rounded-full transition-all" style={{ width: `${progress}%` }} />
                                            </div>
                                        </div>

                                        <div className="flex gap-3 text-xs text-gray-500">
                                            <span className="flex items-center gap-1 text-emerald-600">
                                                <CheckCircle2 className="w-3 h-3" /> {p.task_counts.done} done
                                            </span>
                                            <span className="flex items-center gap-1">
                                                <Clock className="w-3 h-3" /> {p.task_counts.in_progress} in progress
                                            </span>
                                            {p.task_counts.overdue > 0 && (
                                                <span className="flex items-center gap-1 text-red-500">
                                                    <AlertTriangle className="w-3 h-3" /> {p.task_counts.overdue} overdue
                                                </span>
                                            )}
                                        </div>
                                        {p.end_date && (
                                            <p className="text-xs text-gray-400 mt-2">Target: {p.end_date}</p>
                                        )}
                                    </div>
                                );
                            })
                        )}
                    </div>
                </section>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">

                    {/* Shared Reports */}
                    <section>
                        <h2 className="text-sm font-semibold text-gray-700 flex items-center gap-2 mb-3">
                            <FileText className="w-4 h-4 text-gray-400" /> Shared Reports
                        </h2>
                        <div className="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
                            {sharedReports.length === 0 ? (
                                <div className="p-5 text-center">
                                    <p className="text-xs text-gray-400">No reports shared yet.</p>
                                </div>
                            ) : (
                                sharedReports.map((s, i) => (
                                    <div key={s.share_id} className="p-3 flex items-center justify-between">
                                        <div>
                                            <p className="text-sm font-medium text-gray-800">Report {i + 1}</p>
                                            {s.note && <p className="text-xs text-gray-400">{s.note}</p>}
                                            <p className="text-xs text-gray-300">{new Date(s.shared_at).toLocaleDateString()}</p>
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    </section>

                    {/* My Requests */}
                    <section>
                        <h2 className="text-sm font-semibold text-gray-700 flex items-center gap-2 mb-3">
                            <MessageSquare className="w-4 h-4 text-gray-400" /> My Requests
                        </h2>
                        <div className="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
                            {myRequests.length === 0 ? (
                                <div className="p-5 text-center">
                                    <p className="text-xs text-gray-400">No requests submitted yet.</p>
                                    <Button onClick={() => setRequestOpen(true)} className="mt-2 text-xs text-indigo-600 hover:underline">Submit your first request</Button>
                                </div>
                            ) : (
                                myRequests.map(r => (
                                    <div key={r.id} className="p-3">
                                        <div className="flex items-start justify-between">
                                            <p className="text-sm font-medium text-gray-800 flex-1 truncate pr-2">{r.title}</p>
                                            <span className={cn(
                                                'text-xs font-medium px-2 py-0.5 rounded-full flex-shrink-0',
                                                r.status === 'open' ? 'bg-blue-100 text-blue-700' :
                                                r.status === 'actioned' ? 'bg-emerald-100 text-emerald-700' :
                                                'bg-gray-100 text-gray-700',
                                            )}>
                                                {r.status}
                                            </span>
                                        </div>
                                        <p className="text-xs text-gray-400 mt-0.5">
                                            {new Date(r.created_at).toLocaleDateString()}
                                        </p>
                                    </div>
                                ))
                            )}
                        </div>
                    </section>

                </div>
            </main>
        </div>
    );
}

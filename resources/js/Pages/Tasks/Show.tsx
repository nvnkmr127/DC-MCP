import React, { useState, useRef } from 'react';
import { Head, Link, useForm, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { cn, TASK_STATUS_COLORS, PRIORITY_COLORS, formatDate, formatDateTime, timeAgo, formatHours } from '@/lib/utils';
import type { Task, Comment, Attachment, TimeEntry } from '@/types';
import {
    MessageSquare, Paperclip, Clock, Send,
    Upload, Play, Square, Trash2, Edit, ArrowLeft,
} from 'lucide-react';

interface Props {
    task: Task & { comments: Comment[]; attachments: Attachment[]; time_entries: TimeEntry[] };
}

export default function TaskShow({ task }: Props) {
    const [activeTab, setActiveTab] = useState<'comments' | 'attachments' | 'time'>('comments');
    const [timerRunning, setTimerRunning] = useState(false);
    const [timerSeconds, setTimerSeconds] = useState(0);
    const timerRef = useRef<ReturnType<typeof setInterval> | null>(null);

    // Comment form
    const commentForm = useForm({ body: '' });

    // Time log form
    const timeForm = useForm({ hours: '', description: '', logged_date: new Date().toISOString().slice(0, 10) });

    // File upload
    const fileRef = useRef<HTMLInputElement>(null);

    function submitComment(e: React.FormEvent) {
        e.preventDefault();
        commentForm.post(`/tasks/${task.id}/comments`, {
            preserveScroll: true,
            onSuccess: () => commentForm.reset(),
        });
    }

    function submitTime(e: React.FormEvent) {
        e.preventDefault();
        timeForm.post(`/tasks/${task.id}/log-time`, {
            preserveScroll: true,
            onSuccess: () => timeForm.reset(),
        });
    }

    function toggleTimer() {
        if (timerRunning) {
            clearInterval(timerRef.current!);
            setTimerRunning(false);
            const hours = +(timerSeconds / 3600).toFixed(2);
            timeForm.setData('hours', String(hours));
            timeForm.setData('description', 'Time tracked via timer');
            setTimerSeconds(0);
        } else {
            setTimerRunning(true);
            timerRef.current = setInterval(() => setTimerSeconds((s) => s + 1), 1000);
        }
    }

    function formatTimer(s: number) {
        const h = Math.floor(s / 3600).toString().padStart(2, '0');
        const m = Math.floor((s % 3600) / 60).toString().padStart(2, '0');
        const sec = (s % 60).toString().padStart(2, '0');
        return `${h}:${m}:${sec}`;
    }

    function uploadFile(e: React.ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0];
        if (!file) return;
        const form = new FormData();
        form.append('file', file);
        form.append('attachable_type', 'task');
        form.append('attachable_id', task.id);
        router.post('/attachments', form, { preserveScroll: true });
    }

    function updateStatus(status: string) {
        router.patch(`/tasks/${task.id}`, { status }, { preserveScroll: true });
    }

    const totalLogged = task.time_entries.reduce((s, e) => s + e.hours, 0);

    return (
        <AppLayout>
            <Head title={task.title} />

            <div className="max-w-5xl mx-auto">
                {/* Breadcrumb + actions */}
                <div className="flex items-center justify-between mb-4">
                    <div className="flex items-center gap-2 text-sm text-gray-500">
                        <Link href="/projects" className="hover:text-indigo-600">Projects</Link>
                        <span>/</span>
                        <Link href={`/projects/${task.project_id}`} className="hover:text-indigo-600">{task.project?.name}</Link>
                        <span>/</span>
                        <span className="text-gray-900 truncate max-w-[200px]">{task.title}</span>
                    </div>
                    <div className="flex items-center gap-2">
                        <Link
                            href={`/tasks/${task.id}/edit`}
                            className="flex items-center gap-1.5 px-3 py-1.5 text-sm border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50"
                        >
                            <Edit size={13} /> Edit
                        </Link>
                        <button
                            onClick={() => {
                                if (confirm('Delete this task? This cannot be undone.')) {
                                    router.delete(`/tasks/${task.id}`);
                                }
                            }}
                            className="flex items-center gap-1.5 px-3 py-1.5 text-sm border border-red-200 rounded-lg text-red-600 hover:bg-red-50"
                        >
                            <Trash2 size={13} /> Delete
                        </button>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Main content */}
                    <div className="lg:col-span-2 space-y-4">
                        <div className="bg-white rounded-xl border border-gray-200 p-6">
                            <h1 className="text-xl font-bold text-gray-900 mb-2">{task.title}</h1>
                            {task.description && (
                                <p className="text-sm text-gray-600 leading-relaxed mb-4">{task.description}</p>
                            )}
                            <div className="flex items-center gap-2 flex-wrap">
                                <span className={cn('px-2.5 py-1 rounded-full text-xs font-medium capitalize', TASK_STATUS_COLORS[task.status])}>
                                    {task.status.replace('_', ' ')}
                                </span>
                                <span className={cn('px-2.5 py-1 rounded-full text-xs font-medium capitalize', PRIORITY_COLORS[task.priority])}>
                                    {task.priority} priority
                                </span>
                                {task.tags?.map((tag) => (
                                    <span key={tag} className="px-2 py-0.5 bg-gray-100 text-gray-600 rounded-full text-xs">#{tag}</span>
                                ))}
                            </div>
                        </div>

                        {/* Tabs */}
                        <div className="bg-white rounded-xl border border-gray-200">
                            <div className="flex border-b border-gray-200">
                                {[
                                    { key: 'comments', label: 'Comments', icon: MessageSquare, count: task.comments.length },
                                    { key: 'attachments', label: 'Files', icon: Paperclip, count: task.attachments.length },
                                    { key: 'time', label: 'Time', icon: Clock, count: null },
                                ].map(({ key, label, icon: Icon, count }) => (
                                    <button
                                        key={key}
                                        onClick={() => setActiveTab(key as any)}
                                        className={cn(
                                            'flex items-center gap-2 px-5 py-3 text-sm font-medium border-b-2 transition-colors',
                                            activeTab === key
                                                ? 'border-indigo-600 text-indigo-600'
                                                : 'border-transparent text-gray-500 hover:text-gray-700',
                                        )}
                                    >
                                        <Icon size={15} /> {label}
                                        {count !== null && count > 0 && (
                                            <span className="px-1.5 py-0.5 bg-gray-100 text-gray-600 text-xs rounded-full">{count}</span>
                                        )}
                                    </button>
                                ))}
                            </div>

                            <div className="p-5">
                                {activeTab === 'comments' && (
                                    <div className="space-y-4">
                                        {task.comments.length === 0 && (
                                            <p className="text-sm text-gray-500 text-center py-4">No comments yet.</p>
                                        )}
                                        {task.comments.map((comment) => (
                                            <div key={comment.id} className="flex gap-3">
                                                <div className="w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 text-xs flex items-center justify-center font-semibold shrink-0">
                                                    {comment.user?.name?.[0] ?? '?'}
                                                </div>
                                                <div className="flex-1 bg-gray-50 rounded-lg p-3">
                                                    <div className="flex items-center gap-2 mb-1">
                                                        <span className="text-sm font-medium text-gray-900">{comment.user?.name}</span>
                                                        <span className="text-xs text-gray-400">{timeAgo(comment.created_at)}</span>
                                                        <button
                                                            onClick={() => {
                                                                if (confirm('Delete this comment?')) {
                                                                    router.delete(`/tasks/${task.id}/comments/${comment.id}`, { preserveScroll: true });
                                                                }
                                                            }}
                                                            className="ml-auto p-1 text-gray-300 hover:text-red-500 transition-colors rounded"
                                                        >
                                                            <Trash2 size={12} />
                                                        </button>
                                                    </div>
                                                    <p className="text-sm text-gray-700">{comment.body}</p>
                                                </div>
                                            </div>
                                        ))}
                                        <form onSubmit={submitComment} className="flex gap-3">
                                            <div className="flex-1">
                                                <textarea
                                                    value={commentForm.data.body}
                                                    onChange={(e) => commentForm.setData('body', e.target.value)}
                                                    rows={2}
                                                    placeholder="Add a comment…"
                                                    className="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
                                                />
                                            </div>
                                            <button
                                                type="submit"
                                                disabled={!commentForm.data.body || commentForm.processing}
                                                className="self-end p-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50"
                                            >
                                                <Send size={16} />
                                            </button>
                                        </form>
                                    </div>
                                )}

                                {activeTab === 'attachments' && (
                                    <div className="space-y-3">
                                        <input ref={fileRef} type="file" className="hidden" onChange={uploadFile} />
                                        <button
                                            onClick={() => fileRef.current?.click()}
                                            className="flex items-center gap-2 w-full px-4 py-3 border-2 border-dashed border-gray-200 rounded-lg text-sm text-gray-500 hover:border-indigo-300 hover:text-indigo-600 transition-colors"
                                        >
                                            <Upload size={16} /> Click to upload a file
                                        </button>
                                        {task.attachments.length === 0 && (
                                            <p className="text-sm text-gray-500 text-center py-2">No files attached.</p>
                                        )}
                                        {task.attachments.map((att) => (
                                            <div key={att.id} className="flex items-center gap-3 p-3 bg-gray-50 rounded-lg group">
                                                <Paperclip size={16} className="text-gray-400 shrink-0" />
                                                <div className="flex-1 min-w-0">
                                                    <a href={att.url} target="_blank" rel="noreferrer" className="text-sm font-medium text-indigo-600 hover:underline truncate block">
                                                        {att.original_filename}
                                                    </a>
                                                    <p className="text-xs text-gray-400">{(att.size / 1024).toFixed(1)} KB · {timeAgo(att.created_at)}</p>
                                                </div>
                                                <button
                                                    onClick={() => {
                                                        if (confirm('Delete this attachment?')) {
                                                            router.delete(`/attachments/${att.id}`, { preserveScroll: true });
                                                        }
                                                    }}
                                                    className="p-1.5 text-gray-300 hover:text-red-500 transition-colors rounded opacity-0 group-hover:opacity-100 shrink-0"
                                                >
                                                    <Trash2 size={14} />
                                                </button>
                                            </div>
                                        ))}
                                    </div>
                                )}

                                {activeTab === 'time' && (
                                    <div className="space-y-4">
                                        {/* Timer */}
                                        <div className="flex items-center gap-4 p-4 bg-gray-50 rounded-lg">
                                            <button
                                                onClick={toggleTimer}
                                                className={cn('flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium', timerRunning ? 'bg-red-500 text-white hover:bg-red-600' : 'bg-indigo-600 text-white hover:bg-indigo-700')}
                                            >
                                                {timerRunning ? <><Square size={14} /> Stop</> : <><Play size={14} /> Start Timer</>}
                                            </button>
                                            <span className="font-mono text-lg font-bold text-gray-900">{formatTimer(timerSeconds)}</span>
                                            {!timerRunning && <p className="text-xs text-gray-500">Stop to auto-fill the form below</p>}
                                        </div>

                                        {/* Manual log form */}
                                        <form onSubmit={submitTime} className="grid grid-cols-3 gap-3">
                                            <div>
                                                <label className="block text-xs font-medium text-gray-600 mb-1">Hours</label>
                                                <input
                                                    type="number"
                                                    step="0.25"
                                                    min="0.25"
                                                    value={timeForm.data.hours}
                                                    onChange={(e) => timeForm.setData('hours', e.target.value)}
                                                    className="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                                    placeholder="1.5"
                                                    required
                                                />
                                            </div>
                                            <div>
                                                <label className="block text-xs font-medium text-gray-600 mb-1">Date</label>
                                                <input
                                                    type="date"
                                                    value={timeForm.data.logged_date}
                                                    onChange={(e) => timeForm.setData('logged_date', e.target.value)}
                                                    className="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                                />
                                            </div>
                                            <div>
                                                <label className="block text-xs font-medium text-gray-600 mb-1">&nbsp;</label>
                                                <button type="submit" disabled={timeForm.processing} className="w-full py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                                                    Log Time
                                                </button>
                                            </div>
                                            <div className="col-span-3">
                                                <input
                                                    value={timeForm.data.description}
                                                    onChange={(e) => timeForm.setData('description', e.target.value)}
                                                    className="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                                    placeholder="What did you work on?"
                                                />
                                            </div>
                                        </form>

                                        <div className="border-t border-gray-100 pt-3">
                                            <p className="text-xs font-medium text-gray-500 mb-2">LOGGED ({formatHours(totalLogged)} total)</p>
                                            {task.time_entries.map((entry) => (
                                                <div key={entry.id} className="flex justify-between items-center py-2 border-b border-gray-50 group">
                                                    <div>
                                                        <p className="text-sm text-gray-700">{entry.description || '—'}</p>
                                                        <p className="text-xs text-gray-400">{entry.user?.name} · {formatDate(entry.logged_date)}</p>
                                                    </div>
                                                    <div className="flex items-center gap-2">
                                                        <span className="text-sm font-medium text-gray-900">{formatHours(entry.hours)}</span>
                                                        <button
                                                            onClick={() => {
                                                                if (confirm('Delete this time entry?')) {
                                                                    router.delete(`/tasks/${task.id}/time-entries/${entry.id}`, { preserveScroll: true });
                                                                }
                                                            }}
                                                            className="p-1 text-gray-300 hover:text-red-500 transition-colors rounded opacity-0 group-hover:opacity-100"
                                                        >
                                                            <Trash2 size={12} />
                                                        </button>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-4">
                        <div className="bg-white rounded-xl border border-gray-200 p-4">
                            <h3 className="text-sm font-semibold text-gray-900 mb-3">Details</h3>
                            <dl className="space-y-2.5 text-sm">
                                <div>
                                    <dt className="text-xs text-gray-500 mb-0.5">Status</dt>
                                    <select
                                        value={task.status}
                                        onChange={(e) => updateStatus(e.target.value)}
                                        className="w-full px-2 py-1.5 text-xs border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    >
                                        {['backlog','todo','in_progress','in_review','blocked','done','cancelled'].map((s) => (
                                            <option key={s} value={s}>{s.replace('_', ' ')}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <dt className="text-xs text-gray-500 mb-0.5">Assignee</dt>
                                    <dd className="font-medium text-gray-900">{task.assignee?.name ?? 'Unassigned'}</dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-gray-500 mb-0.5">Due Date</dt>
                                    <dd className="font-medium text-gray-900">{formatDate(task.due_date)}</dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-gray-500 mb-0.5">Estimated</dt>
                                    <dd className="font-medium text-gray-900">{task.estimated_hours > 0 ? formatHours(task.estimated_hours) : '—'}</dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-gray-500 mb-0.5">Logged</dt>
                                    <dd className="font-medium text-gray-900">{formatHours(totalLogged)}</dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-gray-500 mb-0.5">Created</dt>
                                    <dd className="text-gray-600">{formatDate(task.created_at)}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

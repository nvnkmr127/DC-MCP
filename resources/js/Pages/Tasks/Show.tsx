import React, { useState, useRef } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useConfirm } from '@/hooks/useConfirm';
import { cn, formatDate, timeAgo, formatHours } from '@/lib/utils';
import type { Task, Attachment, TimeEntry } from '@/types';
import {
    MessageSquare, Paperclip, Clock,
    Upload, Trash2, Edit, RefreshCw, XCircle,
    GitFork, AlertCircle, Activity,
} from 'lucide-react';
import { CommentsSection } from './Partials/CommentsSection';
import { TimeTracker } from './Partials/TimeTracker';
import { DependenciesSection, TaskDep } from './Partials/DependenciesSection';
import { ActivityLog } from '@/Components/Shared/ActivityLog';
import { StatusBadge } from '@/Components/Shared/StatusBadge';
import { Breadcrumbs } from '@/Components/Shared/Breadcrumbs';

interface Props {
    task: Task & {
        comments: any[];
        attachments: Attachment[];
        time_entries: TimeEntry[];
        dependencies?: TaskDep[];
        activities: any[];
    };
    projectTasks?: TaskDep[];
}

export default function TaskShow({ task, projectTasks = [] }: Props) {
    const [activeTab, setActiveTab] = useState<'comments' | 'attachments' | 'time' | 'dependencies' | 'activities'>('comments');
    const fileRef = useRef<HTMLInputElement>(null);

    const confirm = useConfirm();

    const [uploading, setUploading] = useState(false);
    const [uploadError, setUploadError] = useState<string | null>(null);
    const [pendingFile, setPendingFile] = useState<File | null>(null);

    function handleFileChange(e: React.ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0];
        if (!file) return;
        setPendingFile(file);
        setUploadError(null);
        performUpload(file);
    }

    function performUpload(file: File) {
        const form = new FormData();
        form.append('file', file);
        form.append('attachable_type', 'task');
        form.append('attachable_id', task.id);

        setUploading(true);
        setUploadError(null);

        router.post('/attachments', form, {
            preserveScroll: true,
            onSuccess: () => {
                setPendingFile(null);
                setUploading(false);
                if (fileRef.current) fileRef.current.value = '';
            },
            onError: (errors) => {
                setUploading(false);
                setUploadError(errors.file || 'Failed to upload file to S3. Please try again.');
            },
            onFinish: () => {
                setUploading(false);
            }
        });
    }

    function retryUpload() {
        if (pendingFile) performUpload(pendingFile);
    }

    function cancelUpload() {
        setPendingFile(null);
        setUploadError(null);
        if (fileRef.current) fileRef.current.value = '';
    }

    const [skipTimeCheck, setSkipTimeCheck] = useState(false);
    const [optimisticStatus, setOptimisticStatus] = useState(task.status);

    React.useEffect(() => {
        setOptimisticStatus(task.status);
    }, [task.status]);

    async function updateStatus(status: string) {
        if (status === 'done' && task.time_entries.length === 0 && !skipTimeCheck) {
            const ok = await confirm({
                title: 'No Time Logged',
                description: 'You are marking this task as done, but no time has been logged. Do you want to proceed anyway?',
                confirmText: 'Mark Done Anyway',
                cancelText: 'Cancel & Log Time',
            });
            if (ok) {
                setSkipTimeCheck(true);
                setOptimisticStatus(status);
                router.patch(`/tasks/${task.id}`, { status }, { 
                    preserveScroll: true, 
                    onSuccess: () => setSkipTimeCheck(false),
                    onError: () => setOptimisticStatus(task.status)
                });
            } else {
                setActiveTab('time');
            }
            return;
        }
        
        setOptimisticStatus(status);
        router.patch(`/tasks/${task.id}`, { status }, { 
            preserveScroll: true,
            onSuccess: () => setSkipTimeCheck(false),
            onError: () => setOptimisticStatus(task.status)
        });
    }

    const totalLogged = task.time_entries.reduce((s, e) => s + e.hours, 0);
    const dependencies = task.dependencies ?? [];
    const blockers = dependencies.filter(d => d.status !== 'done' && d.status !== 'cancelled');
    const isBlocked = blockers.length > 0;

    return (
        <AppLayout>
            <Head title={task.title} />

            <div className="max-w-5xl mx-auto">
                {/* Blocked banner */}
                {isBlocked && (
                    <div className="mb-4 flex items-center gap-2.5 bg-rose-50 border border-rose-200 rounded-xl px-4 py-3">
                        <AlertCircle size={16} className="text-rose-500 shrink-0" />
                        <p className="text-sm text-rose-700 font-medium flex-1">
                            This task is blocked by {blockers.length} unfinished {blockers.length === 1 ? 'dependency' : 'dependencies'}:{' '}
                            {blockers.map((b, i) => (
                                <span key={b.id}>
                                    <Link href={`/tasks/${b.id}`} className="underline hover:text-rose-900">{b.title}</Link>
                                    {i < blockers.length - 1 ? ', ' : ''}
                                </span>
                            ))}
                        </p>
                    </div>
                )}

                {/* Breadcrumb + actions */}
                <div className="flex items-center justify-between mb-4">
                    <Breadcrumbs items={
                        task.project_id ? [
                            { label: 'Projects', href: '/projects' },
                            { label: task.project?.name ?? 'Project', href: `/projects/${task.project_id}` },
                            { label: task.title }
                        ] : [
                            { label: 'Tasks', href: '/tasks' },
                            { label: task.title }
                        ]
                    } />
                    <div className="flex items-center gap-2">
                        <Link
                            href={`/tasks/${task.id}/edit`}
                            className="flex items-center gap-1.5 px-3 py-1.5 text-sm border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50"
                        >
                            <Edit size={13} /> Edit
                        </Link>
                        <button
                            type="button"
                            onClick={async () => {
                                const ok = await confirm({
                                    title: 'Delete this task?',
                                    description: 'This cannot be undone.',
                                    confirmText: 'Delete',
                                    variant: 'destructive',
                                });
                                if (!ok) return;
                                router.delete(`/tasks/${task.id}`);
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
                            <h1 className="text-xl font-bold text-gray-900 mb-3">{task.title}</h1>
                            {task.description && (
                                <p className="text-sm text-gray-600 leading-relaxed mb-4">{task.description}</p>
                            )}
                            <div className="flex items-center gap-2 flex-wrap">
                                <StatusBadge type="task-status" value={optimisticStatus} />
                                <StatusBadge type="task-priority" value={task.priority} />
                                {task.tags?.map((tag) => (
                                    <span key={tag} className="px-2 py-0.5 bg-gray-100 text-gray-700 rounded-full text-xs">#{tag}</span>
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
                                    { key: 'dependencies', label: 'Dependencies', icon: GitFork, count: dependencies.length || null },
                                    { key: 'activities', label: 'Activity', icon: Activity, count: task.activities.length },
                                ].map(({ key, label, icon: Icon, count }) => (
                                    <button
                                        key={key}
                                        type="button"
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
                                            <span className="px-1.5 py-0.5 bg-gray-100 text-gray-700 text-xs rounded-full">{count}</span>
                                        )}
                                    </button>
                                ))}
                            </div>

                            <div className="p-5">
                                {activeTab === 'comments' && (
                                    <CommentsSection 
                                        submitUrl={`/tasks/${task.id}/comments`}
                                        deleteUrlTemplate={(id) => `/tasks/${task.id}/comments/${id}`}
                                        comments={task.comments || []} 
                                    />
                                )}

                                {activeTab === 'attachments' && (
                                    <div className="space-y-3">
                                        <input ref={fileRef} type="file" className="hidden" onChange={handleFileChange} />
                                        
                                        {!pendingFile && (
                                            <button
                                                type="button"
                                                onClick={() => fileRef.current?.click()}
                                                className="flex items-center justify-center gap-2 w-full px-4 py-3 border-2 border-dashed border-gray-200 rounded-lg text-sm text-gray-500 hover:border-indigo-300 hover:text-indigo-600 transition-colors"
                                            >
                                                <Upload size={16} /> Click to upload a file
                                            </button>
                                        )}

                                        {pendingFile && (
                                            <div className="flex items-center gap-3 p-3 border border-gray-200 rounded-lg bg-gray-50">
                                                <Paperclip size={16} className="text-gray-400 shrink-0" />
                                                <div className="flex-1 min-w-0">
                                                    <p className="text-sm font-medium text-gray-900 truncate">{pendingFile.name}</p>
                                                    {uploading && <p className="text-xs text-indigo-600 mt-0.5">Uploading to S3...</p>}
                                                    {uploadError && <p className="text-xs text-red-600 mt-0.5">{uploadError}</p>}
                                                </div>
                                                
                                                {uploading && <RefreshCw size={16} className="animate-spin text-indigo-600 shrink-0" />}
                                                
                                                {uploadError && (
                                                    <div className="flex items-center gap-1">
                                                        <button 
                                                            type="button" 
                                                            onClick={retryUpload}
                                                            className="px-2 py-1 bg-red-100 hover:bg-red-200 text-red-700 text-xs font-semibold rounded"
                                                        >
                                                            Retry
                                                        </button>
                                                        <button 
                                                            type="button" 
                                                            onClick={cancelUpload}
                                                            className="p-1 text-gray-400 hover:text-gray-600"
                                                        >
                                                            <XCircle size={16} />
                                                        </button>
                                                    </div>
                                                )}
                                            </div>
                                        )}

                                        {task.attachments.length === 0 && !pendingFile && (
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
                                                    type="button"
                                                    onClick={async () => {
                                                        const ok = await confirm({
                                                            title: 'Delete this attachment?',
                                                            description: 'This cannot be undone.',
                                                            confirmText: 'Delete',
                                                            variant: 'destructive',
                                                        });
                                                        if (!ok) return;
                                                        router.delete(`/attachments/${att.id}`, { preserveScroll: true });
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
                                    <TimeTracker taskId={task.id} timeEntries={task.time_entries} />
                                )}

                                {activeTab === 'dependencies' && (
                                    <DependenciesSection taskId={task.id} dependencies={dependencies} projectTasks={projectTasks} />
                                )}

                                {activeTab === 'activities' && (
                                    <ActivityLog activities={task.activities} />
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
                                        value={optimisticStatus}
                                        onChange={(e) => updateStatus(e.target.value)}
                                        className="w-full px-2 py-1.5 text-xs border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white"
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

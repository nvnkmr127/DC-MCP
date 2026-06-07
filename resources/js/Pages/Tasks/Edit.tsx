import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { cn } from '@/lib/utils';
import type { Task } from '@/types';
import { ArrowLeft, Save } from 'lucide-react';

interface Props {
    task: Task;
    projects: Array<{ id: string; name: string }>;
    members:  Array<{ id: string; name: string }>;
}

const inputCls = 'w-full px-3.5 py-2.5 border border-gray-200 rounded-lg text-[13px] bg-gray-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all placeholder-gray-400';
const labelCls = 'block text-[12px] font-semibold text-gray-700 mb-1.5';

export default function TaskEdit({ task, projects, members }: Props) {
    const form = useForm({
        title:           task.title,
        description:     task.description ?? '',
        project_id:      task.project_id,
        status:          task.status,
        priority:        task.priority,
        assigned_to:     task.assigned_to ?? '',
        due_date:        task.due_date ?? '',
        estimated_hours: task.estimated_hours > 0 ? String(task.estimated_hours) : '',
        tags:            (task.tags ?? []).join(', '),
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.transform(data => ({
            ...data,
            tags: data.tags ? data.tags.split(',').map(t => t.trim()).filter(Boolean) : [],
        }));
        form.patch(`/tasks/${task.id}`);
    }

    return (
        <AppLayout title="Edit Task">
            <Head title={`Edit · ${task.title}`} />

            <div className="max-w-2xl mx-auto">
                {/* Header */}
                <div className="flex items-center gap-3 mb-6">
                    <Link
                        href={`/tasks/${task.id}`}
                        className="p-1.5 rounded-lg hover:bg-gray-100 text-gray-500 transition-colors"
                    >
                        <ArrowLeft size={17} />
                    </Link>
                    <div>
                        <h1 className="text-[15px] font-bold text-gray-900">Edit Task</h1>
                        <p className="text-[12px] text-gray-400 mt-0.5 line-clamp-1">{task.title}</p>
                    </div>
                </div>

                <div className="bg-white rounded-xl border border-gray-100 p-6 shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
                    <form onSubmit={submit} className="space-y-5">

                        {/* Title */}
                        <div>
                            <label className={labelCls}>Title <span className="text-red-400">*</span></label>
                            <input
                                type="text"
                                value={form.data.title}
                                onChange={e => form.setData('title', e.target.value)}
                                className={cn(inputCls, form.errors.title && 'border-red-300 bg-red-50')}
                                required
                                autoFocus
                            />
                            {form.errors.title && <p className="mt-1.5 text-[11px] text-red-500 font-medium">{form.errors.title}</p>}
                        </div>

                        {/* Description */}
                        <div>
                            <label className={labelCls}>Description</label>
                            <textarea
                                value={form.data.description}
                                onChange={e => form.setData('description', e.target.value)}
                                rows={4}
                                className={cn(inputCls, 'resize-none')}
                                placeholder="Describe what needs to be done…"
                            />
                        </div>

                        {/* Project + Status */}
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className={labelCls}>Project <span className="text-red-400">*</span></label>
                                <select
                                    value={form.data.project_id}
                                    onChange={e => form.setData('project_id', e.target.value)}
                                    className={inputCls}
                                    required
                                >
                                    <option value="">Select project…</option>
                                    {projects.map(p => (
                                        <option key={p.id} value={p.id}>{p.name}</option>
                                    ))}
                                </select>
                                {form.errors.project_id && <p className="mt-1.5 text-[11px] text-red-500 font-medium">{form.errors.project_id}</p>}
                            </div>
                            <div>
                                <label className={labelCls}>Status</label>
                                <select
                                    value={form.data.status}
                                    onChange={e => form.setData('status', e.target.value as any)}
                                    className={inputCls}
                                >
                                    {['backlog','todo','in_progress','in_review','blocked','done','cancelled'].map(s => (
                                        <option key={s} value={s}>{s.replace(/_/g, ' ')}</option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        {/* Priority + Assignee */}
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className={labelCls}>Priority</label>
                                <select
                                    value={form.data.priority}
                                    onChange={e => form.setData('priority', e.target.value as any)}
                                    className={inputCls}
                                >
                                    {['critical','high','medium','low'].map(p => (
                                        <option key={p} value={p} className="capitalize">{p}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className={labelCls}>Assignee</label>
                                <select
                                    value={form.data.assigned_to}
                                    onChange={e => form.setData('assigned_to', e.target.value)}
                                    className={inputCls}
                                >
                                    <option value="">Unassigned</option>
                                    {members.map(m => (
                                        <option key={m.id} value={m.id}>{m.name}</option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        {/* Due date + Estimated hours */}
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className={labelCls}>Due Date</label>
                                <input
                                    type="date"
                                    value={form.data.due_date}
                                    onChange={e => form.setData('due_date', e.target.value)}
                                    className={inputCls}
                                />
                            </div>
                            <div>
                                <label className={labelCls}>Estimated Hours</label>
                                <input
                                    type="number"
                                    step="0.5"
                                    min="0"
                                    value={form.data.estimated_hours}
                                    onChange={e => form.setData('estimated_hours', e.target.value)}
                                    className={inputCls}
                                    placeholder="e.g. 4"
                                />
                            </div>
                        </div>

                        {/* Tags */}
                        <div>
                            <label className={labelCls}>Tags <span className="text-gray-400 font-normal">(comma-separated)</span></label>
                            <input
                                type="text"
                                value={form.data.tags}
                                onChange={e => form.setData('tags', e.target.value)}
                                className={inputCls}
                                placeholder="design, backend, urgent"
                            />
                        </div>

                        {/* Actions */}
                        <div className="flex items-center gap-3 pt-2 border-t border-gray-100">
                            <button
                                type="submit"
                                disabled={form.processing}
                                className="flex items-center gap-1.5 px-5 py-2.5 bg-indigo-600 text-white text-[13px] font-semibold rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors shadow-sm"
                            >
                                <Save size={14} />
                                {form.processing ? 'Saving…' : 'Save Changes'}
                            </button>
                            <Link
                                href={`/tasks/${task.id}`}
                                className="px-4 py-2.5 text-[13px] text-gray-500 hover:text-gray-700 rounded-lg hover:bg-gray-100 transition-colors"
                            >
                                Cancel
                            </Link>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}

import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { ArrowLeft } from 'lucide-react';

interface Props {
    projects: Array<{ id: string; name: string }>;
    members:  Array<{ id: string; name: string }>;
    defaults: { project_id?: string; status?: string };
}

export default function TaskCreate({ projects, members, defaults }: Props) {
    const form = useForm({
        title:           '',
        description:     '',
        project_id:      defaults.project_id ?? '',
        status:          defaults.status ?? 'todo',
        priority:        'medium',
        assigned_to:     '',
        due_date:        '',
        estimated_hours: '',
        tags:            '',
        type:            'other',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.transform(data => ({
            ...data,
            tags: data.tags ? data.tags.split(',').map(t => t.trim()).filter(Boolean) : [],
        }));
        form.post('/tasks');
    }

    return (
        <AppLayout title="New Task">
            <Head title="New Task" />

            <div className="max-w-2xl mx-auto">
                <div className="flex items-center gap-3 mb-6">
                    <Link href="/tasks" className="p-2 rounded-lg hover:bg-gray-100 text-gray-500">
                        <ArrowLeft size={18} />
                    </Link>
                    <h1 className="text-lg font-bold text-gray-900">New Task</h1>
                </div>

                <div className="bg-white rounded-xl border border-gray-200 p-6">
                    <form onSubmit={submit} className="space-y-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                            <input
                                type="text"
                                value={form.data.title}
                                onChange={e => form.setData('title', e.target.value)}
                                className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                placeholder="Task title"
                                required
                                autoFocus
                            />
                            {form.errors.title && <p className="text-red-500 text-xs mt-1">{form.errors.title}</p>}
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea
                                value={form.data.description}
                                onChange={e => form.setData('description', e.target.value)}
                                rows={3}
                                className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
                                placeholder="Describe what needs to be done…"
                            />
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Project *</label>
                                <select
                                    value={form.data.project_id}
                                    onChange={e => form.setData('project_id', e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    required
                                >
                                    <option value="">Select project…</option>
                                    {projects.map(p => (
                                        <option key={p.id} value={p.id}>{p.name}</option>
                                    ))}
                                </select>
                                {form.errors.project_id && <p className="text-red-500 text-xs mt-1">{form.errors.project_id}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Type</label>
                                <select
                                    value={form.data.type}
                                    onChange={e => form.setData('type', e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                >
                                    {[
                                        { v: 'feature', l: 'Feature' },
                                        { v: 'bug', l: 'Bug' },
                                        { v: 'content', l: 'Content' },
                                        { v: 'design', l: 'Design' },
                                        { v: 'research', l: 'Research' },
                                        { v: 'review', l: 'Review' },
                                        { v: 'meeting', l: 'Meeting' },
                                        { v: 'report', l: 'Report' },
                                        { v: 'campaign_setup', l: 'Campaign Setup' },
                                        { v: 'ad_creative', l: 'Ad Creative' },
                                        { v: 'seo_audit', l: 'SEO Audit' },
                                        { v: 'email_sequence', l: 'Email Sequence' },
                                        { v: 'other', l: 'Other' },
                                    ].map(t => (
                                        <option key={t.v} value={t.v}>{t.l}</option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                <select
                                    value={form.data.status}
                                    onChange={e => form.setData('status', e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                >
                                    {['backlog','todo','in_progress','in_review','blocked'].map(s => (
                                        <option key={s} value={s}>{s.replace('_', ' ')}</option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                                <select
                                    value={form.data.priority}
                                    onChange={e => form.setData('priority', e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                >
                                    {['critical','high','medium','low'].map(p => (
                                        <option key={p} value={p} className="capitalize">{p}</option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Assignee</label>
                                <select
                                    value={form.data.assigned_to}
                                    onChange={e => form.setData('assigned_to', e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                >
                                    <option value="">Unassigned</option>
                                    {members.map(m => (
                                        <option key={m.id} value={m.id}>{m.name}</option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Due Date</label>
                                <input
                                    type="date"
                                    value={form.data.due_date}
                                    onChange={e => form.setData('due_date', e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                />
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Estimated Hours</label>
                                <input
                                    type="number"
                                    step="0.5"
                                    min="0"
                                    value={form.data.estimated_hours}
                                    onChange={e => form.setData('estimated_hours', e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    placeholder="e.g. 4"
                                />
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Tags</label>
                                <input
                                    type="text"
                                    value={form.data.tags}
                                    onChange={e => form.setData('tags', e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    placeholder="design, backend, urgent"
                                />
                            </div>
                        </div>

                        <div className="flex gap-3 pt-2">
                            <button
                                type="submit"
                                disabled={form.processing}
                                className="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50"
                            >
                                Create Task
                            </button>
                            <Link href="/tasks" className="px-4 py-2 text-sm text-gray-600 rounded-lg hover:bg-gray-100">
                                Cancel
                            </Link>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}

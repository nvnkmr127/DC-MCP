import React from 'react';
import { Button } from '@/Components/ui/Button';
import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useUnsavedChanges } from '@/hooks/useUnsavedChanges';
import { cn } from '@/lib/utils';
import type { Project, Client } from '@/types';
import { ArrowLeft, Save } from 'lucide-react';

interface Props {
    project: Project;
    clients: Array<{ id: string; name: string }>;
    members: Array<{ id: string; name: string }>;
}

const inputCls = 'w-full px-3.5 py-2.5 border border-gray-200 rounded-lg text-[13px] bg-gray-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all placeholder-gray-400';
const labelCls = 'block text-[12px] font-semibold text-gray-700 mb-1.5';

export default function ProjectEdit({ project, clients, members }: Props) {
    const form = useForm({
        name:               project.name,
        description:        project.description ?? '',
        client_id:          project.client_id ?? '',
        status:             project.status,
        priority:           project.priority,
        start_date:         project.start_date ?? '',
        end_date:           project.end_date ?? '',
        budget:             project.budget > 0 ? String(project.budget) : '',
        project_manager_id: project.project_manager_id ?? '',
        type:               project.type ?? 'seo',
        tags:               (project.tags ?? []).join(', '),
    });

    useUnsavedChanges(form.isDirty);

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.transform(data => ({
            ...data,
            tags: data.tags ? data.tags.split(',').map(t => t.trim()).filter(Boolean) : [],
        }));
        form.patch(`/projects/${project.id}`);
    }

    return (
        <AppLayout title="Edit Project">
            <Head title={`Edit · ${project.name}`} />

            <div className="max-w-2xl mx-auto">
                {/* Header */}
                <div className="flex items-center gap-3 mb-6">
                    <Link
                        href={`/projects/${project.id}`}
                        className="p-1.5 rounded-lg hover:bg-gray-100 text-gray-700 transition-colors"
                    >
                        <ArrowLeft size={17} />
                    </Link>
                    <div>
                        <h1 className="text-[15px] font-bold text-gray-900">Edit Project</h1>
                        <p className="text-[12px] text-gray-400 mt-0.5">{project.name}</p>
                    </div>
                </div>

                <div className="bg-white rounded-xl border border-gray-100 p-6 shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
                    <form onSubmit={submit} className="space-y-5">

                        {/* Name */}
                        <div>
                            <label className={labelCls}>Project Name <span className="text-red-400">*</span></label>
                            <input
                                type="text"
                                value={form.data.name}
                                onChange={e => form.setData('name', e.target.value)}
                                className={cn(inputCls, form.errors.name && 'border-red-300 bg-red-50')}
                                required
                                autoFocus
                            />
                            {form.errors.name && <p className="mt-1.5 text-[11px] text-red-500 font-medium">{form.errors.name}</p>}
                        </div>

                        {/* Description */}
                        <div>
                            <label className={labelCls}>Description</label>
                            <textarea
                                value={form.data.description}
                                onChange={e => form.setData('description', e.target.value)}
                                rows={3}
                                className={cn(inputCls, 'resize-none')}
                                placeholder="Brief project overview…"
                            />
                        </div>

                        {/* Client + Type */}
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className={labelCls}>Client</label>
                                <select
                                    value={form.data.client_id}
                                    onChange={e => form.setData('client_id', e.target.value)}
                                    className={inputCls}
                                >
                                    <option value="">No client (internal)</option>
                                    {clients.map(c => (
                                        <option key={c.id} value={c.id}>{c.name}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className={labelCls}>Type</label>
                                <select
                                    value={form.data.type}
                                    onChange={e => form.setData('type', e.target.value)}
                                    className={inputCls}
                                >
                                    {[
                                        { v: 'seo', l: 'SEO' },
                                        { v: 'social_media', l: 'Social Media' },
                                        { v: 'performance_ads', l: 'Performance Ads' },
                                        { v: 'web_dev', l: 'Web Dev' },
                                        { v: 'app_dev', l: 'App Dev' },
                                        { v: 'content', l: 'Content' },
                                        { v: 'brand', l: 'Brand' },
                                        { v: 'whatsapp', l: 'WhatsApp' },
                                        { v: 'email_marketing', l: 'Email Marketing' },
                                        { v: 'ecommerce', l: 'E-commerce' },
                                    ].map(t => (
                                        <option key={t.v} value={t.v}>{t.l}</option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        {/* Status + Priority */}
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className={labelCls}>Status</label>
                                <select
                                    value={form.data.status}
                                    onChange={e => form.setData('status', e.target.value as any)}
                                    className={inputCls}
                                >
                                    {['draft','planning','active','on_hold','completed','cancelled'].map(s => (
                                        <option key={s} value={s}>{s.replace(/_/g, ' ')}</option>
                                    ))}
                                </select>
                            </div>
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
                        </div>

                        {/* Dates */}
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className={labelCls}>Start Date</label>
                                <input
                                    type="date"
                                    value={form.data.start_date}
                                    onChange={e => form.setData('start_date', e.target.value)}
                                    className={inputCls}
                                />
                            </div>
                            <div>
                                <label className={labelCls}>End Date</label>
                                <input
                                    type="date"
                                    value={form.data.end_date}
                                    onChange={e => form.setData('end_date', e.target.value)}
                                    className={inputCls}
                                />
                                {form.errors.end_date && <p className="mt-1.5 text-[11px] text-red-500 font-medium">{form.errors.end_date}</p>}
                            </div>
                        </div>

                        {/* Budget + Manager */}
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className={labelCls}>Budget (₹)</label>
                                <input
                                    type="number"
                                    min="0"
                                    step="1000"
                                    value={form.data.budget}
                                    onChange={e => form.setData('budget', e.target.value)}
                                    className={inputCls}
                                    placeholder="0"
                                />
                            </div>
                            <div>
                                <label className={labelCls}>Project Manager</label>
                                <select
                                    value={form.data.project_manager_id}
                                    onChange={e => form.setData('project_manager_id', e.target.value)}
                                    className={inputCls}
                                >
                                    <option value="">Unassigned</option>
                                    {members.map(m => (
                                        <option key={m.id} value={m.id}>{m.name}</option>
                                    ))}
                                </select>
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
                                placeholder="seo, q3-campaign, urgent"
                            />
                        </div>

                        {/* Actions */}
                        <div className="flex items-center gap-3 pt-2 border-t border-gray-100">
                            <Button
                                type="submit"
                                disabled={form.processing}
                                className="flex items-center gap-1.5 px-5 py-2.5 disabled:opacity-50" 
                            >
                                <Save size={14} />
                                {form.processing ? 'Saving…' : 'Save Changes'}
                            </Button>
                            <Link
                                href={`/projects/${project.id}`}
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

import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { ArrowLeft } from 'lucide-react';

interface Props {
    clients: Array<{ id: string; name: string }>;
    members: Array<{ id: string; name: string }>;
}

export default function ProjectCreate({ clients, members }: Props) {
    const form = useForm({
        name:               '',
        description:        '',
        client_id:          '',
        status:             'planning',
        priority:           'medium',
        start_date:         '',
        end_date:           '',
        budget:             '',
        project_manager_id: '',
        type:               'seo',
        tags:               '',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.transform(data => ({
            ...data,
            tags: data.tags ? data.tags.split(',').map(t => t.trim()).filter(Boolean) : [],
        }));
        form.post('/projects');
    }

    return (
        <AppLayout title="New Project">
            <Head title="New Project" />

            <div className="max-w-2xl mx-auto">
                <div className="flex items-center gap-3 mb-6">
                    <Link href="/projects" className="p-2 rounded-lg hover:bg-gray-100 text-gray-500">
                        <ArrowLeft size={18} />
                    </Link>
                    <h1 className="text-lg font-bold text-gray-900">New Project</h1>
                </div>

                <div className="bg-white rounded-xl border border-gray-200 p-6">
                    <form onSubmit={submit} className="space-y-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Project Name *</label>
                            <input
                                type="text"
                                value={form.data.name}
                                onChange={e => form.setData('name', e.target.value)}
                                className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                placeholder="e.g. Q3 SEO Campaign"
                                required
                                autoFocus
                            />
                            {form.errors.name && <p className="text-red-500 text-xs mt-1">{form.errors.name}</p>}
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea
                                value={form.data.description}
                                onChange={e => form.setData('description', e.target.value)}
                                rows={2}
                                className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
                                placeholder="Brief project overview…"
                            />
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Client</label>
                                <select
                                    value={form.data.client_id}
                                    onChange={e => form.setData('client_id', e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                >
                                    <option value="">No client (internal)</option>
                                    {clients.map(c => (
                                        <option key={c.id} value={c.id}>{c.name}</option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Type</label>
                                <select
                                    value={form.data.type}
                                    onChange={e => form.setData('type', e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
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

                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                <select
                                    value={form.data.status}
                                    onChange={e => form.setData('status', e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                >
                                    {['draft','planning','active','on_hold','completed','cancelled'].map(s => (
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
                                <label className="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                                <input
                                    type="date"
                                    value={form.data.start_date}
                                    onChange={e => form.setData('start_date', e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                                <input
                                    type="date"
                                    value={form.data.end_date}
                                    onChange={e => form.setData('end_date', e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                />
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Budget (₹)</label>
                                <input
                                    type="number"
                                    min="0"
                                    step="1000"
                                    value={form.data.budget}
                                    onChange={e => form.setData('budget', e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    placeholder="0"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Project Manager</label>
                                <select
                                    value={form.data.project_manager_id}
                                    onChange={e => form.setData('project_manager_id', e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                >
                                    <option value="">Assign later</option>
                                    {members.map(m => (
                                        <option key={m.id} value={m.id}>{m.name}</option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Tags <span className="text-gray-400 font-normal">(comma-separated)</span></label>
                            <input
                                type="text"
                                value={form.data.tags}
                                onChange={e => form.setData('tags', e.target.value)}
                                className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                placeholder="seo, q3-campaign, urgent"
                            />
                        </div>

                        <div className="flex gap-3 pt-2">
                            <button
                                type="submit"
                                disabled={form.processing}
                                className="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50"
                            >
                                {form.processing ? 'Creating…' : 'Create Project'}
                            </button>
                            <Link href="/projects" className="px-4 py-2 text-sm text-gray-600 rounded-lg hover:bg-gray-100">
                                Cancel
                            </Link>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}

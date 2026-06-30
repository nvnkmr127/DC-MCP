import React, { useState } from 'react';
import { Button } from '@/Components/ui/Button';
import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useUnsavedChanges } from '@/hooks/useUnsavedChanges';
import { ArrowLeft, LayoutTemplate, FilePlus2, CheckCircle2 } from 'lucide-react';
import { cn } from '@/lib/utils';

interface Props {
    clients: Array<{ id: string; name: string }>;
    members: Array<{ id: string; name: string }>;
    templates: Array<{ id: string; name: string; service_type: string; description: string }>;
    goals: Array<{ id: string; title: string }>;
    defaults?: {
        status?: string;
        project_id?: string;
        project_template_id?: string;
    };
}

export default function ProjectCreate({ clients, members, templates, goals, defaults }: Props) {
    const defaultTemplateId = defaults?.project_template_id || '';
    const [step, setStep] = useState<1 | 2>(defaultTemplateId ? 2 : 1);

    const defaultTemplate = templates.find(t => t.id === defaultTemplateId);

    const form = useForm({
        name:               defaultTemplate?.name || '',
        description:        defaultTemplate?.description || '',
        client_id:          '',
        goal_id:            '',
        status:             defaults?.status || 'planning',
        priority:           'medium',
        start_date:         '',
        end_date:           '',
        budget:             '',
        project_manager_id: '',
        type:               defaultTemplate?.service_type || 'seo',
        tags:               '',
        project_template_id: defaultTemplateId,
    });

    useUnsavedChanges(form.isDirty);

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.transform(data => ({
            ...data,
            tags: data.tags ? data.tags.split(',').map(t => t.trim()).filter(Boolean) : [],
        }));
        form.post('/projects');
    }

    function selectTemplate(templateId: string) {
        if (templateId) {
            const template = templates.find(t => t.id === templateId);
            if (template) {
                form.setData(data => ({
                    ...data,
                    project_template_id: templateId,
                    name: data.name || template.name,
                    type: template.service_type || data.type,
                    description: data.description || (template.description || '')
                }));
            }
        } else {
            form.setData('project_template_id', '');
        }
        setStep(2);
    }

    return (
        <AppLayout title="New Project">
            <Head title="New Project" />

            <div className="max-w-5xl mx-auto">
                <div className="flex items-center gap-3 mb-8">
                    {step === 2 ? (
                        <button onClick={() => setStep(1)} className="p-2 rounded-lg hover:bg-gray-100 text-gray-700 transition-colors">
                            <ArrowLeft size={20} />
                        </button>
                    ) : (
                        <Link href="/projects" className="p-2 rounded-lg hover:bg-gray-100 text-gray-700 transition-colors">
                            <ArrowLeft size={20} />
                        </Link>
                    )}
                    <div>
                        <h1 className="text-xl font-bold text-gray-900">New Project</h1>
                        <p className="text-sm text-gray-500 mt-0.5">
                            {step === 1 ? 'Step 1: Choose a template' : 'Step 2: Project details'}
                        </p>
                    </div>
                </div>

                {step === 1 && (
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                        {/* Start from scratch */}
                        <div 
                            onClick={() => selectTemplate('')}
                            className={cn(
                                "relative group cursor-pointer bg-white rounded-xl border-2 p-6 transition-all duration-200 hover:shadow-md flex flex-col items-start",
                                form.data.project_template_id === '' ? "border-indigo-600 ring-1 ring-indigo-600 shadow-sm" : "border-gray-100 hover:border-indigo-300"
                            )}
                        >
                            <div className="w-12 h-12 rounded-xl bg-gray-50 border border-gray-200 flex items-center justify-center mb-4 group-hover:scale-105 transition-transform">
                                <FilePlus2 className="text-gray-500" size={24} />
                            </div>
                            <h3 className="text-base font-bold text-gray-900 mb-1">Start from Scratch</h3>
                            <p className="text-sm text-gray-500">Create a blank project with no predefined tasks.</p>
                            {form.data.project_template_id === '' && (
                                <div className="absolute top-4 right-4 text-indigo-600">
                                    <CheckCircle2 size={20} className="fill-indigo-50" />
                                </div>
                            )}
                        </div>

                        {/* Templates */}
                        {templates?.map(t => (
                            <div 
                                key={t.id}
                                onClick={() => selectTemplate(t.id)}
                                className={cn(
                                    "relative group cursor-pointer bg-white rounded-xl border-2 p-6 transition-all duration-200 hover:shadow-md flex flex-col items-start",
                                    form.data.project_template_id === t.id ? "border-indigo-600 ring-1 ring-indigo-600 shadow-sm" : "border-gray-100 hover:border-indigo-300"
                                )}
                            >
                                <div className="w-12 h-12 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center mb-4 group-hover:scale-105 transition-transform text-indigo-600">
                                    <LayoutTemplate size={24} />
                                </div>
                                <h3 className="text-base font-bold text-gray-900 mb-1">{t.name}</h3>
                                {t.service_type && (
                                    <span className="inline-block mb-2 px-2 py-0.5 bg-gray-50 text-gray-600 rounded text-[11px] font-semibold border border-gray-200 uppercase tracking-wider">
                                        {t.service_type.replace('_', ' ')}
                                    </span>
                                )}
                                <p className="text-sm text-gray-500 line-clamp-3 mt-1">
                                    {t.description || 'Pre-configured project template with standard tasks.'}
                                </p>
                                
                                {form.data.project_template_id === t.id && (
                                    <div className="absolute top-4 right-4 text-indigo-600">
                                        <CheckCircle2 size={20} className="fill-indigo-50" />
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                )}

                {step === 2 && (
                    <div className="bg-white rounded-xl border border-gray-200 p-6 shadow-sm max-w-2xl">
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

                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
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
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Goal</label>
                                    <select
                                        value={form.data.goal_id}
                                        onChange={e => form.setData('goal_id', e.target.value)}
                                        className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    >
                                        <option value="">No goal aligned</option>
                                        {goals.map(g => (
                                            <option key={g.id} value={g.id}>{g.title}</option>
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

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                    <select
                                        value={form.data.status}
                                        onChange={e => form.setData('status', e.target.value)}
                                        className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    >
                                        {['draft','planning','active','on_hold','completed','cancelled'].map(s => (
                                            <option key={s} value={s} className="capitalize">{s.replace('_', ' ')}</option>
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

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
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

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
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

                            <div className="flex gap-3 pt-4 mt-2 border-t border-gray-100">
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                    className="px-5 disabled:opacity-50" 
                                >
                                    {form.processing ? 'Creating…' : 'Create Project'}
                                </Button>
                                <button 
                                    type="button"
                                    onClick={() => setStep(1)} 
                                    className="px-4 py-2 text-sm text-gray-600 rounded-lg hover:bg-gray-100 font-medium transition-colors"
                                >
                                    Back
                                </button>
                            </div>
                        </form>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

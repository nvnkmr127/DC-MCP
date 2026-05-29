import React, { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { cn } from '@/lib/utils';
import { Plus, X, Play, Trash2, Layers } from 'lucide-react';

interface TemplateTask { title: string; priority: string; offset_days: number; estimated_hours: number; }
interface Template { id: string; name: string; description: string | null; service_type: string | null; tasks: TemplateTask[]; }
interface Client { id: string; name: string; }
interface Props { templates: Template[]; clients: Client[]; }

function TemplateModal({ onClose }: { onClose: () => void }) {
    const form = useForm({ name: '', service_type: '', description: '' });
    const [tasks, setTasks] = useState([{ title: '', priority: 'medium', offset_days: '0', estimated_hours: '1' }]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const taskList = tasks.filter(t => t.title).map(t => ({
            title: t.title, priority: t.priority,
            offset_days: parseInt(t.offset_days || '0'), estimated_hours: parseFloat(t.estimated_hours || '1'),
        }));
        form.transform(d => ({ ...d, tasks: taskList }));
        form.post('/project-templates', { onSuccess: onClose });
    };

    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 space-y-4 max-h-[90vh] overflow-y-auto">
                <div className="flex items-center justify-between">
                    <h2 className="text-[15px] font-bold text-gray-900">New Template</h2>
                    <button onClick={onClose}><X size={16} className="text-gray-400" /></button>
                </div>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                        <div className="col-span-2">
                            <label className="text-xs text-gray-500 font-medium">Template Name *</label>
                            <input type="text" value={form.data.name} onChange={e => form.setData('name', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Service Type</label>
                            <input type="text" value={form.data.service_type} onChange={e => form.setData('service_type', e.target.value)}
                                placeholder="e.g. SEO, Social Media"
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Description</label>
                            <input type="text" value={form.data.description} onChange={e => form.setData('description', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                        </div>
                    </div>
                    <div>
                        <div className="flex items-center justify-between mb-1">
                            <label className="text-xs text-gray-500 font-medium">Tasks</label>
                            <button type="button" onClick={() => setTasks(t => [...t, { title: '', priority: 'medium', offset_days: '0', estimated_hours: '1' }])}
                                className="text-xs text-indigo-600 font-medium flex items-center gap-1"><Plus size={11} /> Add</button>
                        </div>
                        {tasks.map((task, i) => (
                            <div key={i} className="grid grid-cols-12 gap-1.5 mb-1.5 items-center">
                                <input type="text" placeholder="Task title" value={task.title}
                                    onChange={e => { const n = [...tasks]; n[i].title = e.target.value; setTasks(n); }}
                                    className="col-span-5 border border-gray-300 rounded-lg px-2 py-1.5 text-xs focus:ring-1 focus:ring-indigo-500" />
                                <select value={task.priority} onChange={e => { const n = [...tasks]; n[i].priority = e.target.value; setTasks(n); }}
                                    className="col-span-2 border border-gray-300 rounded-lg px-1.5 py-1.5 text-xs focus:ring-1 focus:ring-indigo-500">
                                    {['low', 'medium', 'high', 'critical'].map(p => <option key={p} value={p}>{p}</option>)}
                                </select>
                                <input type="number" placeholder="Day +" value={task.offset_days}
                                    onChange={e => { const n = [...tasks]; n[i].offset_days = e.target.value; setTasks(n); }}
                                    className="col-span-2 border border-gray-300 rounded-lg px-1.5 py-1.5 text-xs focus:ring-1 focus:ring-indigo-500" />
                                <input type="number" placeholder="Hrs" value={task.estimated_hours}
                                    onChange={e => { const n = [...tasks]; n[i].estimated_hours = e.target.value; setTasks(n); }}
                                    className="col-span-2 border border-gray-300 rounded-lg px-1.5 py-1.5 text-xs focus:ring-1 focus:ring-indigo-500" />
                                <button type="button" onClick={() => setTasks(t => t.filter((_, j) => j !== i))} className="col-span-1 text-gray-400 hover:text-rose-500 flex justify-center">
                                    <X size={13} />
                                </button>
                            </div>
                        ))}
                    </div>
                    <div className="flex justify-end gap-2 pt-1">
                        <button type="button" onClick={onClose} className="px-4 py-2 text-sm text-gray-600">Cancel</button>
                        <button type="submit" disabled={form.processing || !form.data.name}
                            className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                            {form.processing ? 'Creating…' : 'Create Template'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

function UseTemplateModal({ template, clients, onClose }: { template: Template; clients: Client[]; onClose: () => void }) {
    const form = useForm({ client_id: '', name: template.name, start_date: new Date().toISOString().slice(0, 10) });
    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 space-y-3">
                <div className="flex items-center justify-between">
                    <h2 className="text-[15px] font-bold text-gray-900">Use Template: {template.name}</h2>
                    <button onClick={onClose}><X size={16} className="text-gray-400" /></button>
                </div>
                <form onSubmit={e => { e.preventDefault(); form.post(`/project-templates/${template.id}/create-project`, { onSuccess: onClose }); }} className="space-y-3">
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Project Name *</label>
                        <input type="text" value={form.data.name} onChange={e => form.setData('name', e.target.value)}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Client *</label>
                        <select value={form.data.client_id} onChange={e => form.setData('client_id', e.target.value)}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                            <option value="">Select client…</option>
                            {clients.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Start Date *</label>
                        <input type="date" value={form.data.start_date} onChange={e => form.setData('start_date', e.target.value)}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div className="flex justify-end gap-2 pt-1">
                        <button type="button" onClick={onClose} className="px-4 py-2 text-sm text-gray-600">Cancel</button>
                        <button type="submit" disabled={form.processing || !form.data.client_id || !form.data.name}
                            className="px-4 py-2 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700 disabled:opacity-50">
                            {form.processing ? 'Creating…' : 'Create Project'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export default function ProjectTemplatesIndex({ templates, clients }: Props) {
    const [newOpen, setNewOpen] = useState(false);
    const [useTemplate, setUseTemplate] = useState<Template | null>(null);

    return (
        <AppLayout title="Project Templates">
            <Head title="Project Templates" />
            <div className="max-w-4xl space-y-5">
                <div className="flex items-center justify-between">
                    <h1 className="text-lg font-bold text-gray-900">Project Templates</h1>
                    <button onClick={() => setNewOpen(true)}
                        className="flex items-center gap-2 px-3 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                        <Plus size={14} /> New Template
                    </button>
                </div>

                <div className="grid grid-cols-2 gap-4">
                    {templates.length === 0 && (
                        <div className="col-span-2 bg-white rounded-xl border border-dashed border-gray-200 px-5 py-10 text-center">
                            <Layers size={24} className="text-gray-300 mx-auto mb-2" />
                            <p className="text-sm text-gray-400">No templates yet.</p>
                        </div>
                    )}
                    {templates.map(t => (
                        <div key={t.id} className="bg-white rounded-xl border border-gray-200 p-4">
                            <div className="flex items-start justify-between gap-2 mb-3">
                                <div>
                                    <p className="text-sm font-semibold text-gray-900">{t.name}</p>
                                    {t.service_type && (
                                        <span className="inline-block mt-0.5 px-1.5 py-0.5 bg-indigo-50 text-indigo-700 rounded text-[10px] font-medium">
                                            {t.service_type}
                                        </span>
                                    )}
                                    {t.description && <p className="text-xs text-gray-500 mt-1">{t.description}</p>}
                                </div>
                                <button onClick={() => { if (confirm('Delete template?')) router.delete(`/project-templates/${t.id}`); }}
                                    className="p-1 text-gray-400 hover:text-rose-500 shrink-0">
                                    <Trash2 size={13} />
                                </button>
                            </div>
                            <p className="text-xs text-gray-500 mb-3">{t.tasks.length} task{t.tasks.length !== 1 ? 's' : ''}</p>
                            {t.tasks.slice(0, 3).map((task, i) => (
                                <div key={i} className="flex items-center gap-1.5 text-xs text-gray-600 mb-1">
                                    <span className="w-1.5 h-1.5 rounded-full bg-gray-300 shrink-0" />
                                    {task.title}
                                    <span className="text-gray-400">(+{task.offset_days}d)</span>
                                </div>
                            ))}
                            {t.tasks.length > 3 && <p className="text-xs text-gray-400">+{t.tasks.length - 3} more tasks</p>}
                            <button onClick={() => setUseTemplate(t)}
                                className="mt-3 w-full flex items-center justify-center gap-1.5 px-3 py-2 bg-emerald-600 text-white text-xs font-semibold rounded-lg hover:bg-emerald-700">
                                <Play size={11} /> Use Template
                            </button>
                        </div>
                    ))}
                </div>
            </div>
            {newOpen && <TemplateModal onClose={() => setNewOpen(false)} />}
            {useTemplate && <UseTemplateModal template={useTemplate} clients={clients} onClose={() => setUseTemplate(null)} />}
        </AppLayout>
    );
}

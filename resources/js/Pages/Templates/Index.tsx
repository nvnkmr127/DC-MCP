import React, { useState } from 'react';
import { Button } from '@/Components/ui/Button';
import { Head, useForm, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Plus, X, Layers, Trash2, Play, RotateCcw, Pause } from 'lucide-react';
import { useConfirm } from '@/hooks/useConfirm';
import { cn } from '@/lib/utils';
import type { Client, User } from '@/types';

// Types for Project Templates
type TemplateTask = {
    title: string;
    priority: string;
    offset_days: number;
    estimated_hours: number;
};

type Template = {
    id: string;
    name: string;
    description: string | null;
    service_type: string | null;
    tasks: TemplateTask[];
    task_count: number;
};

// Types for Recurring Tasks
type RecurringRule = {
    id: string;
    title: string;
    description: string | null;
    frequency: 'daily' | 'weekly' | 'monthly' | 'quarterly';
    frequency_day: number | null;
    priority: string;
    role_required: string | null;
    estimated_hours: number | null;
    is_active: boolean;
    last_spawned_at: string | null;
    next_spawn_at: string | null;
    client: { id: string, name: string } | null;
    project: { id: string, name: string } | null;
};

interface Props {
    templates: Template[];
    rules: RecurringRule[];
    clients: Client[];
    team: User[];
}

// ---------------------------------------------------------
// Project Templates Components
// ---------------------------------------------------------
function ProjectTemplateModal({ onClose }: { onClose: () => void }) {
    const form = useForm({
        name: '',
        service_type: '',
        description: '',
        tasks: [] as TemplateTask[],
    });

    const addTask = () => {
        form.setData('tasks', [...form.data.tasks, { title: '', priority: 'medium', offset_days: 0, estimated_hours: 0 }]);
    };
    const updateTask = (index: number, field: keyof TemplateTask, value: any) => {
        const newTasks = [...form.data.tasks];
        newTasks[index] = { ...newTasks[index], [field]: value };
        form.setData('tasks', newTasks);
    };
    const removeTask = (index: number) => {
        form.setData('tasks', form.data.tasks.filter((_, i) => i !== index));
    };

    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] flex flex-col">
                <div className="flex items-center justify-between p-6 border-b border-gray-100">
                    <h2 className="text-lg font-bold text-gray-900">New Project Template</h2>
                    <Button onClick={onClose}><X size={20} className="text-gray-400" /></Button>
                </div>
                <div className="p-6 overflow-y-auto flex-1 space-y-5">
                    <div className="space-y-4">
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Template Name *</label>
                            <input type="text" value={form.data.name} onChange={e => form.setData('name', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" placeholder="e.g. Website Redesign" />
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="text-xs text-gray-500 font-medium">Service Type</label>
                                <input type="text" value={form.data.service_type} onChange={e => form.setData('service_type', e.target.value)}
                                    className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" placeholder="e.g. SEO, Web Dev" />
                            </div>
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Description</label>
                            <textarea value={form.data.description} onChange={e => form.setData('description', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 min-h-[80px]" />
                        </div>
                    </div>
                    
                    <div>
                        <div className="flex items-center justify-between mb-3">
                            <h3 className="text-sm font-semibold text-gray-900">Tasks</h3>
                            <Button type="button" onClick={addTask} className="flex items-center gap-1" variant="ghost" size="sm" >
                                <Plus size={12} /> Add Task
                            </Button>
                        </div>
                        {form.data.tasks.length === 0 ? (
                            <div className="p-6 bg-gray-50 rounded-lg border border-dashed border-gray-300 text-center text-sm text-gray-500">
                                No tasks added. Projects created from this template will be empty.
                            </div>
                        ) : (
                            <div className="space-y-3">
                                {form.data.tasks.map((task, idx) => (
                                    <div key={idx} className="flex items-start gap-2 bg-gray-50 p-3 rounded-lg border border-gray-200">
                                        <div className="flex-1 grid grid-cols-12 gap-3">
                                            <div className="col-span-5">
                                                <input type="text" value={task.title} onChange={e => updateTask(idx, 'title', e.target.value)} placeholder="Task title"
                                                    className="w-full border-gray-300 rounded px-2 py-1 text-xs" />
                                            </div>
                                            <div className="col-span-3">
                                                <select value={task.priority} onChange={e => updateTask(idx, 'priority', e.target.value)}
                                                    className="w-full border-gray-300 rounded px-2 py-1 text-xs">
                                                    <option value="low">Low</option>
                                                    <option value="medium">Medium</option>
                                                    <option value="high">High</option>
                                                    <option value="critical">Critical</option>
                                                </select>
                                            </div>
                                            <div className="col-span-2">
                                                <input type="number" min="0" value={task.offset_days} onChange={e => updateTask(idx, 'offset_days', parseInt(e.target.value)||0)} title="Days after project start"
                                                    className="w-full border-gray-300 rounded px-2 py-1 text-xs" placeholder="Days" />
                                            </div>
                                            <div className="col-span-2">
                                                <input type="number" step="0.5" min="0" value={task.estimated_hours} onChange={e => updateTask(idx, 'estimated_hours', parseFloat(e.target.value)||0)} title="Est. hours"
                                                    className="w-full border-gray-300 rounded px-2 py-1 text-xs" placeholder="Hrs" />
                                            </div>
                                        </div>
                                        <Button type="button" onClick={() => removeTask(idx)} className="p-1 text-gray-400 hover:text-rose-500">
                                            <Trash2 size={16} />
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
                <div className="p-6 border-t border-gray-100 flex justify-end gap-2 bg-gray-50/50 rounded-b-2xl">
                    <Button type="button" onClick={onClose} variant="ghost" >Cancel</Button>
                    <Button onClick={() => form.post('/project-templates', { onSuccess: onClose })} disabled={form.processing || !form.data.name}
                        className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                        {form.processing ? 'Creating…' : 'Create Template'}
                    </Button>
                </div>
            </div>
        </div>
    );
}

function UseProjectTemplateModal({ template, clients, onClose }: { template: Template; clients: Client[]; onClose: () => void }) {
    const form = useForm({ client_id: '', name: template.name, start_date: new Date().toISOString().slice(0, 10) });
    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 space-y-3">
                <div className="flex items-center justify-between">
                    <h2 className="text-[15px] font-bold text-gray-900">Use Template: {template.name}</h2>
                    <Button onClick={onClose}><X size={16} className="text-gray-400" /></Button>
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
                        <Button type="button" onClick={onClose} variant="ghost" >Cancel</Button>
                        <Button type="submit" disabled={form.processing || !form.data.client_id || !form.data.name}
                            className="disabled:opacity-50" variant="ghost" >
                            {form.processing ? 'Creating…' : 'Create Project'}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}

// ---------------------------------------------------------
// Recurring Tasks Components
// ---------------------------------------------------------
const FREQ_STYLES: Record<string, string> = {
    'daily': 'bg-blue-50 text-blue-700',
    'weekly': 'bg-emerald-50 text-emerald-700',
    'monthly': 'bg-purple-50 text-purple-700',
    'quarterly': 'bg-amber-50 text-amber-700',
};

const PRIORITY_STYLES: Record<string, string> = {
    'low': 'bg-gray-50 text-gray-600',
    'medium': 'bg-indigo-50 text-indigo-700',
    'high': 'bg-rose-50 text-rose-700',
    'critical': 'bg-rose-100 text-rose-800 ring-1 ring-inset ring-rose-300',
};

function AddRecurringRuleModal({ onClose }: { onClose: () => void }) {
    const form = useForm({
        title: '',
        description: '',
        frequency: 'weekly',
        priority: 'medium',
        role_required: '',
        estimated_hours: '',
    });

    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-[15px] font-bold text-gray-900">New Recurring Task</h2>
                    <Button onClick={onClose}><X size={16} className="text-gray-400" /></Button>
                </div>
                <form onSubmit={e => { e.preventDefault(); form.post('/recurring-tasks', { onSuccess: onClose }); }} className="space-y-3">
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Task Title *</label>
                        <input type="text" value={form.data.title} onChange={e => form.setData('title', e.target.value)}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Description</label>
                        <textarea value={form.data.description} onChange={e => form.setData('description', e.target.value)} rows={2}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Frequency *</label>
                            <select value={form.data.frequency} onChange={e => form.setData('frequency', e.target.value as any)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                                <option value="quarterly">Quarterly</option>
                            </select>
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Priority *</label>
                            <select value={form.data.priority} onChange={e => form.setData('priority', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Role Required</label>
                            <select value={form.data.role_required} onChange={e => form.setData('role_required', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                <option value="">Any</option>
                                {['ceo', 'project_manager', 'analyst', 'marketer', 'developer', 'designer', 'copywriter'].map(r =>
                                    <option key={r} value={r}>{r.replace('_', ' ')}</option>
                                )}
                            </select>
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Estimated Hours</label>
                            <input type="number" step="0.5" min="0" value={form.data.estimated_hours}
                                onChange={e => form.setData('estimated_hours', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                        </div>
                    </div>
                    <div className="flex justify-end gap-2 pt-2">
                        <Button type="button" onClick={onClose} variant="ghost" >Cancel</Button>
                        <Button type="submit" disabled={form.processing || !form.data.title}
                            className="disabled:opacity-50" >
                            {form.processing ? 'Adding…' : 'Add Rule'}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}


// ---------------------------------------------------------
// Main Unified Templates Page
// ---------------------------------------------------------
export default function TemplatesIndex({ templates, rules, clients, team }: Props) {
    const [activeTab, setActiveTab] = useState<'projects' | 'recurring'>('projects');
    
    // Modals state
    const [newTemplateOpen, setNewTemplateOpen] = useState(false);
    const [useTemplate, setUseTemplate] = useState<Template | null>(null);
    const [newRuleOpen, setNewRuleOpen] = useState(false);
    
    const confirm = useConfirm();

    return (
        <AppLayout title="Templates">
            <Head title="Templates" />
            <div className="max-w-5xl mx-auto space-y-6">
                
                {/* Header & Actions */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Templates Hub</h1>
                        <p className="text-sm text-gray-500 mt-0.5">Automate your repeating workflows and setups</p>
                    </div>
                    <div>
                        {activeTab === 'projects' ? (
                            <Button onClick={() => setNewTemplateOpen(true)}
                                className="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 shadow-sm transition-colors">
                                <Plus size={16} /> New Template
                            </Button>
                        ) : (
                            <Button onClick={() => setNewRuleOpen(true)}
                                className="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 shadow-sm transition-colors">
                                <Plus size={16} /> Add Rule
                            </Button>
                        )}
                    </div>
                </div>

                {/* Tabs Navigation */}
                <div className="flex border-b border-gray-200">
                    <Button
                        onClick={() => setActiveTab('projects')}
                        className={cn(
                            "pb-3 px-1 text-sm font-medium mr-8 border-b-2 transition-colors",
                            activeTab === 'projects' ? "border-indigo-600 text-indigo-600" : "border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300"
                        )}
                    >
                        Project Templates
                    </Button>
                    <Button
                        onClick={() => setActiveTab('recurring')}
                        className={cn(
                            "pb-3 px-1 text-sm font-medium border-b-2 transition-colors",
                            activeTab === 'recurring' ? "border-indigo-600 text-indigo-600" : "border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300"
                        )}
                    >
                        Recurring Tasks
                    </Button>
                </div>

                {/* Tab Content: Project Templates */}
                {activeTab === 'projects' && (
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {templates.length === 0 && (
                            <div className="col-span-1 md:col-span-2 bg-white rounded-xl border border-dashed border-gray-200 px-5 py-12 text-center">
                                <Layers size={32} className="text-gray-300 mx-auto mb-3" />
                                <h3 className="text-sm font-medium text-gray-900">No project templates</h3>
                                <p className="text-sm text-gray-400 mt-1">Create a template to stamp out full projects instantly.</p>
                            </div>
                        )}
                        {templates.map(t => (
                            <div key={t.id} className="bg-white rounded-xl border border-gray-200 p-5 shadow-sm hover:shadow-md transition-shadow">
                                <div className="flex items-start justify-between gap-2 mb-4">
                                    <div>
                                        <h3 className="text-base font-bold text-gray-900">{t.name}</h3>
                                        {t.service_type && (
                                            <span className="inline-block mt-1.5 px-2 py-0.5 bg-indigo-50 text-indigo-700 rounded text-xs font-medium border border-indigo-100">
                                                {t.service_type}
                                            </span>
                                        )}
                                        {t.description && <p className="text-sm text-gray-500 mt-2 line-clamp-2">{t.description}</p>}
                                    </div>
                                    <Button onClick={async () => {
                                        const ok = await confirm({
                                            title: 'Delete template?',
                                            description: 'This action cannot be undone.',
                                            confirmText: 'Delete',
                                            variant: 'destructive',
                                        });
                                        if (!ok) return;
                                        router.delete(`/project-templates/${t.id}`);
                                    }}
                                        className="p-1.5 rounded-md text-gray-400 hover:bg-rose-50 hover:text-rose-600 transition-colors shrink-0">
                                        <Trash2 size={16} />
                                    </Button>
                                </div>
                                
                                <div className="bg-gray-50 rounded-lg p-3 mb-4">
                                    <p className="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">
                                        {t.tasks.length} Task{t.tasks.length !== 1 ? 's' : ''}
                                    </p>
                                    <div className="space-y-1.5">
                                        {t.tasks.slice(0, 3).map((task, i) => (
                                            <div key={i} className="flex items-center gap-2 text-sm text-gray-700">
                                                <span className="w-1.5 h-1.5 rounded-full bg-indigo-300 shrink-0" />
                                                <span className="truncate">{task.title}</span>
                                                <span className="text-xs text-gray-400 ml-auto shrink-0">(+{task.offset_days}d)</span>
                                            </div>
                                        ))}
                                        {t.tasks.length > 3 && (
                                            <p className="text-xs text-gray-500 pl-3.5 pt-1 font-medium">
                                                +{t.tasks.length - 3} more tasks
                                            </p>
                                        )}
                                    </div>
                                </div>
                                
                                <Button onClick={() => setUseTemplate(t)}
                                    className="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-emerald-50 text-emerald-700 hover:bg-emerald-600 hover:text-white border border-emerald-200 hover:border-emerald-600 transition-colors text-sm font-semibold rounded-lg">
                                    <Play size={16} /> Use Template
                                </Button>
                            </div>
                        ))}
                    </div>
                )}

                {/* Tab Content: Recurring Tasks */}
                {activeTab === 'recurring' && (
                    <div className="space-y-3">
                        {rules.length === 0 && (
                            <div className="bg-white rounded-xl border border-dashed border-gray-200 py-12 text-center">
                                <RotateCcw className="w-10 h-10 text-gray-300 mx-auto mb-3" />
                                <h3 className="text-sm font-medium text-gray-900">No recurring task rules</h3>
                                <p className="text-sm text-gray-400 mt-1">Set up rules to auto-spawn tasks on a schedule.</p>
                            </div>
                        )}
                        {rules.map(rule => (
                            <div key={rule.id} className={cn('bg-white rounded-xl border p-5 shadow-sm transition-opacity', rule.is_active ? 'border-gray-200' : 'border-gray-200 bg-gray-50/50 opacity-75')}>
                                <div className="flex items-start justify-between gap-4">
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center gap-3 flex-wrap mb-1">
                                            <h3 className="text-base font-bold text-gray-900">{rule.title}</h3>
                                            <span className={cn('text-xs px-2.5 py-1 rounded-md font-semibold border', FREQ_STYLES[rule.frequency])}>
                                                {rule.frequency.toUpperCase()}
                                            </span>
                                            <span className={cn('text-xs px-2.5 py-1 rounded-md font-medium border border-transparent', PRIORITY_STYLES[rule.priority])}>
                                                {rule.priority}
                                            </span>
                                            {!rule.is_active && <span className="text-xs px-2 py-0.5 bg-gray-100 text-gray-500 rounded font-medium border border-gray-200">Paused</span>}
                                        </div>
                                        {rule.description && <p className="text-sm text-gray-500 mt-2">{rule.description}</p>}
                                        
                                        <div className="flex flex-wrap items-center gap-x-6 gap-y-2 mt-4 text-sm">
                                            {rule.role_required && (
                                                <div className="flex items-center gap-1.5 text-gray-500">
                                                    <span className="font-medium text-gray-400">Role:</span>
                                                    <span className="text-gray-700 capitalize">{rule.role_required.replace('_', ' ')}</span>
                                                </div>
                                            )}
                                            {rule.estimated_hours && (
                                                <div className="flex items-center gap-1.5 text-gray-500">
                                                    <span className="font-medium text-gray-400">Est:</span>
                                                    <span className="text-gray-700">{rule.estimated_hours}h</span>
                                                </div>
                                            )}
                                            <div className="flex items-center gap-1.5 text-gray-500">
                                                <span className="font-medium text-gray-400">Next spawn:</span>
                                                <span className="text-gray-700 font-medium">{rule.next_spawn_at || 'N/A'}</span>
                                            </div>
                                            {rule.client && (
                                                <div className="flex items-center gap-1.5 text-gray-500">
                                                    <span className="font-medium text-gray-400">Client:</span>
                                                    <span className="text-gray-700">{rule.client.name}</span>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2 shrink-0 border-l border-gray-100 pl-4 ml-2">
                                        <Button onClick={() => router.patch(`/recurring-tasks/${rule.id}`, { is_active: !rule.is_active }, { preserveScroll: true })}
                                            className={cn('p-2 rounded-lg border transition-colors flex items-center justify-center',
                                                rule.is_active
                                                    ? 'border-amber-200 text-amber-600 hover:bg-amber-50'
                                                    : 'border-emerald-200 text-emerald-600 hover:bg-emerald-50'
                                            )} title={rule.is_active ? 'Pause Rule' : 'Resume Rule'}>
                                            {rule.is_active ? <Pause size={20} /> : <Play size={20} />}
                                        </Button>
                                        <Button onClick={async () => {
                                            const ok = await confirm({
                                                title: 'Delete this rule?',
                                                description: 'This cannot be undone.',
                                                confirmText: 'Delete',
                                                variant: 'destructive',
                                            });
                                            if (!ok) return;
                                            router.delete(`/recurring-tasks/${rule.id}`, { preserveScroll: true });
                                        }}
                                            className="p-2 rounded-lg border border-gray-200 text-gray-400 hover:text-rose-500 hover:border-rose-200 hover:bg-rose-50 transition-colors flex items-center justify-center">
                                            <Trash2 size={20} />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {/* Modals */}
            {newTemplateOpen && <ProjectTemplateModal onClose={() => setNewTemplateOpen(false)} />}
            {useTemplate && <UseProjectTemplateModal template={useTemplate} clients={clients} onClose={() => setUseTemplate(null)} />}
            {newRuleOpen && <AddRecurringRuleModal onClose={() => setNewRuleOpen(false)} />}
        </AppLayout>
    );
}

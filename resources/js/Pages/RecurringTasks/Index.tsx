import React, { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { cn } from '@/lib/utils';
import { Plus, X, RotateCcw, Pause, Play, Trash2 } from 'lucide-react';

const FREQ_STYLES: Record<string, string> = {
    daily:     'bg-blue-100 text-blue-700',
    weekly:    'bg-indigo-100 text-indigo-700',
    monthly:   'bg-violet-100 text-violet-700',
    quarterly: 'bg-purple-100 text-purple-700',
};

const PRIORITY_STYLES: Record<string, string> = {
    low:      'bg-gray-100 text-gray-600',
    medium:   'bg-yellow-100 text-yellow-700',
    high:     'bg-orange-100 text-orange-700',
    critical: 'bg-rose-100 text-rose-700',
};

interface Rule {
    id: string; title: string; description: string | null;
    frequency: string; frequency_day: number | null;
    priority: string; role_required: string | null;
    estimated_hours: number | null; is_active: boolean;
    last_spawned_at: string | null; next_spawn_at: string | null;
    client: { id: string; name: string } | null;
    project: { id: string; name: string } | null;
}
interface Props {
    rules: Rule[];
    team: { id: string; name: string }[];
}

function AddRuleModal({ onClose }: { onClose: () => void }) {
    const form = useForm({
        title: '', description: '', frequency: 'weekly', frequency_day: '',
        priority: 'medium', role_required: '', estimated_hours: '',
    });

    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 space-y-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-bold text-gray-900">Add Recurring Rule</h2>
                    <button onClick={onClose}><X className="w-5 h-5 text-gray-400" /></button>
                </div>
                <form onSubmit={e => { e.preventDefault(); form.post('/recurring-tasks', { onSuccess: onClose }); }}
                    className="space-y-3">
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Task Title *</label>
                        <input type="text" value={form.data.title} onChange={e => form.setData('title', e.target.value)}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Description</label>
                        <textarea value={form.data.description} onChange={e => form.setData('description', e.target.value)} rows={2}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 resize-none" />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Frequency *</label>
                            <select value={form.data.frequency} onChange={e => form.setData('frequency', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                                <option value="quarterly">Quarterly</option>
                            </select>
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 font-medium">
                                {form.data.frequency === 'weekly' ? 'Day (0=Sun…6=Sat)' : form.data.frequency === 'monthly' ? 'Day of Month' : 'Skip'}
                            </label>
                            <input type="number" value={form.data.frequency_day}
                                onChange={e => form.setData('frequency_day', e.target.value)}
                                min={form.data.frequency === 'weekly' ? 0 : 1}
                                max={form.data.frequency === 'weekly' ? 6 : 28}
                                disabled={form.data.frequency === 'daily' || form.data.frequency === 'quarterly'}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 disabled:bg-gray-50" />
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Priority</label>
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
                    </div>
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Estimated Hours</label>
                        <input type="number" step="0.5" min="0" value={form.data.estimated_hours}
                            onChange={e => form.setData('estimated_hours', e.target.value)}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div className="flex justify-end gap-2 pt-2">
                        <button type="button" onClick={onClose} className="px-4 py-2 text-sm text-gray-600">Cancel</button>
                        <button type="submit" disabled={form.processing || !form.data.title}
                            className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                            {form.processing ? 'Adding…' : 'Add Rule'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export default function RecurringTasksIndex({ rules }: Props) {
    const [addOpen, setAddOpen] = useState(false);

    return (
        <AppLayout>
            <Head title="Recurring Tasks" />
            <div className="max-w-4xl mx-auto px-4 py-6 space-y-5">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Recurring Tasks</h1>
                        <p className="text-sm text-gray-500 mt-0.5">Auto-spawn tasks on a schedule</p>
                    </div>
                    <button onClick={() => setAddOpen(true)}
                        className="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                        <Plus className="w-4 h-4" /> Add Rule
                    </button>
                </div>

                {rules.length === 0 ? (
                    <div className="bg-white rounded-xl border border-gray-200 py-16 text-center">
                        <RotateCcw className="w-10 h-10 text-gray-200 mx-auto mb-3" />
                        <p className="text-sm text-gray-400">No recurring task rules yet.</p>
                        <button onClick={() => setAddOpen(true)} className="mt-2 text-sm text-indigo-600 font-medium">
                            Create the first rule →
                        </button>
                    </div>
                ) : (
                    <div className="space-y-3">
                        {rules.map(rule => (
                            <div key={rule.id} className={cn('bg-white rounded-xl border p-4', rule.is_active ? 'border-gray-200' : 'border-gray-100 opacity-60')}>
                                <div className="flex items-start justify-between gap-3">
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center gap-2 flex-wrap">
                                            <h3 className="font-semibold text-gray-900 text-sm">{rule.title}</h3>
                                            <span className={cn('text-xs px-2 py-0.5 rounded-full font-medium', FREQ_STYLES[rule.frequency])}>
                                                {rule.frequency}
                                            </span>
                                            <span className={cn('text-xs px-2 py-0.5 rounded-full font-medium', PRIORITY_STYLES[rule.priority])}>
                                                {rule.priority}
                                            </span>
                                            {!rule.is_active && <span className="text-xs text-gray-400 font-medium">Paused</span>}
                                        </div>
                                        {rule.description && <p className="text-xs text-gray-500 mt-1">{rule.description}</p>}
                                        <div className="flex items-center gap-4 mt-2 text-xs text-gray-400">
                                            {rule.role_required && <span>Role: <span className="text-gray-600">{rule.role_required.replace('_', ' ')}</span></span>}
                                            {rule.estimated_hours && <span>~{rule.estimated_hours}h</span>}
                                            {rule.last_spawned_at && <span>Last: {rule.last_spawned_at}</span>}
                                            {rule.next_spawn_at && <span>Next: {rule.next_spawn_at}</span>}
                                            {rule.client && <span>Client: {rule.client.name}</span>}
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-1 shrink-0">
                                        <button onClick={() => router.patch(`/recurring-tasks/${rule.id}`, { is_active: !rule.is_active })}
                                            className={cn('p-1.5 rounded-lg border transition-colors',
                                                rule.is_active
                                                    ? 'border-amber-200 text-amber-600 hover:bg-amber-50'
                                                    : 'border-emerald-200 text-emerald-600 hover:bg-emerald-50'
                                            )} title={rule.is_active ? 'Pause' : 'Resume'}>
                                            {rule.is_active ? <Pause className="w-3.5 h-3.5" /> : <Play className="w-3.5 h-3.5" />}
                                        </button>
                                        <button onClick={() => { if (confirm('Delete this rule?')) router.delete(`/recurring-tasks/${rule.id}`); }}
                                            className="p-1.5 rounded-lg border border-gray-200 text-gray-400 hover:text-rose-500 hover:border-rose-200 hover:bg-rose-50 transition-colors">
                                            <Trash2 className="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                {addOpen && <AddRuleModal onClose={() => setAddOpen(false)} />}
            </div>
        </AppLayout>
    );
}

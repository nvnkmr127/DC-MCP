import React, { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useConfirm } from '@/hooks/useConfirm';
import { cn } from '@/lib/utils';
import { Plus, X, ToggleLeft, ToggleRight, Trash2, Workflow } from 'lucide-react';

interface WorkflowTrigger {
    id: string; name: string; description: string | null; trigger_event: string;
    action_type: string; conditions: Record<string, string>; action_config: Record<string, string>;
    is_active: boolean; run_count: number; last_run_at: string | null;
}
interface Props { workflows: WorkflowTrigger[]; }

const TRIGGER_LABELS: Record<string, string> = {
    task_completed: 'Task Completed', invoice_sent: 'Invoice Sent', project_created: 'Project Created',
    client_added: 'Client Added', retainer_renewed: 'Retainer Renewed', proposal_accepted: 'Proposal Accepted',
};
const ACTION_LABELS: Record<string, string> = {
    send_notification: 'Send Notification', create_task: 'Create Task',
    send_email: 'Send Email', update_status: 'Update Status',
};
const TRIGGER_STYLES: Record<string, string> = {
    task_completed: 'bg-emerald-100 text-emerald-700', invoice_sent: 'bg-blue-100 text-blue-700',
    project_created: 'bg-indigo-100 text-indigo-700', client_added: 'bg-violet-100 text-violet-700',
    retainer_renewed: 'bg-amber-100 text-amber-700', proposal_accepted: 'bg-green-100 text-green-700',
};

function WorkflowModal({ onClose }: { onClose: () => void }) {
    const form = useForm({ name: '', description: '', trigger_event: 'task_completed', action_type: 'send_notification', is_active: true });
    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-3">
                <div className="flex items-center justify-between">
                    <h2 className="text-[15px] font-bold text-gray-900">New Workflow</h2>
                    <button onClick={onClose}><X size={16} className="text-gray-400" /></button>
                </div>
                <form onSubmit={e => { e.preventDefault(); form.post('/workflows', { onSuccess: onClose }); }} className="space-y-3">
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Workflow Name *</label>
                        <input type="text" value={form.data.name} onChange={e => form.setData('name', e.target.value)}
                            placeholder="e.g. Notify CEO on invoice sent"
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Description</label>
                        <textarea value={form.data.description} onChange={e => form.setData('description', e.target.value)} rows={2}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 resize-none" />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Trigger Event</label>
                            <select value={form.data.trigger_event} onChange={e => form.setData('trigger_event', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                {Object.entries(TRIGGER_LABELS).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Action</label>
                            <select value={form.data.action_type} onChange={e => form.setData('action_type', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                {Object.entries(ACTION_LABELS).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                            </select>
                        </div>
                    </div>
                    <div className="flex justify-end gap-2 pt-1">
                        <button type="button" onClick={onClose} className="px-4 py-2 text-sm text-gray-600">Cancel</button>
                        <button type="submit" disabled={form.processing || !form.data.name}
                            className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                            {form.processing ? 'Creating…' : 'Create Workflow'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export default function WorkflowsIndex({ workflows }: Props) {
    const [modalOpen, setModalOpen] = useState(false);
    const confirm = useConfirm();

    return (
        <AppLayout title="Workflows">
            <Head title="Workflows" />
            <div className="max-w-4xl space-y-5">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-lg font-bold text-gray-900">Workflow Triggers</h1>
                        <p className="text-xs text-gray-500 mt-0.5">Automate actions based on events</p>
                    </div>
                    <button onClick={() => setModalOpen(true)}
                        className="flex items-center gap-2 px-3 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                        <Plus size={14} /> New Workflow
                    </button>
                </div>

                <div className="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
                    {workflows.length === 0 && (
                        <div className="px-5 py-12 text-center">
                            <Workflow size={24} className="text-gray-300 mx-auto mb-2" />
                            <p className="text-sm text-gray-400">No workflows configured yet.</p>
                        </div>
                    )}
                    {workflows.map(w => (
                        <div key={w.id} className={cn('px-5 py-4 flex items-start gap-4', !w.is_active && 'opacity-60')}>
                            <div className="flex-1 min-w-0">
                                <div className="flex items-center gap-2 mb-1">
                                    <p className="text-sm font-semibold text-gray-900">{w.name}</p>
                                    <span className={cn('px-1.5 py-0.5 rounded text-[10px] font-medium', TRIGGER_STYLES[w.trigger_event] ?? 'bg-gray-100 text-gray-600')}>
                                        {TRIGGER_LABELS[w.trigger_event] ?? w.trigger_event}
                                    </span>
                                    <span className="px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-600">
                                        → {ACTION_LABELS[w.action_type] ?? w.action_type}
                                    </span>
                                </div>
                                {w.description && <p className="text-xs text-gray-500">{w.description}</p>}
                                <p className="text-xs text-gray-400 mt-1">
                                    Run {w.run_count} time{w.run_count !== 1 ? 's' : ''}
                                    {w.last_run_at ? ` · Last run ${w.last_run_at}` : ''}
                                </p>
                            </div>
                            <div className="flex items-center gap-1.5 shrink-0">
                                <button onClick={() => router.post(`/workflows/${w.id}/toggle`)}
                                    className="text-gray-400 hover:text-indigo-600 transition-colors">
                                    {w.is_active ? <ToggleRight size={20} className="text-indigo-600" /> : <ToggleLeft size={20} />}
                                </button>
                                <button onClick={async () => {
                                    const ok = await confirm({
                                        title: 'Delete workflow?',
                                        description: 'This cannot be undone.',
                                        confirmText: 'Delete',
                                        variant: 'destructive',
                                    });
                                    if (!ok) return;
                                    router.delete(`/workflows/${w.id}`);
                                }}
                                    className="p-1 text-gray-400 hover:text-rose-500 rounded hover:bg-rose-50 transition-colors">
                                    <Trash2 size={14} />
                                </button>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
            {modalOpen && <WorkflowModal onClose={() => setModalOpen(false)} />}
        </AppLayout>
    );
}

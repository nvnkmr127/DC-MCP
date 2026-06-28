import React, { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { cn } from '@/lib/utils';
import { Plus, X, GitMerge, CheckSquare } from 'lucide-react';
import { Breadcrumbs } from '@/Components/Shared/Breadcrumbs';
import { toast } from 'sonner';

interface SprintTask { id: string; story_points: number; task: { id: string; title: string; status: string } | null; }
interface Sprint {
    id: string; name: string; goal: string | null; status: string;
    start_date: string | null; end_date: string | null;
    retrospective_notes: string | null;
    project: { id: string; name: string } | null;
    sprint_tasks: SprintTask[];
}
interface Project { id: string; name: string; }
interface Props { sprints: Sprint[]; projects: Project[]; }

const STATUS_STYLES: Record<string, string> = {
    planning: 'bg-gray-100 text-gray-700', active: 'bg-emerald-100 text-emerald-700', completed: 'bg-blue-100 text-blue-700',
};

function SprintModal({ projects, onClose }: { projects: Project[]; onClose: () => void }) {
    const form = useForm({ project_id: '', name: '', start_date: '', end_date: '', goal: '', status: 'planning' });
    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-3">
                <div className="flex items-center justify-between">
                    <h2 className="text-[15px] font-bold text-gray-900">New Sprint</h2>
                    <button onClick={onClose}><X size={16} className="text-gray-400" /></button>
                </div>
                <form onSubmit={e => { e.preventDefault(); form.post('/sprints', { onSuccess: onClose }); }} className="space-y-3">
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Project *</label>
                        <select value={form.data.project_id} onChange={e => form.setData('project_id', e.target.value)}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                            <option value="">Select project…</option>
                            {projects.map(p => <option key={p.id} value={p.id}>{p.name}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Sprint Name *</label>
                        <input type="text" value={form.data.name} onChange={e => form.setData('name', e.target.value)}
                            placeholder="e.g. Sprint 1 — May"
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Start Date *</label>
                            <input type="date" value={form.data.start_date} onChange={e => form.setData('start_date', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 font-medium">End Date *</label>
                            <input type="date" value={form.data.end_date} onChange={e => form.setData('end_date', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                        </div>
                    </div>
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Goal</label>
                        <textarea value={form.data.goal} onChange={e => form.setData('goal', e.target.value)} rows={2}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 resize-none" />
                    </div>
                    <div className="flex justify-end gap-2 pt-1">
                        <button type="button" onClick={onClose} className="px-4 py-2 text-sm text-gray-600">Cancel</button>
                        <button type="submit" disabled={form.processing || !form.data.project_id || !form.data.name}
                            className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                            {form.processing ? 'Creating…' : 'Create Sprint'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

function SprintRetrospectiveModal({ sprint, onClose, onComplete }: { sprint: Sprint, onClose: () => void, onComplete: () => void }) {
    const unfinishedCount = sprint.sprint_tasks.filter(t => t.task?.status !== 'done' && t.task?.status !== 'cancelled').length;
    const form = useForm({ unfinished_action: 'backlog', new_sprint_name: '', retrospective_notes: '' });

    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-3">
                <div className="flex items-center justify-between">
                    <h2 className="text-[15px] font-bold text-gray-900">Complete Sprint</h2>
                    <button onClick={onClose}><X size={16} className="text-gray-400" /></button>
                </div>
                
                {unfinishedCount > 0 ? (
                    <div className="bg-amber-50 border border-amber-200 text-amber-700 p-3 rounded-lg text-sm">
                        There are <strong>{unfinishedCount} open tasks</strong> in this sprint. What would you like to do with them?
                    </div>
                ) : (
                    <p className="text-sm text-gray-600">All tasks are completed. Great job!</p>
                )}

                <form onSubmit={e => { 
                    e.preventDefault(); 
                    form.post(`/sprints/${sprint.id}/complete`, { 
                        onSuccess: () => {
                            toast.success('🎉 Sprint completed! Great job team!');
                            onComplete();
                        }
                    }); 
                }} className="space-y-4 pt-2">
                    {unfinishedCount > 0 && (
                        <div className="space-y-2">
                            <label className="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="unfinished_action" value="backlog"
                                    checked={form.data.unfinished_action === 'backlog'}
                                    onChange={() => form.setData('unfinished_action', 'backlog')}
                                    className="text-indigo-600 focus:ring-indigo-500" />
                                <span className="text-sm text-gray-700">Move open tasks to Backlog</span>
                            </label>
                            <label className="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="unfinished_action" value="new_sprint"
                                    checked={form.data.unfinished_action === 'new_sprint'}
                                    onChange={() => form.setData('unfinished_action', 'new_sprint')}
                                    className="text-indigo-600 focus:ring-indigo-500" />
                                <span className="text-sm text-gray-700">Carry over to a New Sprint</span>
                            </label>
                        </div>
                    )}

                    {form.data.unfinished_action === 'new_sprint' && unfinishedCount > 0 && (
                        <div className="pl-6">
                            <label className="text-xs text-gray-500 font-medium">New Sprint Name *</label>
                            <input type="text" value={form.data.new_sprint_name} onChange={e => form.setData('new_sprint_name', e.target.value)}
                                placeholder="e.g. Sprint 2"
                                required
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                        </div>
                    )}

                    <div>
                        <label className="text-xs text-gray-500 font-medium">Retrospective Notes</label>
                        <textarea value={form.data.retrospective_notes} onChange={e => form.setData('retrospective_notes', e.target.value)} rows={3}
                            placeholder="What went well? What could be improved?"
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 resize-none" />
                    </div>

                    <div className="flex justify-end gap-2 pt-2">
                        <button type="button" onClick={onClose} className="px-4 py-2 text-sm text-gray-600">Cancel</button>
                        <button type="submit" disabled={form.processing || (form.data.unfinished_action === 'new_sprint' && !form.data.new_sprint_name && unfinishedCount > 0)}
                            className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                            {form.processing ? 'Completing…' : 'Complete Sprint'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export default function SprintsIndex({ sprints, projects }: Props) {
    const [modalOpen, setModalOpen] = useState(false);
    const [completingSprint, setCompletingSprint] = useState<Sprint | null>(null);
    const [expandedId, setExpandedId] = useState<string | null>(null);

    const active = sprints.filter(s => s.status === 'active');
    const planning = sprints.filter(s => s.status === 'planning');
    const completed = sprints.filter(s => s.status === 'completed');

    const SprintCard = ({ sprint }: { sprint: Sprint }) => {
        const totalPoints = sprint.sprint_tasks.reduce((s, t) => s + t.story_points, 0);
        const doneCount = sprint.sprint_tasks.filter(t => t.task?.status === 'done').length;
        const progress = sprint.sprint_tasks.length > 0 ? Math.round((doneCount / sprint.sprint_tasks.length) * 100) : 0;

        return (
            <div className="bg-white rounded-xl border border-gray-200 p-4">
                <div className="flex items-start justify-between gap-3 mb-2">
                    <div>
                        <p className="text-sm font-semibold text-gray-900">{sprint.name}</p>
                        <p className="text-xs text-gray-500 mt-0.5">{sprint.project?.name ?? '—'}</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <span className={cn('px-2 py-0.5 rounded text-[10px] font-semibold capitalize', STATUS_STYLES[sprint.status])}>
                            {sprint.status}
                        </span>
                        <select value={sprint.status}
                            onChange={e => {
                                if (e.target.value === 'completed') {
                                    setCompletingSprint(sprint);
                                } else {
                                    const newStatus = e.target.value;
                                    router.patch(`/sprints/${sprint.id}`, { status: newStatus }, {
                                        onSuccess: () => {
                                            if (newStatus === 'active') {
                                                toast.success('🚀 Sprint started! Time to crush it!');
                                            } else {
                                                toast.success(`Sprint status updated to ${newStatus}.`);
                                            }
                                        }
                                    });
                                }
                            }}
                            className="text-xs border border-gray-200 rounded px-1.5 py-0.5 text-gray-500 bg-white focus:ring-1 focus:ring-indigo-500">
                            <option value="planning">Planning</option>
                            <option value="active">Active</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                </div>
                {sprint.goal && <p className="text-xs text-gray-600 mb-2 italic">{sprint.goal}</p>}
                <div className="flex items-center gap-4 text-xs text-gray-500 mb-2">
                    <span>{sprint.start_date} → {sprint.end_date}</span>
                    <span>{sprint.sprint_tasks.length} tasks · {totalPoints} pts</span>
                </div>
                <div className="w-full bg-gray-100 rounded-full h-1.5">
                    <div className="bg-indigo-500 h-1.5 rounded-full transition-all" style={{ width: `${progress}%` }} />
                </div>
                <p className="text-xs text-gray-500 mt-1">{doneCount}/{sprint.sprint_tasks.length} done ({progress}%)</p>

                {sprint.status === 'completed' && sprint.retrospective_notes && (
                    <div className="mt-3 p-3 bg-gray-50 border border-gray-100 rounded-lg">
                        <p className="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">Retrospective</p>
                        <p className="text-xs text-gray-700 whitespace-pre-wrap">{sprint.retrospective_notes}</p>
                    </div>
                )}

                <button onClick={() => setExpandedId(expandedId === sprint.id ? null : sprint.id)}
                    className="mt-2 text-xs text-indigo-600 font-medium">
                    {expandedId === sprint.id ? 'Hide tasks ↑' : 'Show tasks ↓'}
                </button>
                {expandedId === sprint.id && (
                    <div className="mt-3 space-y-1.5 border-t border-gray-100 pt-3">
                        {sprint.sprint_tasks.map(st => (
                            <div key={st.id} className="flex items-center justify-between gap-2">
                                <div className="flex items-center gap-2">
                                    <CheckSquare size={11} className={st.task?.status === 'done' ? 'text-emerald-500' : 'text-gray-400'} />
                                    <span className="text-xs text-gray-700">{st.task?.title ?? '—'}</span>
                                </div>
                                <span className="text-xs text-gray-400">{st.story_points}pts</span>
                            </div>
                        ))}
                        {sprint.sprint_tasks.length === 0 && <p className="text-xs text-gray-400">No tasks in sprint.</p>}
                    </div>
                )}
            </div>
        );
    };

    return (
        <AppLayout title="Sprints">
            <Head title="Sprints" />
            <div className="max-w-4xl space-y-5">
                <div className="mb-2">
                    <Breadcrumbs items={[
                        { label: 'Sprints' }
                    ]} />
                </div>
                <div className="flex items-center justify-between">
                    <h1 className="text-lg font-bold text-gray-900">Sprint Planner</h1>
                    <button onClick={() => setModalOpen(true)}
                        className="flex items-center gap-2 px-3 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                        <Plus size={14} /> New Sprint
                    </button>
                </div>

                {active.length > 0 && (
                    <div>
                        <h2 className="text-xs font-bold text-emerald-700 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                            <GitMerge size={12} /> Active
                        </h2>
                        <div className="grid grid-cols-1 gap-3">
                            {active.map(s => <SprintCard key={s.id} sprint={s} />)}
                        </div>
                    </div>
                )}

                {planning.length > 0 && (
                    <div>
                        <h2 className="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3">Planning</h2>
                        <div className="grid grid-cols-2 gap-3">
                            {planning.map(s => <SprintCard key={s.id} sprint={s} />)}
                        </div>
                    </div>
                )}

                {completed.length > 0 && (
                    <div>
                        <h2 className="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Completed</h2>
                        <div className="grid grid-cols-2 gap-3">
                            {completed.map(s => <SprintCard key={s.id} sprint={s} />)}
                        </div>
                    </div>
                )}

                {sprints.length === 0 && (
                    <div className="bg-white rounded-xl border border-gray-200 px-5 py-16 text-center shadow-sm">
                        <div className="w-12 h-12 rounded-xl bg-gray-50 border border-gray-100 shadow-sm flex items-center justify-center mx-auto mb-4">
                            <GitMerge size={20} className="text-gray-400" />
                        </div>
                        <p className="text-[14px] font-semibold text-gray-900 mb-1">Welcome to Sprint Planning!</p>
                        <p className="text-[13px] text-gray-500 max-w-sm mx-auto mb-6">Organize your tasks into time-boxed sprints to focus your team and deliver work incrementally.</p>
                        <button onClick={() => setModalOpen(true)} className="px-4 py-2 bg-indigo-600 border border-transparent rounded-lg text-[13px] font-semibold text-white hover:bg-indigo-700 transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1">
                            Create First Sprint
                        </button>
                    </div>
                )}
            </div>
            {modalOpen && <SprintModal projects={projects} onClose={() => setModalOpen(false)} />}
            {completingSprint && (
                <SprintRetrospectiveModal 
                    sprint={completingSprint} 
                    onClose={() => setCompletingSprint(null)} 
                    onComplete={() => setCompletingSprint(null)} 
                />
            )}
        </AppLayout>
    );
}

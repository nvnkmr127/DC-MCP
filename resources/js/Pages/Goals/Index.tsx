import React, { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { cn } from '@/lib/utils';
import { Plus, X, Trash2, Flag } from 'lucide-react';

const PERIODS = ['q1', 'q2', 'q3', 'q4', 'annual'] as const;
const PERIOD_LABELS: Record<string, string> = { q1: 'Q1', q2: 'Q2', q3: 'Q3', q4: 'Q4', annual: 'Annual' };

const STATUS_STYLES: Record<string, string> = {
    draft:     'bg-gray-100 text-gray-600',
    active:    'bg-indigo-100 text-indigo-700',
    completed: 'bg-emerald-100 text-emerald-700',
    cancelled: 'bg-rose-100 text-rose-600',
};

interface KeyResult { id: string; title: string; target: number; current: number; unit: string; }
interface Goal {
    id: string; title: string; description: string | null;
    period: string; year: number; status: string; progress: number;
    key_results: KeyResult[];
    owner: { id: string; name: string } | null;
}
interface Props {
    goals: Goal[]; byPeriod: Record<string, Goal[]>;
    currentPeriod: string; orgProgress: number; year: number;
    team: { id: string; name: string }[];
}

function progressColor(p: number) {
    if (p >= 70) return 'bg-emerald-500';
    if (p >= 40) return 'bg-amber-400';
    return 'bg-rose-500';
}
function progressTextColor(p: number) {
    if (p >= 70) return 'text-emerald-600';
    if (p >= 40) return 'text-amber-600';
    return 'text-rose-600';
}

function GoalCard({ goal }: { goal: Goal }) {
    const [editingKr, setEditingKr] = useState<string | null>(null);
    const [krValue, setKrValue] = useState('');

    function saveKr(krId: string) {
        router.patch(`/goals/${goal.id}/kr`, { kr_id: krId, current: krValue });
        setEditingKr(null);
    }

    return (
        <div className="bg-white rounded-xl border border-gray-200 p-4 space-y-3">
            <div className="flex items-start justify-between gap-2">
                <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 flex-wrap">
                        <h3 className="font-semibold text-gray-900 text-sm">{goal.title}</h3>
                        <span className={cn('text-xs px-2 py-0.5 rounded-full font-medium', STATUS_STYLES[goal.status])}>
                            {goal.status}
                        </span>
                    </div>
                    {goal.owner && <p className="text-xs text-gray-400 mt-0.5">Owner: {goal.owner.name}</p>}
                    {goal.description && <p className="text-xs text-gray-500 mt-1">{goal.description}</p>}
                </div>
                <div className="text-right shrink-0">
                    <span className={cn('text-2xl font-bold', progressTextColor(goal.progress))}>{goal.progress}%</span>
                </div>
            </div>

            <div className="w-full bg-gray-100 rounded-full h-2">
                <div className={cn('h-2 rounded-full transition-all', progressColor(goal.progress))}
                    style={{ width: `${goal.progress}%` }} />
            </div>

            {goal.key_results.length > 0 && (
                <div className="space-y-2 pt-1">
                    {goal.key_results.map(kr => {
                        const pct = kr.target > 0 ? Math.min(100, Math.round((kr.current / kr.target) * 100)) : 0;
                        return (
                            <div key={kr.id} className="flex items-center gap-2">
                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center justify-between mb-0.5">
                                        <span className="text-xs text-gray-700 truncate">{kr.title}</span>
                                        <span className="text-xs text-gray-500 shrink-0 ml-2">
                                            {editingKr === kr.id ? (
                                                <input type="number" value={krValue}
                                                    onChange={e => setKrValue(e.target.value)}
                                                    onBlur={() => saveKr(kr.id)}
                                                    onKeyDown={e => e.key === 'Enter' && saveKr(kr.id)}
                                                    autoFocus
                                                    className="w-16 border border-indigo-300 rounded px-1 py-0.5 text-xs text-right focus:outline-none focus:ring-1 focus:ring-indigo-400" />
                                            ) : (
                                                <button onClick={() => { setEditingKr(kr.id); setKrValue(String(kr.current)); }}
                                                    className="hover:text-indigo-600 font-medium">
                                                    {kr.current}/{kr.target}{kr.unit ? ` ${kr.unit}` : ''}
                                                </button>
                                            )}
                                        </span>
                                    </div>
                                    <div className="w-full bg-gray-100 rounded-full h-1">
                                        <div className={cn('h-1 rounded-full', progressColor(pct))} style={{ width: `${pct}%` }} />
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}

            <div className="flex items-center gap-2 pt-1">
                {goal.status === 'active' && (
                    <button onClick={() => router.patch(`/goals/${goal.id}`, { status: 'completed' })}
                        className="text-xs text-emerald-600 hover:text-emerald-700 font-medium">Mark Complete</button>
                )}
                <button onClick={() => { if (confirm('Delete this goal?')) router.delete(`/goals/${goal.id}`); }}
                    className="text-xs text-gray-400 hover:text-rose-500 ml-auto">
                    <Trash2 className="w-3.5 h-3.5" />
                </button>
            </div>
        </div>
    );
}

function AddGoalModal({ team, onClose, currentPeriod, year }: { team: Props['team']; onClose: () => void; currentPeriod: string; year: number }) {
    const form = useForm({
        title: '', description: '', period: currentPeriod, year: String(year),
        owner_id: '', status: 'active',
    });
    const [krs, setKrs] = useState<{ title: string; target: string; unit: string }[]>([]);

    function addKr() { setKrs(prev => [...prev, { title: '', target: '', unit: '' }]); }
    function removeKr(i: number) { setKrs(prev => prev.filter((_, j) => j !== i)); }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        const data = {
            ...form.data,
            year: parseInt(form.data.year),
            key_results: krs.filter(k => k.title && k.target).map(k => ({
                title: k.title, target: parseFloat(k.target), unit: k.unit,
            })),
        };
        router.post('/goals', data, { onSuccess: onClose });
    }

    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 space-y-4 max-h-[90vh] overflow-y-auto">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-bold text-gray-900">Add Goal</h2>
                    <button onClick={onClose}><X className="w-5 h-5 text-gray-400" /></button>
                </div>
                <form onSubmit={submit} className="space-y-3">
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Title *</label>
                        <input type="text" value={form.data.title} onChange={e => form.setData('title', e.target.value)}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Description</label>
                        <textarea value={form.data.description} onChange={e => form.setData('description', e.target.value)} rows={2}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 resize-none" />
                    </div>
                    <div className="grid grid-cols-3 gap-3">
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Period</label>
                            <select value={form.data.period} onChange={e => form.setData('period', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                {PERIODS.map(p => <option key={p} value={p}>{PERIOD_LABELS[p]}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Year</label>
                            <input type="number" value={form.data.year} onChange={e => form.setData('year', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Owner</label>
                            <select value={form.data.owner_id} onChange={e => form.setData('owner_id', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                <option value="">—</option>
                                {team.map(u => <option key={u.id} value={u.id}>{u.name}</option>)}
                            </select>
                        </div>
                    </div>

                    <div>
                        <div className="flex items-center justify-between mb-2">
                            <label className="text-xs text-gray-500 font-medium">Key Results</label>
                            <button type="button" onClick={addKr} className="text-xs text-indigo-600 hover:text-indigo-700 font-medium">+ Add KR</button>
                        </div>
                        {krs.map((kr, i) => (
                            <div key={i} className="flex items-center gap-2 mb-2">
                                <input type="text" placeholder="Key result…" value={kr.title}
                                    onChange={e => setKrs(prev => prev.map((k, j) => j === i ? { ...k, title: e.target.value } : k))}
                                    className="flex-1 border border-gray-300 rounded-lg px-2 py-1.5 text-xs focus:ring-1 focus:ring-indigo-400" />
                                <input type="number" placeholder="Target" value={kr.target}
                                    onChange={e => setKrs(prev => prev.map((k, j) => j === i ? { ...k, target: e.target.value } : k))}
                                    className="w-20 border border-gray-300 rounded-lg px-2 py-1.5 text-xs focus:ring-1 focus:ring-indigo-400" />
                                <input type="text" placeholder="Unit" value={kr.unit}
                                    onChange={e => setKrs(prev => prev.map((k, j) => j === i ? { ...k, unit: e.target.value } : k))}
                                    className="w-16 border border-gray-300 rounded-lg px-2 py-1.5 text-xs focus:ring-1 focus:ring-indigo-400" />
                                <button type="button" onClick={() => removeKr(i)} className="text-gray-400 hover:text-rose-500">
                                    <X className="w-3.5 h-3.5" />
                                </button>
                            </div>
                        ))}
                    </div>

                    <div className="flex justify-end gap-2 pt-2">
                        <button type="button" onClick={onClose} className="px-4 py-2 text-sm text-gray-600">Cancel</button>
                        <button type="submit" disabled={!form.data.title}
                            className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                            Add Goal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export default function GoalsIndex({ goals, byPeriod, currentPeriod, orgProgress, year, team }: Props) {
    const [activePeriod, setActivePeriod] = useState(currentPeriod);
    const [addOpen, setAddOpen] = useState(false);

    const shownGoals = byPeriod[activePeriod] ?? [];

    return (
        <AppLayout>
            <Head title="Goals & OKRs" />
            <div className="max-w-5xl mx-auto px-4 py-6 space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Goals & OKRs</h1>
                        <p className="text-sm text-gray-500 mt-0.5">Quarterly objectives and key results</p>
                    </div>
                    <div className="flex items-center gap-3">
                        <div className="text-center">
                            <p className="text-xs text-gray-400">Org Progress</p>
                            <p className={cn('text-2xl font-bold', progressTextColor(orgProgress))}>{orgProgress}%</p>
                        </div>
                        <button onClick={() => setAddOpen(true)}
                            className="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                            <Plus className="w-4 h-4" /> Add Goal
                        </button>
                    </div>
                </div>

                <div className="flex gap-2">
                    {PERIODS.map(p => (
                        <button key={p} onClick={() => setActivePeriod(p)}
                            className={cn('px-4 py-1.5 rounded-lg text-sm font-medium border transition-all',
                                activePeriod === p
                                    ? 'bg-indigo-600 text-white border-indigo-600'
                                    : p === currentPeriod
                                        ? 'bg-indigo-50 text-indigo-700 border-indigo-200'
                                        : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300'
                            )}>
                            {PERIOD_LABELS[p]}
                            {p === currentPeriod && <span className="ml-1.5 text-[10px] opacity-70">Current</span>}
                        </button>
                    ))}
                </div>

                {shownGoals.length === 0 ? (
                    <div className="bg-white rounded-xl border border-gray-200 py-16 text-center">
                        <Flag className="w-10 h-10 text-gray-200 mx-auto mb-3" />
                        <p className="text-sm text-gray-400">No goals for {PERIOD_LABELS[activePeriod]} {year}.</p>
                        <button onClick={() => setAddOpen(true)} className="mt-2 text-sm text-indigo-600 hover:text-indigo-700 font-medium">
                            Add the first goal →
                        </button>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {shownGoals.map(goal => <GoalCard key={goal.id} goal={goal} />)}
                    </div>
                )}

                {addOpen && <AddGoalModal team={team} onClose={() => setAddOpen(false)} currentPeriod={currentPeriod} year={year} />}
            </div>
        </AppLayout>
    );
}

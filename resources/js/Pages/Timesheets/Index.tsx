import React, { useState } from 'react';
import { Button } from '@/Components/ui/Button';
import { Head, router, useForm } from '@inertiajs/react';
import WorkloadLayout from '@/Layouts/WorkloadLayout';
import { cn } from '@/lib/utils';
import { ChevronLeft, ChevronRight, Play, Square, Clock } from 'lucide-react';
import { useConfirm } from '@/hooks/useConfirm';

interface TimeEntry {
    id: string; task_id: string | null; task_title: string | null; project_name: string | null;
    hours: number; description: string | null; logged_date: string;
    is_billable: boolean; timer_started_at: string | null; status: 'pending' | 'approved' | 'flagged';
}
interface Props {
    entries: TimeEntry[]; weekStart: string; weekEnd: string;
    totalHours: number; billableHours: number; utilization: number;
    teamMembers: { id: string; name: string }[];
    tasks: { id: string; title: string }[];
    viewUserId: string; isCeo: boolean;
}

function formatHours(h: number) {
    if (h === 0) return '—';
    const hrs = Math.floor(h);
    const mins = Math.round((h - hrs) * 60);
    return mins > 0 ? `${hrs}h ${mins}m` : `${hrs}h`;
}

function weekDays(weekStart: string): string[] {
    const days = [];
    const start = new Date(weekStart + 'T00:00:00');
    for (let i = 0; i < 7; i++) {
        const d = new Date(start);
        d.setDate(start.getDate() + i);
        days.push(d.toISOString().slice(0, 10));
    }
    return days;
}

const DAY_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

function prevWeek(ws: string) {
    const d = new Date(ws + 'T00:00:00');
    d.setDate(d.getDate() - 7);
    return d.toISOString().slice(0, 10);
}
function nextWeek(ws: string) {
    const d = new Date(ws + 'T00:00:00');
    d.setDate(d.getDate() + 7);
    return d.toISOString().slice(0, 10);
}

export default function TimesheetsIndex({ entries, weekStart, weekEnd, totalHours, billableHours, utilization, teamMembers, tasks, viewUserId, isCeo }: Props) {
    const days = weekDays(weekStart);
    const [addOpen, setAddOpen] = useState(false);
    const [addDay, setAddDay] = useState<string | null>(null);
    const [selectedIds, setSelectedIds] = useState<Set<string>>(new Set());
    const form = useForm({ task_id: '', hours: '', description: '', logged_date: '', is_billable: 'true' });
    const confirm = useConfirm();

    const activeTimer = entries.find(e => e.timer_started_at);

    const entriesByDay: Record<string, TimeEntry[]> = {};
    days.forEach(d => { entriesByDay[d] = []; });
    entries.forEach(e => {
        if (entriesByDay[e.logged_date]) entriesByDay[e.logged_date].push(e);
    });

    function navigate(ws: string) {
        router.get('/timesheets', { week: ws }, { preserveState: true });
    }

    function openAddFor(day: string) {
        setAddDay(day);
        form.setData('logged_date', day);
        form.setData('is_billable', 'true');
        setAddOpen(true);
    }

    const today = new Date().toISOString().slice(0, 10);

    return (
        <WorkloadLayout title="Timesheets" currentTab="timesheets">
            <Head title="Timesheets" />
            <div className="space-y-5">
                <div className="flex items-center justify-between">
                    <div>
                        {isCeo && teamMembers.length > 0 && (
                            <div className="flex items-center gap-3">
                                <label className="text-sm font-medium text-gray-700">Team Member:</label>
                                <select 
                                    value={viewUserId}
                                    onChange={e => router.get('/timesheets', { user_id: e.target.value, week: weekStart }, { preserveState: true })}
                                    className="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-indigo-500 min-w-[200px]"
                                >
                                    {teamMembers.map(m => <option key={m.id} value={m.id}>{m.name}</option>)}
                                </select>
                            </div>
                        )}
                    </div>
                    <div className="flex items-center gap-3">
                        {activeTimer ? (
                            <Button onClick={async () => {
                                const ok = await confirm({
                                    title: 'Stop Timer & Log Time?',
                                    description: 'Are you sure these hours are correct? The time entry will be locked once stopped.',
                                    confirmText: 'Stop & Log',
                                });
                                if (ok) {
                                    router.post(`/timesheets/timer/${activeTimer.id}/stop`);
                                }
                            }}
                                className="flex items-center gap-2 px-4 py-2 bg-rose-600 text-white text-sm font-medium rounded-lg hover:bg-rose-700">
                                <Square className="w-4 h-4" /> Stop Timer
                            </Button>
                        ) : (
                            <Button onClick={() => {
                                if (tasks.length === 0) return;
                                const taskId = tasks[0].id;
                                router.post('/timesheets/timer/start', { task_id: taskId, is_billable: true });
                            }} className="flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700">
                                <Play className="w-4 h-4" /> Start Timer
                            </Button>
                        )}
                    </div>
                </div>

                {/* Stats */}
                <div className="grid grid-cols-3 gap-4">
                    <div className="bg-white rounded-xl border border-gray-200 p-4">
                        <div className="flex justify-between items-start mb-1">
                            <p className="text-xs text-gray-400">Total Hours</p>
                            <span className="text-[10px] font-medium text-gray-400 uppercase tracking-wider">Target: 40h</span>
                        </div>
                        <p className="text-2xl font-bold text-gray-900 mt-1">{formatHours(totalHours)}</p>
                        <div className="mt-3 h-1.5 w-full bg-gray-100 rounded-full overflow-hidden">
                            <div className={cn("h-full rounded-full", totalHours >= 40 ? 'bg-emerald-500' : 'bg-indigo-500')} style={{ width: `${Math.min(100, (totalHours / 40) * 100)}%` }} />
                        </div>
                    </div>
                    <div className="bg-white rounded-xl border border-gray-200 p-4">
                        <p className="text-xs text-gray-400">Billable Hours</p>
                        <p className="text-2xl font-bold text-emerald-600 mt-1">{formatHours(billableHours)}</p>
                    </div>
                    <div className="bg-white rounded-xl border border-gray-200 p-4">
                        <p className="text-xs text-gray-400">Utilization</p>
                        <p className={cn('text-2xl font-bold mt-1', utilization >= 70 ? 'text-emerald-600' : utilization >= 50 ? 'text-amber-600' : 'text-rose-600')}>
                            {utilization}%
                        </p>
                    </div>
                </div>

                {/* Active timer banner */}
                {activeTimer && (
                    <div className="bg-rose-50 border border-rose-200 rounded-xl p-3 flex items-center gap-3">
                        <div className="w-2 h-2 rounded-full bg-rose-500 animate-pulse" />
                        <p className="text-sm text-rose-700 font-medium">
                            Timer running — {activeTimer.task_title ?? 'No task'} (started {activeTimer.timer_started_at ? new Date(activeTimer.timer_started_at).toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' }) : ''})
                        </p>
                        <Button onClick={async () => {
                            const ok = await confirm({
                                title: 'Stop Timer & Log Time?',
                                description: 'Are you sure these hours are correct? The time entry will be locked once stopped.',
                                confirmText: 'Stop & Log',
                            });
                            if (ok) {
                                router.post(`/timesheets/timer/${activeTimer.id}/stop`);
                            }
                        }}
                            className="ml-auto px-3 py-1 bg-rose-600 text-white text-xs rounded-lg hover:bg-rose-700 font-medium">
                            Stop
                        </Button>
                    </div>
                )}

                {/* Week grid */}
                <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <div className="flex items-center justify-between px-5 py-3 border-b border-gray-100">
                        <Button onClick={() => navigate(prevWeek(weekStart))} className="p-1.5 rounded-lg border border-gray-200 hover:bg-gray-50">
                            <ChevronLeft className="w-4 h-4" />
                        </Button>
                        <span className="text-sm font-semibold text-gray-700">
                            {new Date(weekStart + 'T00:00:00').toLocaleDateString('en-IN', { day: 'numeric', month: 'short' })} –{' '}
                            {new Date(weekEnd + 'T00:00:00').toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })}
                        </span>
                        <Button onClick={() => navigate(nextWeek(weekStart))} className="p-1.5 rounded-lg border border-gray-200 hover:bg-gray-50">
                            <ChevronRight className="w-4 h-4" />
                        </Button>
                    </div>
                    <div className="grid grid-cols-7">
                        {days.map((day, i) => {
                            const dayEntries = entriesByDay[day] ?? [];
                            const dayHours = dayEntries.reduce((s, e) => s + e.hours, 0);
                            const isToday = day === today;
                            return (
                                <div key={day} className={cn('border-r last:border-r-0 border-gray-100 min-h-[120px]', isToday && 'bg-indigo-50/40')}>
                                    <div className={cn('px-3 py-2 border-b border-gray-100 text-center', isToday && 'bg-indigo-50')}>
                                        <p className="text-xs text-gray-400">{DAY_LABELS[i]}</p>
                                        <p className={cn('text-sm font-bold', isToday ? 'text-indigo-600' : 'text-gray-700')}>
                                            {parseInt(day.slice(8))}
                                        </p>
                                        {dayHours > 0 && <p className="text-[10px] text-gray-400">{formatHours(dayHours)}</p>}
                                    </div>
                                    <div className="px-2 py-1.5 space-y-1">
                                        {dayEntries.map(e => (
                                            <label key={e.id} className={cn('relative block rounded text-[10px] pl-6 pr-1.5 py-1.5 border cursor-pointer select-none transition-colors',
                                                selectedIds.has(e.id) ? 'ring-1 ring-indigo-500 border-indigo-500 bg-indigo-50 text-indigo-900' :
                                                e.is_billable ? 'bg-emerald-50 border-emerald-100 text-emerald-800' : 'bg-gray-50 border-gray-100 text-gray-600'
                                            )}>
                                                {isCeo && (
                                                    <input 
                                                        type="checkbox" 
                                                        className="absolute left-1.5 top-2 w-3 h-3 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                                                        checked={selectedIds.has(e.id)}
                                                        onChange={(ev) => {
                                                            const s = new Set(selectedIds);
                                                            if (ev.target.checked) s.add(e.id);
                                                            else s.delete(e.id);
                                                            setSelectedIds(s);
                                                        }}
                                                    />
                                                )}
                                                {!isCeo && <div className="absolute left-2 top-2 w-1.5 h-1.5 rounded-full bg-current opacity-40" />}
                                                <div className="flex justify-between items-start mb-0.5">
                                                    <p className="font-semibold truncate pr-2">{e.task_title ?? 'No task'}</p>
                                                    {e.status === 'approved' && <span className="shrink-0 inline-flex items-center justify-center w-3 h-3 bg-emerald-100 text-emerald-600 rounded-full text-[8px]">✓</span>}
                                                    {e.status === 'flagged' && <span className="shrink-0 inline-flex items-center justify-center w-3 h-3 bg-rose-100 text-rose-600 rounded-full text-[8px]">!</span>}
                                                </div>
                                                <p className="text-opacity-80">{formatHours(e.hours)}{!e.is_billable && ' · NB'}</p>
                                            </label>
                                        ))}
                                        <Button onClick={() => openAddFor(day)}
                                            className="w-full text-[10px] text-gray-300 hover:text-indigo-500 hover:bg-indigo-50 rounded py-1 transition-colors text-center">
                                            + log
                                        </Button>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>

                {addOpen && (
                    <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
                        <div className="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-4">
                            <h2 className="text-lg font-bold text-gray-900">Log Time — {addDay}</h2>
                            <form onSubmit={e => {
                                e.preventDefault();
                                router.post(`/tasks/${form.data.task_id}/log-time`, {
                                    hours: form.data.hours,
                                    description: form.data.description,
                                    logged_date: form.data.logged_date,
                                    is_billable: form.data.is_billable === 'true',
                                }, { onSuccess: () => setAddOpen(false) });
                            }} className="space-y-3">
                                <div>
                                    <label className="text-xs text-gray-500 font-medium">Task *</label>
                                    <select value={form.data.task_id} onChange={e => form.setData('task_id', e.target.value)}
                                        className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                        <option value="">Select task…</option>
                                        {tasks.map(t => <option key={t.id} value={t.id}>{t.title}</option>)}
                                    </select>
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="text-xs text-gray-500 font-medium">Hours *</label>
                                        <input type="number" step="0.25" min="0.25" max="24" value={form.data.hours}
                                            onChange={e => form.setData('hours', e.target.value)}
                                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                                    </div>
                                    <div>
                                        <label className="text-xs text-gray-500 font-medium">Billable</label>
                                        <select value={form.data.is_billable} onChange={e => form.setData('is_billable', e.target.value)}
                                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                            <option value="true">Billable</option>
                                            <option value="false">Non-billable</option>
                                        </select>
                                    </div>
                                </div>
                                <div>
                                    <label className="text-xs text-gray-500 font-medium">Description</label>
                                    <input type="text" value={form.data.description} onChange={e => form.setData('description', e.target.value)}
                                        className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                                </div>
                                <div className="flex justify-end gap-2 pt-2">
                                    <Button type="button" onClick={() => setAddOpen(false)} className="px-4 py-2 text-sm text-gray-600">Cancel</Button>
                                    <Button type="submit" disabled={!form.data.task_id || !form.data.hours}
                                        className="disabled:opacity-50" >
                                        Log Time
                                    </Button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}

                {/* Bulk Actions Bar */}
                {selectedIds.size > 0 && (
                    <div className="fixed bottom-6 left-1/2 -translate-x-1/2 bg-gray-900 text-white px-4 py-3 rounded-xl shadow-2xl flex items-center gap-4 animate-in slide-in-from-bottom-4 z-40">
                        <span className="text-sm font-medium">{selectedIds.size} selected</span>
                        <div className="w-px h-4 bg-gray-700" />
                        <div className="flex items-center gap-2">
                            <Button
                                onClick={() => {
                                    router.post('/timesheets/bulk-status', { entry_ids: Array.from(selectedIds), status: 'approved' }, { onSuccess: () => setSelectedIds(new Set()) });
                                }}
                                className="bg-emerald-500 hover:bg-emerald-600 text-white text-xs px-3 py-1.5 h-auto rounded-lg"
                            >
                                Approve
                            </Button>
                            <Button
                                onClick={() => {
                                    router.post('/timesheets/bulk-status', { entry_ids: Array.from(selectedIds), status: 'flagged' }, { onSuccess: () => setSelectedIds(new Set()) });
                                }}
                                className="bg-rose-500 hover:bg-rose-600 text-white text-xs px-3 py-1.5 h-auto rounded-lg"
                            >
                                Flag
                            </Button>
                            <Button
                                onClick={() => {
                                    router.post('/timesheets/bulk-status', { entry_ids: Array.from(selectedIds), status: 'pending' }, { onSuccess: () => setSelectedIds(new Set()) });
                                }}
                                className="bg-gray-700 hover:bg-gray-600 text-white text-xs px-3 py-1.5 h-auto rounded-lg"
                            >
                                Clear Status
                            </Button>
                        </div>
                        <button onClick={() => setSelectedIds(new Set())} className="ml-2 text-gray-400 hover:text-white transition-colors">
                            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                )}
            </div>
        </WorkloadLayout>
    );
}

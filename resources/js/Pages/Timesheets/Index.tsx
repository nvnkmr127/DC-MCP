import React, { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import WorkloadLayout from '@/Layouts/WorkloadLayout';
import { cn } from '@/lib/utils';
import { ChevronLeft, ChevronRight, Play, Square, Clock } from 'lucide-react';
import { useConfirm } from '@/hooks/useConfirm';

interface TimeEntry {
    id: string; task_id: string | null; task_title: string | null; project_name: string | null;
    hours: number; description: string | null; logged_date: string;
    is_billable: boolean; timer_started_at: string | null;
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
                    <div></div>
                    <div className="flex items-center gap-3">
                        {activeTimer ? (
                            <button onClick={async () => {
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
                            </button>
                        ) : (
                            <button onClick={() => {
                                if (tasks.length === 0) return;
                                const taskId = tasks[0].id;
                                router.post('/timesheets/timer/start', { task_id: taskId, is_billable: true });
                            }} className="flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700">
                                <Play className="w-4 h-4" /> Start Timer
                            </button>
                        )}
                    </div>
                </div>

                {/* Stats */}
                <div className="grid grid-cols-3 gap-4">
                    <div className="bg-white rounded-xl border border-gray-200 p-4">
                        <p className="text-xs text-gray-400">Total Hours</p>
                        <p className="text-2xl font-bold text-gray-900 mt-1">{formatHours(totalHours)}</p>
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
                        <button onClick={async () => {
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
                        </button>
                    </div>
                )}

                {/* Week grid */}
                <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <div className="flex items-center justify-between px-5 py-3 border-b border-gray-100">
                        <button onClick={() => navigate(prevWeek(weekStart))} className="p-1.5 rounded-lg border border-gray-200 hover:bg-gray-50">
                            <ChevronLeft className="w-4 h-4" />
                        </button>
                        <span className="text-sm font-semibold text-gray-700">
                            {new Date(weekStart + 'T00:00:00').toLocaleDateString('en-IN', { day: 'numeric', month: 'short' })} –{' '}
                            {new Date(weekEnd + 'T00:00:00').toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })}
                        </span>
                        <button onClick={() => navigate(nextWeek(weekStart))} className="p-1.5 rounded-lg border border-gray-200 hover:bg-gray-50">
                            <ChevronRight className="w-4 h-4" />
                        </button>
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
                                            <div key={e.id} className={cn('rounded text-[10px] px-1.5 py-1 border',
                                                e.is_billable ? 'bg-emerald-50 border-emerald-100 text-emerald-800' : 'bg-gray-50 border-gray-100 text-gray-600'
                                            )}>
                                                <p className="font-medium truncate">{e.task_title ?? 'No task'}</p>
                                                <p className="text-gray-400">{formatHours(e.hours)}{!e.is_billable && ' · NB'}</p>
                                            </div>
                                        ))}
                                        <button onClick={() => openAddFor(day)}
                                            className="w-full text-[10px] text-gray-300 hover:text-indigo-500 hover:bg-indigo-50 rounded py-1 transition-colors text-center">
                                            + log
                                        </button>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>

                {/* Add entry modal */}
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
                                    <button type="button" onClick={() => setAddOpen(false)} className="px-4 py-2 text-sm text-gray-600">Cancel</button>
                                    <button type="submit" disabled={!form.data.task_id || !form.data.hours}
                                        className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                                        Log Time
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}
            </div>
        </WorkloadLayout>
    );
}

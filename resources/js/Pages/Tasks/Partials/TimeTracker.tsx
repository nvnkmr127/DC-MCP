import React, { useState, useEffect } from 'react';
import { useForm, router, usePage } from '@inertiajs/react';
import { useConfirm } from '@/hooks/useConfirm';
import { Play, Square, Trash2 } from 'lucide-react';
import { cn, formatDate, formatHours } from '@/lib/utils';

interface TimeTrackerProps {
    taskId: string;
    timeEntries: any[];
}

export const TimeTracker: React.FC<TimeTrackerProps> = ({ taskId, timeEntries }) => {
    const { auth } = usePage<any>().props;
    const confirm = useConfirm();
    
    // Find if there is an active timer for this task and current user
    const activeTimer = timeEntries.find(e => e.timer_started_at && e.user?.id === auth.user.id);
    
    const [elapsedTime, setElapsedTime] = useState('00:00:00');

    // Update elapsed time every second if timer is active
    useEffect(() => {
        if (!activeTimer?.timer_started_at) {
            setElapsedTime('00:00:00');
            return;
        }

        const interval = setInterval(() => {
            const start = new Date(activeTimer.timer_started_at).getTime();
            const now = new Date().getTime();
            const diff = Math.max(0, Math.floor((now - start) / 1000));
            
            const h = Math.floor(diff / 3600).toString().padStart(2, '0');
            const m = Math.floor((diff % 3600) / 60).toString().padStart(2, '0');
            const s = (diff % 60).toString().padStart(2, '0');
            setElapsedTime(`${h}:${m}:${s}`);
        }, 1000);

        return () => clearInterval(interval);
    }, [activeTimer]);

    const timeForm = useForm({
        hours: '',
        description: '',
        logged_date: new Date().toISOString().slice(0, 10)
    });

    function startGlobalTimer() {
        router.post('/timesheets/timer/start', { task_id: taskId }, { preserveScroll: true });
    }

    function stopGlobalTimer() {
        if (activeTimer) {
            router.post(`/timesheets/timer/${activeTimer.id}/stop`, {}, { preserveScroll: true });
        }
    }

    function submitTime(e: React.FormEvent) {
        e.preventDefault();
        timeForm.post(`/tasks/${taskId}/log-time`, {
            preserveScroll: true,
            onSuccess: () => timeForm.reset(),
        });
    }

    const totalLogged = timeEntries.reduce((s, e) => s + (e.hours || 0), 0);

    return (
        <div className="space-y-4">
            {/* Timer */}
            <div className="flex items-center gap-4 p-4 bg-gray-50 rounded-lg">
                {!activeTimer ? (
                    <button
                        type="button"
                        onClick={startGlobalTimer}
                        className="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-indigo-600 text-white hover:bg-indigo-700"
                    >
                        <Play size={14} /> Start Timer
                    </button>
                ) : (
                    <button
                        type="button"
                        onClick={stopGlobalTimer}
                        className="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-red-500 text-white hover:bg-red-600"
                    >
                        <Square size={14} /> Stop & Log
                    </button>
                )}

                <span className="font-mono text-lg font-bold text-gray-900">{elapsedTime}</span>
                {!activeTimer && <p className="text-xs text-gray-500">Track your time</p>}
                {activeTimer && <p className="text-xs text-indigo-500 font-medium animate-pulse">Running...</p>}
            </div>

            {/* Manual log form */}
            <form onSubmit={submitTime} className="grid grid-cols-3 gap-3">
                <div>
                    <label className="block text-xs font-medium text-gray-600 mb-1">Hours</label>
                    <input
                        type="number"
                        step="0.25"
                        min="0.25"
                        value={timeForm.data.hours}
                        onChange={(e) => timeForm.setData('hours', e.target.value)}
                        className="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        placeholder="1.5"
                        required
                    />
                </div>
                <div>
                    <label className="block text-xs font-medium text-gray-600 mb-1">Date</label>
                    <input
                        type="date"
                        value={timeForm.data.logged_date}
                        onChange={(e) => timeForm.setData('logged_date', e.target.value)}
                        className="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </div>
                <div>
                    <label className="block text-xs font-medium text-gray-600 mb-1">&nbsp;</label>
                    <button 
                        type="submit" 
                        disabled={timeForm.processing} 
                        className="w-full py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700 disabled:opacity-50 font-medium"
                    >
                        Log Time
                    </button>
                </div>
                <div className="col-span-3">
                    <input
                        value={timeForm.data.description}
                        onChange={(e) => timeForm.setData('description', e.target.value)}
                        className="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        placeholder="What did you work on?"
                    />
                </div>
            </form>

            <div className="border-t border-gray-100 pt-3">
                <p className="text-xs font-medium text-gray-500 mb-2">LOGGED ({formatHours(totalLogged)} total)</p>
                {timeEntries.filter(e => !e.timer_started_at).map((entry) => (
                    <div key={entry.id} className="flex justify-between items-center py-2 border-b border-gray-50 group">
                        <div>
                            <p className="text-sm text-gray-700">{entry.description || '—'}</p>
                            <p className="text-xs text-gray-400">{entry.user?.name} · {formatDate(entry.logged_date)}</p>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="text-sm font-medium text-gray-900">{formatHours(entry.hours)}</span>
                            <button
                                type="button"
                                onClick={async () => {
                                    const ok = await confirm({
                                        title: 'Delete this time entry?',
                                        description: 'This cannot be undone.',
                                        confirmText: 'Delete',
                                        variant: 'destructive',
                                    });
                                    if (!ok) return;
                                    router.delete(`/tasks/${taskId}/time-entries/${entry.id}`, { preserveScroll: true });
                                }}
                                className="p-1 text-gray-300 hover:text-red-500 transition-colors rounded opacity-0 group-hover:opacity-100"
                            >
                                <Trash2 size={12} />
                            </button>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
};

import React from 'react';
import { useForm, router } from '@inertiajs/react';
import type { TimeEntry } from '@/types';
import { useStopwatch } from '@/hooks/useStopwatch';
import { useConfirm } from '@/hooks/useConfirm';
import { Play, Square, Trash2 } from 'lucide-react';
import { cn, formatDate, formatHours } from '@/lib/utils';

interface TimeTrackerProps {
    taskId: string;
    timeEntries: TimeEntry[];
}

export const TimeTracker: React.FC<TimeTrackerProps> = ({ taskId, timeEntries }) => {
    const confirm = useConfirm();
    const timeForm = useForm({
        hours: '',
        description: '',
        logged_date: new Date().toISOString().slice(0, 10)
    });

    const { timerRunning, timerSeconds, formattedTime, start, pause, stop, reset } = useStopwatch(taskId);

    function handleStopLog() {
        const secs = stop();
        const calculatedHours = +(secs / 3600).toFixed(2);
        if (calculatedHours < 0.01 && secs > 0) {
             timeForm.setData({
                 hours: "0.25", // Minimum increment
                 description: 'Time tracked via timer',
                 logged_date: timeForm.data.logged_date,
             });
        } else {
             timeForm.setData({
                 hours: String(Math.max(calculatedHours, 0.25)), // Enforce min 0.25h to pass validation
                 description: 'Time tracked via timer',
                 logged_date: timeForm.data.logged_date,
             });
        }
        reset();
    }

    function submitTime(e: React.FormEvent) {
        e.preventDefault();
        timeForm.post(`/tasks/${taskId}/log-time`, {
            preserveScroll: true,
            onSuccess: () => timeForm.reset(),
        });
    }

    const totalLogged = timeEntries.reduce((s, e) => s + e.hours, 0);

    return (
        <div className="space-y-4">
            {/* Timer */}
            <div className="flex items-center gap-4 p-4 bg-gray-50 rounded-lg">
                {!timerRunning && timerSeconds === 0 && (
                    <button
                        type="button"
                        onClick={start}
                        className="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-indigo-600 text-white hover:bg-indigo-700"
                    >
                        <Play size={14} /> Start Timer
                    </button>
                )}

                {timerRunning && (
                    <button
                        type="button"
                        onClick={pause}
                        className="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-amber-500 text-white hover:bg-amber-600"
                    >
                        <Square size={14} /> Pause
                    </button>
                )}

                {!timerRunning && timerSeconds > 0 && (
                    <button
                        type="button"
                        onClick={start}
                        className="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-indigo-600 text-white hover:bg-indigo-700"
                    >
                        <Play size={14} /> Resume
                    </button>
                )}

                {timerSeconds > 0 && (
                    <button
                        type="button"
                        onClick={handleStopLog}
                        className="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-red-500 text-white hover:bg-red-600"
                    >
                        <Square size={14} /> Stop & Log
                    </button>
                )}

                <span className="font-mono text-lg font-bold text-gray-900">{formattedTime}</span>
                {timerSeconds === 0 && <p className="text-xs text-gray-500">Track your time</p>}
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
                {timeEntries.map((entry) => (
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

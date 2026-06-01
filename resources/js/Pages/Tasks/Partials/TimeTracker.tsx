import React from 'react';
import { useForm, router } from '@inertiajs/react';
import type { TimeEntry } from '@/types';
import { useStopwatch } from '@/hooks/useStopwatch';
import { Play, Square, Trash2 } from 'lucide-react';
import { cn, formatDate, formatHours } from '@/lib/utils';

interface TimeTrackerProps {
    taskId: string;
    timeEntries: TimeEntry[];
}

export const TimeTracker: React.FC<TimeTrackerProps> = ({ taskId, timeEntries }) => {
    const timeForm = useForm({
        hours: '',
        description: '',
        logged_date: new Date().toISOString().slice(0, 10)
    });

    const { timerRunning, formattedTime, start, stop, reset } = useStopwatch(taskId);

    function handleToggleTimer() {
        if (timerRunning) {
            const secs = stop();
            const calculatedHours = +(secs / 3600).toFixed(2);
            timeForm.setData({
                hours: String(calculatedHours),
                description: 'Time tracked via timer',
                logged_date: timeForm.data.logged_date,
            });
            reset();
        } else {
            start();
        }
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
                <button
                    type="button"
                    onClick={handleToggleTimer}
                    className={cn(
                        'flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors',
                        timerRunning 
                            ? 'bg-red-500 text-white hover:bg-red-600' 
                            : 'bg-indigo-600 text-white hover:bg-indigo-700'
                    )}
                >
                    {timerRunning ? <><Square size={14} /> Stop</> : <><Play size={14} /> Start Timer</>}
                </button>
                <span className="font-mono text-lg font-bold text-gray-900">{formattedTime}</span>
                {!timerRunning && <p className="text-xs text-gray-500">Stop to auto-fill the form below</p>}
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
                                onClick={() => {
                                    if (confirm('Delete this time entry?')) {
                                        router.delete(`/tasks/${taskId}/time-entries/${entry.id}`, { preserveScroll: true });
                                    }
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

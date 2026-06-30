import React, { useState, useEffect, useRef } from 'react';
import { Play, Square, X, Clock } from 'lucide-react';
import axios from 'axios';
import { toast } from 'sonner';
import { usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import { PageProps } from '@/types';
import { Button } from '@/Components/ui/Button';

type TimerState = {
    active: boolean;
    startTime: number | null;
    taskId: string | null;
    taskTitle: string | null;
    projectName?: string | null;
};

const STORAGE_KEY = 'digicloudify_global_timer';

function formatDuration(ms: number) {
    const totalSeconds = Math.floor(ms / 1000);
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;
    
    return [
        hours.toString().padStart(2, '0'),
        minutes.toString().padStart(2, '0'),
        seconds.toString().padStart(2, '0')
    ].join(':');
}

export function GlobalTimer() {
    const { auth } = usePage<PageProps>().props;
    const user = auth.user;
    
    const [timer, setTimer] = useState<TimerState>({
        active: false,
        startTime: null,
        taskId: null,
        taskTitle: null
    });
    
    const [elapsed, setElapsed] = useState(0);
    const [open, setOpen] = useState(false);
    
    const [myTasks, setMyTasks] = useState<any[]>([]);
    const [loadingTasks, setLoadingTasks] = useState(false);
    
    const [description, setDescription] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const dropdownRef = useRef<HTMLDivElement>(null);

    // Load from local storage
    useEffect(() => {
        const saved = localStorage.getItem(STORAGE_KEY);
        if (saved) {
            try {
                setTimer(JSON.parse(saved));
            } catch(e) {}
        }
    }, []);

    // Save and run timer
    useEffect(() => {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(timer));
        
        let interval: any;
        if (timer.active && timer.startTime) {
            setElapsed(Date.now() - timer.startTime);
            interval = setInterval(() => {
                setElapsed(Date.now() - timer.startTime!);
            }, 1000);
        } else {
            setElapsed(0);
        }
        return () => clearInterval(interval);
    }, [timer]);

    useEffect(() => {
        function handleClickOutside(e: MouseEvent) {
            if (dropdownRef.current && !dropdownRef.current.contains(e.target as Node)) {
                setOpen(false);
            }
        }
        if (open) {
            document.addEventListener('mousedown', handleClickOutside);
            if (!timer.active && myTasks.length === 0) {
                fetchTasks();
            }
        }
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, [open, timer.active]);
    
    const fetchTasks = async () => {
        setLoadingTasks(true);
        try {
            const res = await axios.get(`/api/v1/tasks?assigned_to=${user.id}`);
            const activeTasks = (res.data.data || []).filter((t: any) => !['done', 'cancelled'].includes(t.status));
            setMyTasks(activeTasks);
        } catch (error) {
            console.error('Failed to load tasks', error);
        } finally {
            setLoadingTasks(false);
        }
    };

    const startTimer = (task: any) => {
        setTimer({
            active: true,
            startTime: Date.now(),
            taskId: task.id,
            taskTitle: task.title,
            projectName: task.project?.name
        });
    };

    const stopTimerAndLog = async () => {
        if (!timer.taskId || !timer.startTime) return;
        
        const ms = Date.now() - timer.startTime;
        let hours = ms / (1000 * 60 * 60);
        
        if (hours < 0.01) {
             hours = 0.01; 
        }

        setSubmitting(true);
        try {
            await axios.post(`/api/v1/tasks/${timer.taskId}/log-time`, {
                hours: Number(hours.toFixed(2)),
                description: description || `Worked on ${timer.taskTitle}`,
                logged_date: new Date().toISOString().split('T')[0]
            });
            toast.success('Time logged successfully!');
            setTimer({ active: false, startTime: null, taskId: null, taskTitle: null });
            setDescription('');
            setOpen(false);
        } catch (error: any) {
            toast.error(error.response?.data?.message || 'Failed to log time');
        } finally {
            setSubmitting(false);
        }
    };
    
    const cancelTimer = () => {
        setTimer({ active: false, startTime: null, taskId: null, taskTitle: null });
        setDescription('');
    };

    return (
        <div className="relative" ref={dropdownRef}>
            <button
                onClick={() => setOpen(!open)}
                className={cn(
                    "flex items-center gap-2 px-3 py-1.5 rounded-lg text-[13px] font-semibold transition-colors shadow-sm h-8",
                    timer.active 
                        ? "bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100" 
                        : "bg-white border border-gray-200 text-gray-600 hover:bg-gray-50"
                )}
            >
                {timer.active ? (
                    <>
                        <span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span className="font-mono tabular-nums">{formatDuration(elapsed)}</span>
                    </>
                ) : (
                    <>
                        <Clock size={14} className="text-gray-400" />
                        <span>Timer</span>
                    </>
                )}
            </button>

            {open && (
                <div className="absolute right-0 top-full mt-2 w-72 bg-white rounded-xl shadow-[0_10px_40px_rgba(0,0,0,0.1)] border border-gray-200 z-50 overflow-hidden flex flex-col">
                    <div className="px-4 py-3 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
                        <h3 className="text-[13px] font-bold text-gray-900">
                            {timer.active ? 'Active Timer' : 'Start a Timer'}
                        </h3>
                        <button onClick={() => setOpen(false)} className="text-gray-400 hover:text-gray-600">
                            <X size={14} />
                        </button>
                    </div>

                    <div className="p-4">
                        {timer.active ? (
                            <div className="space-y-4">
                                <div>
                                    <p className="text-[11px] font-semibold text-emerald-600 uppercase tracking-wider mb-1">Tracking Time For</p>
                                    <p className="text-[13px] font-bold text-gray-900 line-clamp-1">{timer.taskTitle}</p>
                                    {timer.projectName && (
                                        <p className="text-[11px] text-gray-500 line-clamp-1 mt-0.5">{timer.projectName}</p>
                                    )}
                                </div>
                                
                                <div className="text-3xl font-mono text-center font-bold text-gray-800 tabular-nums py-2 bg-gray-50 rounded-lg border border-gray-100">
                                    {formatDuration(elapsed)}
                                </div>

                                <div>
                                    <label className="text-xs font-medium text-gray-700 mb-1 block">What are you working on?</label>
                                    <textarea 
                                        value={description}
                                        onChange={e => setDescription(e.target.value)}
                                        className="w-full text-[13px] border-gray-200 rounded-lg focus:ring-emerald-500 focus:border-emerald-500 min-h-[60px] resize-none"
                                        placeholder="Optional description..."
                                    />
                                </div>

                                <div className="flex gap-2">
                                    <Button 
                                        onClick={stopTimerAndLog} 
                                        disabled={submitting}
                                        className="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white font-medium shadow-sm h-9"
                                    >
                                        {submitting ? 'Saving...' : 'Stop & Log'}
                                    </Button>
                                    <Button 
                                        onClick={cancelTimer}
                                        variant="outline"
                                        className="px-3 text-red-600 hover:text-red-700 hover:bg-red-50 border-red-200 h-9"
                                        title="Cancel Timer (Discard)"
                                    >
                                        <Square size={14} />
                                    </Button>
                                </div>
                            </div>
                        ) : (
                            <div className="space-y-2">
                                <p className="text-[11px] text-gray-500 font-medium px-1 uppercase tracking-wider">Select a task</p>
                                
                                <div className="max-h-[240px] overflow-y-auto pr-1 -mr-1 space-y-1 custom-scrollbar">
                                    {loadingTasks ? (
                                        <div className="text-xs text-gray-400 text-center py-6">Loading your tasks...</div>
                                    ) : myTasks.length === 0 ? (
                                        <div className="text-xs text-gray-400 text-center py-6">No active tasks assigned to you.</div>
                                    ) : (
                                        myTasks.map(task => (
                                            <button
                                                key={task.id}
                                                onClick={() => startTimer(task)}
                                                className="w-full flex items-center justify-between text-left p-2.5 rounded-lg hover:bg-indigo-50 border border-transparent hover:border-indigo-100 transition-colors group"
                                            >
                                                <div className="min-w-0 pr-2">
                                                    <p className="text-[13px] font-medium text-gray-900 truncate group-hover:text-indigo-900 transition-colors">{task.title}</p>
                                                    {task.project && (
                                                        <p className="text-[11px] text-gray-500 truncate group-hover:text-indigo-600/70 transition-colors">{task.project.name}</p>
                                                    )}
                                                </div>
                                                <Play size={14} className="text-indigo-600 opacity-0 group-hover:opacity-100 transition-opacity shrink-0" />
                                            </button>
                                        ))
                                    )}
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}

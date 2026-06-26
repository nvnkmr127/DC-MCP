import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import WorkloadLayout from '@/Layouts/WorkloadLayout';
import { Users, AlertTriangle, CheckSquare, Clock } from 'lucide-react';
import { cn } from '@/lib/utils';

interface TeamMember {
    id: string; name: string; email: string; role: string;
    total_tasks: number; urgent_tasks: number; overdue_tasks: number; due_today: number;
    total_estimated_hours: number; logged_hours_this_week: number;
    load_percent: number;
}
interface ActiveTask {
    id: string; title: string; status: string; priority: string;
    due_date: string | null; estimated_hours: number; actual_hours: number;
    assignee: { id: string; name: string } | null;
}
interface TimesheetEntry {
    id: string; user_id: string; task_title: string; hours: number;
    logged_date: string; description: string; timer_started_at: string | null;
}
interface Props {
    team: TeamMember[];
    activeTasks: ActiveTask[];
    recentTimesheets: TimesheetEntry[];
    stats: { total_active: number; unassigned: number; overloaded_count: number };
}

const PRIORITY_COLORS: Record<string, string> = {
    critical: 'bg-rose-100 text-rose-700',
    urgent: 'bg-rose-100 text-rose-700',
    high:   'bg--100 text--800',
    medium: 'bg-blue-100 text-blue-700',
    low:    'bg-gray-100 text-gray-700',
};
const STATUS_COLORS: Record<string, string> = {
    todo:       'bg-gray-100 text-gray-700',
    in_progress:'bg-blue-100 text-blue-700',
    in_review:  'bg-violet-100 text-violet-700',
};

function LoadBar({ percent }: { percent: number }) {
    const color = percent > 80 ? 'bg-rose-500' : percent > 50 ? 'bg-amber-400' : 'bg-emerald-500';
    return (
        <div className="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
            <div className={cn('h-full rounded-full transition-all', color)} style={{ width: `${percent}%` }} />
        </div>
    );
}

export default function CapacityIndex({ team, activeTasks, recentTimesheets, stats }: Props) {
    const [filterMember, setFilterMember] = useState<string>('');

    const filteredTasks = filterMember
        ? activeTasks.filter(t => t.assignee?.id === filterMember)
        : activeTasks;

    const filteredTimesheets = filterMember
        ? recentTimesheets.filter(t => t.user_id === filterMember)
        : recentTimesheets;

    return (
        <WorkloadLayout title="Team Capacity" currentTab="capacity">
            <Head title="Team Capacity" />

            <div className="space-y-6">
                {/* Stats */}
                <div className="grid grid-cols-3 gap-4">
                    {[
                        { label: 'Active Tasks', value: stats.total_active, icon: CheckSquare, color: 'text-blue-600', bg: 'bg-blue-50' },
                        { label: 'Unassigned', value: stats.unassigned, icon: Users, color: 'text-amber-600', bg: 'bg-amber-50' },
                        { label: 'Overloaded (>10)', value: stats.overloaded_count, icon: AlertTriangle, color: 'text-rose-600', bg: 'bg-rose-50' },
                    ].map(({ label, value, icon: Icon, color, bg }) => (
                        <div key={label} className="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-4">
                            <div className={cn('w-10 h-10 rounded-xl flex items-center justify-center', bg)}>
                                <Icon className={cn('w-5 h-5', color)} />
                            </div>
                            <div>
                                <p className="text-xs text-gray-500">{label}</p>
                                <p className="text-2xl font-bold text-gray-900">{value}</p>
                            </div>
                        </div>
                    ))}
                </div>

                {/* Team Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    {team.map(member => (
                        <div
                            key={member.id}
                            onClick={() => setFilterMember(filterMember === member.id ? '' : member.id)}
                            className={cn(
                                'bg-white rounded-xl border p-4 space-y-3 cursor-pointer transition-all',
                                filterMember === member.id ? 'border-indigo-400 ring-2 ring-indigo-100' : 'border-gray-200 hover:border-gray-300'
                            )}
                        >
                            <div className="flex items-center gap-3">
                                <div className="w-9 h-9 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-sm font-bold shrink-0">
                                    {member.name[0]}
                                </div>
                                <div className="min-w-0">
                                    <p className="font-semibold text-gray-900 text-sm truncate">{member.name}</p>
                                    <p className="text-xs text-gray-500 capitalize">{member.role?.replace('_', ' ') ?? '—'}</p>
                                </div>
                            </div>

                            <div className="space-y-1.5 border-b border-gray-100 pb-3">
                                <div className="flex justify-between text-xs text-gray-500">
                                    <span>Weekly Capacity (40h)</span>
                                    <span className={cn('font-semibold', member.load_percent > 80 ? 'text-rose-600' : member.load_percent > 50 ? 'text-amber-600' : 'text-emerald-600')}>
                                        {member.logged_hours_this_week}h logged
                                    </span>
                                </div>
                                <LoadBar percent={member.load_percent} />
                            </div>

                            <div className="grid grid-cols-2 gap-2 text-xs">
                                <div className="flex justify-between col-span-2">
                                    <span className="text-gray-400">Total Est. Backlog</span>
                                    <span className="font-semibold text-gray-700">{member.total_estimated_hours}h</span>
                                </div>
                                {[
                                    { label: 'Urgent', value: member.urgent_tasks, color: 'text-rose-600' },
                                    { label: 'Overdue', value: member.overdue_tasks, color: 'text-orange-600' },
                                    { label: 'Due Today', value: member.due_today, color: 'text-amber-600' },
                                    { label: 'Total', value: member.total_tasks, color: 'text-gray-700' },
                                ].map(({ label, value, color }) => (
                                    <div key={label} className="flex justify-between">
                                        <span className="text-gray-400">{label}</span>
                                        <span className={cn('font-semibold', color)}>{value}</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {/* Active Tasks List */}
                    <div className="bg-white rounded-xl border border-gray-200 flex flex-col">
                        <div className="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                            <h2 className="text-sm font-semibold text-gray-700">
                                Active Tasks {filterMember ? `— ${team.find(m => m.id === filterMember)?.name}` : '(all)'}
                            </h2>
                            {filterMember && (
                                <button onClick={() => setFilterMember('')} className="text-xs text-indigo-600 hover:text-indigo-800">
                                    Clear filter
                                </button>
                            )}
                        </div>
                        <div className="divide-y divide-gray-100 flex-1 overflow-auto max-h-[600px]">
                            {filteredTasks.length === 0 && (
                                <p className="px-5 py-8 text-center text-gray-400 text-sm">No active tasks.</p>
                            )}
                            {filteredTasks.map(task => (
                                <div key={task.id} className="px-5 py-3 flex items-center gap-4 hover:bg-gray-50 transition-colors">
                                    <div className="flex-1 min-w-0">
                                        <p className="text-sm font-medium text-gray-900 truncate">{task.title}</p>
                                        <div className="flex items-center gap-2 mt-1 text-[11px] text-gray-500">
                                            <span>Est: {task.estimated_hours}h</span>
                                            <span>•</span>
                                            <span>Logged: {task.actual_hours}h</span>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2 shrink-0">
                                        <span className={cn('px-2 py-0.5 rounded-full text-[10px] font-medium uppercase tracking-wider', PRIORITY_COLORS[task.priority] ?? 'bg-gray-100 text-gray-700')}>
                                            {task.priority}
                                        </span>
                                        <span className={cn('px-2 py-0.5 rounded-full text-[10px] font-medium uppercase tracking-wider', STATUS_COLORS[task.status] ?? 'bg-gray-100 text-gray-700')}>
                                            {task.status.replace('_', ' ')}
                                        </span>
                                        {task.due_date && (
                                            <span className={cn('flex items-center gap-1 text-xs', new Date(task.due_date) < new Date() ? 'text-rose-600' : 'text-gray-500')}>
                                                <Clock className="w-3 h-3" /> {task.due_date}
                                            </span>
                                        )}
                                        {task.assignee && (
                                            <div className="flex items-center gap-1.5 text-xs text-gray-500">
                                                <div className="w-5 h-5 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-semibold">
                                                    {task.assignee.name[0]}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Recent Timesheets */}
                    <div className="bg-white rounded-xl border border-gray-200 flex flex-col">
                        <div className="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                            <h2 className="text-sm font-semibold text-gray-700">
                                Recent Timesheets {filterMember ? `— ${team.find(m => m.id === filterMember)?.name}` : '(all)'}
                            </h2>
                        </div>
                        <div className="divide-y divide-gray-100 flex-1 overflow-auto max-h-[600px]">
                            {filteredTimesheets.length === 0 && (
                                <p className="px-5 py-8 text-center text-gray-400 text-sm">No timesheets logged this week.</p>
                            )}
                            {filteredTimesheets.map(entry => (
                                <div key={entry.id} className="px-5 py-3 flex items-center gap-4 hover:bg-gray-50 transition-colors">
                                    <div className="flex-1 min-w-0">
                                        <p className="text-sm font-medium text-gray-900 truncate">
                                            {entry.task_title ?? 'Unknown Task'}
                                        </p>
                                        <p className="text-[11px] text-gray-500 truncate mt-0.5">
                                            {entry.description || 'No description provided'}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-3 shrink-0 text-right">
                                        <div>
                                            {entry.timer_started_at ? (
                                                <span className="inline-flex items-center gap-1 text-xs font-medium text-green-600 bg-green-50 px-2 py-0.5 rounded-full">
                                                    <span className="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse" />
                                                    Running
                                                </span>
                                            ) : (
                                                <span className="text-sm font-bold text-gray-900">{entry.hours}h</span>
                                            )}
                                            <p className="text-[10px] text-gray-400 uppercase tracking-wider mt-0.5">{entry.logged_date}</p>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </WorkloadLayout>
    );
}

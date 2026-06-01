import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { cn, formatDate, dueDateLabel } from '@/lib/utils';
import type { Task, PaginatedResponse } from '@/types';
import { Plus, SlidersHorizontal, CheckSquare, AlertCircle } from 'lucide-react';
import { TASK_STATUS_DOT, TASK_PRIORITY_DOT } from '@/lib/constants';
import { StatusBadge } from '@/Components/Shared/StatusBadge';
import { Pagination } from '@/Components/ui/Pagination';

interface Props {
    tasks: PaginatedResponse<Task>;
    filters: { status?: string; priority?: string; assigned?: string; overdue?: string };
}

export default function TasksIndex({ tasks, filters }: Props) {
    const [showFilters, setShowFilters] = useState(false);
    const [selected, setSelected] = useState<string[]>([]);

    function applyFilter(key: string, val: string) {
        const current = (filters as any)[key];
        router.get('/tasks', { ...filters, [key]: val === current ? '' : val }, { preserveState: true });
    }

    function toggleSelect(id: string) {
        setSelected((prev) => prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]);
    }

    function toggleAll() {
        if (selected.length === tasks.data.length) setSelected([]);
        else setSelected(tasks.data.map((t) => t.id));
    }

    const statuses  = ['backlog', 'todo', 'in_progress', 'in_review', 'blocked', 'done'];
    const priorities = ['urgent', 'high', 'medium', 'low'];
    const activeFilterCount = [filters.status, filters.priority, filters.assigned, filters.overdue].filter(Boolean).length;

    return (
        <AppLayout title="Tasks">
            <Head title="Tasks" />

            {/* ── Toolbar ── */}
            <div className="flex items-center justify-between gap-3 mb-4">
                <div className="flex items-center gap-2">
                    {/* Filter toggle */}
                    <button
                        onClick={() => setShowFilters(!showFilters)}
                        className={cn(
                            'flex items-center gap-2 px-3 py-1.5 text-[13px] font-medium border rounded-lg transition-colors',
                            showFilters || activeFilterCount > 0
                                ? 'bg-indigo-50 text-indigo-700 border-indigo-200'
                                : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50',
                        )}
                    >
                        <SlidersHorizontal size={13} />
                        Filters
                        {activeFilterCount > 0 && (
                            <span className="min-w-[18px] h-[18px] text-[10px] font-bold bg-indigo-600 text-white rounded-full flex items-center justify-center px-1">
                                {activeFilterCount}
                            </span>
                        )}
                    </button>

                    {/* Active filter chips */}
                    {filters.assigned === 'me' && (
                        <button
                            onClick={() => applyFilter('assigned', 'me')}
                            className="flex items-center gap-1 px-2.5 py-1 bg-indigo-100 text-indigo-700 text-[11px] font-semibold rounded-full hover:bg-indigo-200 transition-colors"
                        >
                            Assigned to me ×
                        </button>
                    )}
                    {filters.overdue === '1' && (
                        <button
                            onClick={() => applyFilter('overdue', '1')}
                            className="flex items-center gap-1.5 px-2.5 py-1 bg-red-100 text-red-700 text-[11px] font-semibold rounded-full hover:bg-red-200 transition-colors"
                        >
                            <AlertCircle size={10} /> Overdue ×
                        </button>
                    )}

                    {/* Bulk actions */}
                    {selected.length > 0 && (
                        <div className="flex items-center gap-2 ml-1 pl-3 border-l border-gray-200">
                            <span className="text-[12px] text-gray-500">{selected.length} selected</span>
                            <button className="px-2.5 py-1 text-[12px] font-medium bg-white border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50 transition-colors">
                                Update status
                            </button>
                        </div>
                    )}
                </div>

                <Link
                    href="/tasks/create"
                    className="flex items-center gap-1.5 px-4 py-2 bg-indigo-600 text-white text-[13px] font-semibold rounded-lg hover:bg-indigo-700 transition-colors shadow-sm"
                >
                    <Plus size={14} /> New Task
                </Link>
            </div>

            {/* ── Filter panel ── */}
            {showFilters && (
                <div className="bg-white border border-gray-100 rounded-xl p-4 mb-4 shadow-sm">
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <p className="text-[10px] font-semibold uppercase tracking-widest text-gray-400 mb-2">Status</p>
                            <div className="flex flex-wrap gap-1.5">
                                {statuses.map((s) => (
                                    <button
                                        key={s}
                                        onClick={() => applyFilter('status', s)}
                                        className={cn(
                                            'flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-medium capitalize transition-colors border',
                                            filters.status === s
                                                ? 'bg-indigo-600 text-white border-indigo-600'
                                                : 'bg-white text-gray-600 border-gray-200 hover:border-indigo-200 hover:text-indigo-600',
                                        )}
                                    >
                                        <span className={cn('w-1.5 h-1.5 rounded-full', TASK_STATUS_DOT[s])} />
                                        {s.replace(/_/g, ' ')}
                                    </button>
                                ))}
                            </div>
                        </div>
                        <div>
                            <p className="text-[10px] font-semibold uppercase tracking-widest text-gray-400 mb-2">Priority</p>
                            <div className="flex flex-wrap gap-1.5">
                                {priorities.map((p) => (
                                    <button
                                        key={p}
                                        onClick={() => applyFilter('priority', p)}
                                        className={cn(
                                            'flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-medium capitalize transition-colors border',
                                            filters.priority === p
                                                ? 'bg-indigo-600 text-white border-indigo-600'
                                                : 'bg-white text-gray-600 border-gray-200 hover:border-indigo-200 hover:text-indigo-600',
                                        )}
                                    >
                                        <span className={cn('w-1.5 h-1.5 rounded-full', TASK_PRIORITY_DOT[p])} />
                                        {p}
                                    </button>
                                ))}
                            </div>
                        </div>
                        <div>
                            <p className="text-[10px] font-semibold uppercase tracking-widest text-gray-400 mb-2">Quick filters</p>
                            <div className="flex flex-wrap gap-1.5">
                                <button
                                    onClick={() => applyFilter('assigned', 'me')}
                                    className={cn(
                                        'px-2.5 py-1 rounded-lg text-[11px] font-medium border transition-colors',
                                        filters.assigned === 'me'
                                            ? 'bg-indigo-600 text-white border-indigo-600'
                                            : 'bg-white text-gray-600 border-gray-200 hover:border-indigo-200',
                                    )}
                                >
                                    Assigned to me
                                </button>
                                <button
                                    onClick={() => applyFilter('overdue', '1')}
                                    className={cn(
                                        'px-2.5 py-1 rounded-lg text-[11px] font-medium border transition-colors',
                                        filters.overdue === '1'
                                            ? 'bg-red-600 text-white border-red-600'
                                            : 'bg-white text-gray-600 border-gray-200 hover:border-red-200',
                                    )}
                                >
                                    Overdue only
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* ── Table ── */}
            <div className="bg-white rounded-xl border border-gray-100 overflow-hidden shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
                {tasks.data.length === 0 ? (
                    <div className="p-16 text-center">
                        <div className="w-12 h-12 rounded-xl bg-gray-50 flex items-center justify-center mx-auto mb-3">
                            <CheckSquare size={20} className="text-gray-300" />
                        </div>
                        <p className="text-[13px] font-medium text-gray-600 mb-1">No tasks match your filters</p>
                        <p className="text-[12px] text-gray-400">Try adjusting or clearing your filters</p>
                    </div>
                ) : (
                    <table className="w-full text-[13px]">
                        <thead>
                            <tr className="border-b border-gray-100">
                                <th className="w-9 px-4 py-3">
                                    <input
                                        type="checkbox"
                                        checked={selected.length === tasks.data.length && tasks.data.length > 0}
                                        onChange={toggleAll}
                                        className="w-3.5 h-3.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                                    />
                                </th>
                                {['Task', 'Project', 'Status', 'Priority', 'Assignee', 'Due', 'Est.'].map((h) => (
                                    <th key={h} className="text-left px-3 py-3 text-[11px] font-semibold text-gray-400 uppercase tracking-wide">
                                        {h}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {tasks.data.map((task) => {
                                const due = dueDateLabel(task.due_date);
                                const isSelected = selected.includes(task.id);
                                return (
                                    <tr
                                        key={task.id}
                                        className={cn(
                                            'border-b border-gray-50 transition-colors hover:bg-gray-50/60',
                                            isSelected && 'bg-indigo-50/40',
                                        )}
                                    >
                                        <td className="px-4 py-3">
                                            <input
                                                type="checkbox"
                                                checked={isSelected}
                                                onChange={() => toggleSelect(task.id)}
                                                className="w-3.5 h-3.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                                            />
                                        </td>
                                        <td className="px-3 py-3 max-w-[240px]">
                                            <Link
                                                href={`/tasks/${task.id}`}
                                                className="font-medium text-gray-900 hover:text-indigo-600 transition-colors line-clamp-1 block"
                                            >
                                                {task.title}
                                            </Link>
                                        </td>
                                        <td className="px-3 py-3 text-gray-400 text-[12px] max-w-[120px] truncate">
                                            {task.project?.name ?? '—'}
                                        </td>
                                        <td className="px-3 py-3">
                                            <StatusBadge type="task-status" value={task.status} />
                                        </td>
                                        <td className="px-3 py-3">
                                            <StatusBadge type="task-priority" value={task.priority} />
                                        </td>
                                        <td className="px-3 py-3 text-[12px] text-gray-500 whitespace-nowrap">
                                            {task.assignee?.name ?? <span className="text-gray-300">Unassigned</span>}
                                        </td>
                                        <td className="px-3 py-3 whitespace-nowrap">
                                            {task.due_date ? (
                                                <span className={cn(
                                                    'text-[12px] font-medium',
                                                    due.variant === 'destructive' ? 'text-red-600' :
                                                    due.variant === 'warning' ? 'text-yellow-600' : 'text-gray-500',
                                                )}>
                                                    {formatDate(task.due_date)}
                                                </span>
                                            ) : <span className="text-gray-300 text-[12px]">—</span>}
                                        </td>
                                        <td className="px-3 py-3 text-[12px] text-gray-400">
                                            {task.estimated_hours > 0 ? `${task.estimated_hours}h` : '—'}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                )}

                {/* Pagination */}
                <Pagination
                    meta={tasks.meta}
                    onPageChange={(page) => router.get('/tasks', { ...filters, page }, { preserveState: true })}
                    labelSingular="task"
                    labelPlural="tasks"
                />
            </div>
        </AppLayout>
    );
}

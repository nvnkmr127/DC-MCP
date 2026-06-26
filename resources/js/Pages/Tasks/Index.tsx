import React, { useState, useEffect, useCallback } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import WorkloadLayout from '@/Layouts/WorkloadLayout';
import { useConfirm } from '@/hooks/useConfirm';
import { cn, formatDate, dueDateLabel } from '@/lib/utils';
import type { Task, PaginatedResponse, User, Project } from '@/types';
import { Plus, SlidersHorizontal, CheckSquare, AlertCircle, Download, X, ArrowUp, ArrowDown, ArrowUpDown } from 'lucide-react';
import { TASK_STATUS_DOT, TASK_PRIORITY_DOT } from '@/lib/constants';
import { StatusBadge } from '@/Components/Shared/StatusBadge';
import { Pagination } from '@/Components/ui/Pagination';

interface Props {
    tasks: PaginatedResponse<Task>;
    members?: User[];
    projects?: Project[];
    filters: { status?: string; priority?: string; assigned?: string; overdue?: string; sort?: string };
}

export default function TasksIndex({ tasks, members = [], projects = [], filters = {} }: Props) {
    const [showFilters, setShowFilters] = useState(false);
    const [selected, setSelected] = useState<string[]>([]);
    
    const currentFilters = {
        status: filters.status ? filters.status.split(',') : [],
        priority: filters.priority ? filters.priority.split(',') : [],
        assigned: filters.assigned ? filters.assigned.split(',') : [],
        overdue: filters.overdue,
        sort: filters.sort ? filters.sort.split(',') : []
    };

    const confirm = useConfirm();

    useEffect(() => {
        const hasParams = window.location.search.length > 0;
        if (!hasParams) {
            const saved = localStorage.getItem('tasks_filters');
            if (saved) {
                try {
                    const parsed = JSON.parse(saved);
                    if (Object.keys(parsed).length > 0) {
                        router.get('/tasks', parsed, { preserveState: true, replace: true });
                    }
                } catch (e) {}
            }
        }
    }, []);

    useEffect(() => {
        if (window.location.search.length > 0) {
            localStorage.setItem('tasks_filters', JSON.stringify(filters));
        } else if (Object.keys(filters).length === 0) {
             localStorage.removeItem('tasks_filters');
        }
    }, [filters]);

    const applyFilters = (newFilters: any) => {
        router.get('/tasks', newFilters, { preserveState: true, preserveScroll: true });
    };

    const toggleArrayFilter = (key: 'status' | 'priority' | 'assigned', val: string) => {
        const currentArr = currentFilters[key];
        const newArr = currentArr.includes(val) ? currentArr.filter(x => x !== val) : [...currentArr, val];
        applyFilters({ ...filters, [key]: newArr.join(',') || undefined, page: 1 });
    };

    const toggleSingleFilter = (key: 'overdue', val: string | undefined) => {
        applyFilters({ ...filters, [key]: val, page: 1 });
    };

    const toggleSort = (column: string, multi: boolean) => {
        let sorts = [...currentFilters.sort];
        const existingIdx = sorts.findIndex(s => s.startsWith(column + ':'));
        
        let newDir = 'asc';
        if (existingIdx >= 0) {
            const currentDir = sorts[existingIdx].split(':')[1];
            if (currentDir === 'asc') {
                newDir = 'desc';
            } else {
                newDir = ''; 
            }
        }

        if (!multi) {
            sorts = []; 
        } else if (existingIdx >= 0) {
            sorts.splice(existingIdx, 1); 
        }

        if (newDir) {
            sorts.push(`${column}:${newDir}`);
        }

        applyFilters({ ...filters, sort: sorts.join(',') || undefined, page: 1 });
    };

    const clearFilters = () => {
        applyFilters({}); 
        localStorage.removeItem('tasks_filters');
    };

    const getSortIndicator = (column: string) => {
        const sortParam = currentFilters.sort.find(s => s.startsWith(column + ':'));
        if (!sortParam) return <ArrowUpDown size={12} className="text-gray-300 ml-1 inline opacity-0 group-hover:opacity-100 transition-opacity" />;
        const dir = sortParam.split(':')[1];
        return dir === 'asc' 
            ? <ArrowUp size={12} className="text-indigo-600 ml-1 inline" /> 
            : <ArrowDown size={12} className="text-indigo-600 ml-1 inline" />;
    };

    const SortableHeader = ({ column, label }: { column: string, label: string }) => (
        <th 
            className="text-left px-3 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wide cursor-pointer hover:bg-gray-100 select-none transition-colors group"
            onClick={(e) => toggleSort(column, e.shiftKey)}
            title="Click to sort, Shift+Click to multi-sort"
        >
            <div className="flex items-center">
                {label}
                {getSortIndicator(column)}
            </div>
        </th>
    );

    function toggleSelect(id: string) {
        setSelected((prev) => prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]);
    }
    function toggleAll() {
        if (selected.length === tasks.data.length) setSelected([]);
        else setSelected(tasks.data.map((t) => t.id));
    }
    async function handleBulkDelete() {
        const ok = await confirm({
            title: 'Delete tasks?',
            description: `Are you sure you want to delete ${selected.length} task(s)? This cannot be undone.`,
            confirmText: 'Delete',
            variant: 'destructive',
        });
        if (!ok) return;
        router.delete('/tasks/bulk-destroy', {
            data: { task_ids: selected },
            onSuccess: () => setSelected([]),
        });
    }
    function handleBulkUpdate(field: string, value: string) {
        router.post('/tasks/bulk-update', {
            task_ids: selected,
            [field]: value
        }, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => setSelected([]),
        });
    }

    const statuses  = ['backlog', 'todo', 'in_progress', 'in_review', 'blocked', 'done'];
    const priorities = ['critical', 'high', 'medium', 'low'];
    const activeFilterCount = currentFilters.status.length + currentFilters.priority.length + currentFilters.assigned.length + (currentFilters.overdue ? 1 : 0);

    return (
        <WorkloadLayout title="Tasks" currentTab="tasks">
            <Head title="Tasks" />
            <div className="flex items-center justify-between gap-3 mb-4">
                <div className="flex items-center gap-2 flex-wrap">
                    <button
                        onClick={() => setShowFilters(!showFilters)}
                        className={cn(
                            'flex items-center gap-2 px-3 py-1.5 text-[13px] font-medium border rounded-lg transition-colors shadow-sm',
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
                    
                    {activeFilterCount > 0 && (
                        <button 
                            onClick={clearFilters}
                            className="text-[12px] font-medium text-gray-500 hover:text-gray-900 px-2 flex items-center gap-1 transition-colors"
                        >
                            <X size={12} /> Clear all
                        </button>
                    )}

                    {selected.length > 0 && (
                        <div className="flex items-center gap-2 ml-1 pl-3 border-l border-gray-200">
                            <span className="text-[12px] text-gray-500">{selected.length} selected</span>
                            <select 
                                onChange={(e) => handleBulkUpdate('status', e.target.value)}
                                value=""
                                className="px-2 py-1 bg-white border border-gray-200 text-[11px] rounded hover:bg-gray-50 outline-none text-gray-600"
                            >
                                <option value="" disabled>Set Status...</option>
                                {statuses.map(s => <option key={s} value={s}>{s.replace('_', ' ')}</option>)}
                            </select>
                            <button
                                onClick={handleBulkDelete}
                                className="px-2.5 py-1 bg-red-600 text-white text-[11px] font-semibold rounded hover:bg-red-700 transition-colors"
                            >
                                Delete
                            </button>
                        </div>
                    )}
                </div>

                <div className="flex items-center gap-2">
                    <a 
                        href={`/tasks/export${typeof window !== 'undefined' ? window.location.search : ''}`} 
                        className="flex items-center gap-1.5 px-3 py-2 bg-white border border-gray-200 text-gray-700 text-[13px] font-semibold rounded-lg hover:bg-gray-50 transition-colors shadow-sm"
                    >
                        <Download size={14} /> Export CSV
                    </a>
                    <Link href="/tasks/create" className="flex items-center gap-1.5 px-4 py-2 bg-indigo-600 text-white text-[13px] font-semibold rounded-lg hover:bg-indigo-700 transition-colors shadow-sm">
                        <Plus size={14} /> New Task
                    </Link>
                </div>
            </div>

            {showFilters && (
                <div className="bg-white border border-gray-100 rounded-xl p-5 mb-4 shadow-[0_1px_3px_rgba(0,0,0,0.04)] animate-in slide-in-from-top-2 duration-200">
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <div>
                            <p className="text-[11px] font-semibold uppercase tracking-widest text-gray-500 mb-3">Status</p>
                            <div className="flex flex-col gap-2">
                                {statuses.map((s) => (
                                    <label key={s} className="flex items-center gap-2 cursor-pointer group">
                                        <input 
                                            type="checkbox" 
                                            checked={currentFilters.status.includes(s)}
                                            onChange={() => toggleArrayFilter('status', s)}
                                            className="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                                        />
                                        <div className="flex items-center gap-1.5 text-[13px] text-gray-700 group-hover:text-gray-900 transition-colors capitalize">
                                            <span className={cn('w-2 h-2 rounded-full', TASK_STATUS_DOT[s])} />
                                            {s.replace(/_/g, ' ')}
                                        </div>
                                    </label>
                                ))}
                            </div>
                        </div>
                        <div>
                            <p className="text-[11px] font-semibold uppercase tracking-widest text-gray-500 mb-3">Priority</p>
                            <div className="flex flex-col gap-2">
                                {priorities.map((p) => (
                                    <label key={p} className="flex items-center gap-2 cursor-pointer group">
                                        <input 
                                            type="checkbox" 
                                            checked={currentFilters.priority.includes(p)}
                                            onChange={() => toggleArrayFilter('priority', p)}
                                            className="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                                        />
                                        <div className="flex items-center gap-1.5 text-[13px] text-gray-700 group-hover:text-gray-900 transition-colors capitalize">
                                            <span className={cn('w-2 h-2 rounded-full', TASK_PRIORITY_DOT[p])} />
                                            {p}
                                        </div>
                                    </label>
                                ))}
                            </div>
                        </div>
                        <div>
                            <p className="text-[11px] font-semibold uppercase tracking-widest text-gray-500 mb-3">Assignee</p>
                            <div className="flex flex-col gap-2 max-h-[160px] overflow-y-auto pr-2 scrollbar-thin">
                                <label className="flex items-center gap-2 cursor-pointer group">
                                    <input 
                                        type="checkbox" 
                                        checked={currentFilters.assigned.includes('me')}
                                        onChange={() => toggleArrayFilter('assigned', 'me')}
                                        className="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                                    />
                                    <span className="text-[13px] font-medium text-indigo-600 group-hover:text-indigo-800 transition-colors">Assigned to Me</span>
                                </label>
                                <label className="flex items-center gap-2 cursor-pointer group">
                                    <input 
                                        type="checkbox" 
                                        checked={currentFilters.assigned.includes('unassigned')}
                                        onChange={() => toggleArrayFilter('assigned', 'unassigned')}
                                        className="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                                    />
                                    <span className="text-[13px] text-gray-500 italic group-hover:text-gray-700 transition-colors">Unassigned</span>
                                </label>
                                <div className="h-px bg-gray-100 my-1 w-full" />
                                {members?.map((m) => (
                                    <label key={m.id} className="flex items-center gap-2 cursor-pointer group">
                                        <input 
                                            type="checkbox" 
                                            checked={currentFilters.assigned.includes(m.id)}
                                            onChange={() => toggleArrayFilter('assigned', m.id)}
                                            className="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                                        />
                                        <span className="text-[13px] text-gray-700 group-hover:text-gray-900 transition-colors truncate">{m.name}</span>
                                    </label>
                                ))}
                            </div>
                        </div>
                        <div>
                            <p className="text-[11px] font-semibold uppercase tracking-widest text-gray-500 mb-3">Quick Filters</p>
                            <label className="flex items-center gap-2 cursor-pointer group bg-red-50 hover:bg-red-100 border border-red-100 p-2.5 rounded-lg transition-colors">
                                <input 
                                    type="checkbox" 
                                    checked={currentFilters.overdue === '1'}
                                    onChange={() => toggleSingleFilter('overdue', currentFilters.overdue ? undefined : '1')}
                                    className="w-4 h-4 rounded border-red-300 text-red-600 focus:ring-red-500 cursor-pointer"
                                />
                                <div className="flex items-center gap-1.5 text-[13px] font-medium text-red-700">
                                    <AlertCircle size={14} /> Overdue Only
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
            )}

            <div className="bg-white rounded-xl border border-gray-100 overflow-hidden shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
                {tasks.data.length === 0 ? (
                    <div className="p-16 text-center">
                        <div className="w-12 h-12 rounded-xl bg-gray-50 flex items-center justify-center mx-auto mb-3">
                            <CheckSquare size={20} className="text-gray-300" />
                        </div>
                        <p className="text-[13px] font-medium text-gray-600 mb-1">No tasks match your filters</p>
                        <p className="text-[12px] text-gray-400">Try adjusting or clearing your filters to see more results</p>
                        {activeFilterCount > 0 && (
                            <button onClick={clearFilters} className="mt-4 px-4 py-2 bg-white border border-gray-200 rounded-lg text-[13px] font-medium text-gray-600 hover:bg-gray-50 transition-colors shadow-sm">
                                Clear all filters
                            </button>
                        )}
                    </div>
                ) : (
                    <table className="w-full text-[13px]">
                        <thead>
                            <tr className="border-b border-gray-200 bg-gray-50/50">
                                <th className="w-9 px-4 py-3">
                                    <input
                                        type="checkbox"
                                        checked={selected.length === tasks.data.length && tasks.data.length > 0}
                                        onChange={toggleAll}
                                        className="w-3.5 h-3.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                                    />
                                </th>
                                <SortableHeader column="title" label="Task" />
                                <th className="text-left px-3 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wide">Project</th>
                                <SortableHeader column="status" label="Status" />
                                <SortableHeader column="priority" label="Priority" />
                                <th className="text-left px-3 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-wide">Assignee</th>
                                <SortableHeader column="due_date" label="Due" />
                                <SortableHeader column="estimated_hours" label="Est." />
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
                                            <Link href={`/tasks/${task.id}`} className="font-medium text-gray-900 hover:text-indigo-600 transition-colors line-clamp-1 block">
                                                {task.title}
                                            </Link>
                                        </td>
                                        <td className="px-3 py-3 text-gray-400 text-[12px] max-w-[120px] truncate">
                                            {task.project?.name ?? '—'}
                                        </td>
                                        <td className="px-3 py-3"><StatusBadge type="task-status" value={task.status} /></td>
                                        <td className="px-3 py-3"><StatusBadge type="task-priority" value={task.priority} /></td>
                                        <td className="px-3 py-3 text-[12px] text-gray-500 whitespace-nowrap">
                                            {task.assignee?.name ?? <span className="text-gray-300">Unassigned</span>}
                                        </td>
                                        <td className="px-3 py-3 whitespace-nowrap">
                                            {task.due_date ? (
                                                <span className={cn('text-[12px] font-medium', due.variant === 'destructive' ? 'text-red-600' : due.variant === 'warning' ? 'text-yellow-600' : 'text-gray-500')}>
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

                <Pagination
                    meta={tasks.meta}
                    onPageChange={(page) => applyFilters({ ...filters, page })}
                    labelSingular="task"
                    labelPlural="tasks"
                />
            </div>
        </WorkloadLayout>
    );
}

import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useConfirm } from '@/hooks/useConfirm';
import { cn, formatDate, formatCurrency, TASK_STATUS_COLORS, PRIORITY_COLORS } from '@/lib/utils';
import type { Project, Task } from '@/types';
import { Kanban, Plus, BarChart, Edit, ArrowLeft, Trash2, Activity, AlertTriangle } from 'lucide-react';
import { ActivityLog } from '@/Components/Shared/ActivityLog';
import { Breadcrumbs } from '@/Components/Shared/Breadcrumbs';

interface Props {
    project: Project & {
        completion_pct: number;
        total_tasks?: number;
        completed_tasks?: number;
        client?: { id: string; name: string } | null;
        manager?: { id: string; name: string } | null;
        milestones?: Array<{ id: string; name: string; due_date: string; status: string }>;
        sprints?: Array<{ id: string; name: string; status: string; start_date: string; end_date: string }>;
        activities?: any[];
    };
}

const STATUS_STYLES: Record<string, string> = {
    planning:  'bg-gray-100 text-gray-700',
    active:    'bg-green-100 text-green-700',
    on_hold:   'bg--100 text--800',
    completed: 'bg-blue-100 text-blue-700',
    cancelled: 'bg-red-100 text-red-700',
};

export default function ProjectShow({ project }: Props) {
    const confirm = useConfirm();
    return (
        <AppLayout>
            <Head title={project.name} />

            <div className="max-w-5xl mx-auto">
                {/* Breadcrumb */}
                <div className="mb-4">
                    <Breadcrumbs items={[
                        { label: 'Projects', href: '/projects' },
                        { label: project.name }
                    ]} />
                </div>

                {/* Budget Burn Rate Alert */}
                {project.budget > 0 && (project.budget_used / project.budget) >= 0.9 && (
                    <div className="bg-rose-50 border border-rose-200 rounded-xl px-4 py-3 mb-4 flex items-start gap-3">
                        <AlertTriangle size={18} className="text-rose-600 shrink-0 mt-0.5" />
                        <div>
                            <h3 className="text-sm font-semibold text-rose-800">High Budget Utilization Alert</h3>
                            <p className="text-sm text-rose-600 mt-0.5">
                                This project has consumed {Math.round((project.budget_used / project.budget) * 100)}% of its allocated budget ({formatCurrency(project.budget_used)} of {formatCurrency(project.budget)}). Please review the remaining scope.
                            </p>
                        </div>
                    </div>
                )}

                {/* Header */}
                <div className="bg-white rounded-xl border border-gray-200 p-6 mb-4">
                    <div className="flex items-start justify-between">
                        <div className="flex-1">
                            <div className="flex items-center gap-3 mb-1">
                                <h1 className="text-xl font-bold text-gray-900">{project.name}</h1>
                                <span className={cn('px-2 py-0.5 rounded-full text-xs font-medium capitalize', STATUS_STYLES[project.status])}>
                                    {project.status.replace('_', ' ')}
                                </span>
                            </div>
                            {project.client && (
                                <Link href={`/clients/${project.client.id}`} className="text-sm text-indigo-600 hover:underline">
                                    {project.client.name}
                                </Link>
                            )}
                            {project.description && (
                                <p className="text-sm text-gray-600 mt-2 leading-relaxed">{project.description}</p>
                            )}
                        </div>
                        <div className="flex items-center gap-2 ml-4">
                            <Link href={`/projects/${project.id}/kanban`} className="flex items-center gap-1.5 px-3 py-1.5 text-sm border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50">
                                <Kanban size={14} /> Kanban
                            </Link>
                            <Link href={`/projects/${project.id}/stats`} className="flex items-center gap-1.5 px-3 py-1.5 text-sm border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50">
                                <BarChart size={14} /> Stats
                            </Link>
                            <Link href={`/projects/${project.id}/edit`} className="flex items-center gap-1.5 px-3 py-1.5 text-sm border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50">
                                <Edit size={14} /> Edit
                            </Link>
                            <button
                                onClick={async () => {
                                    const openTasks = (project.total_tasks ?? 0) - (project.completed_tasks ?? 0);
                                    const description = openTasks > 0
                                        ? `This project has ${openTasks} open task${openTasks === 1 ? '' : 's'}. Are you sure? All tasks will be deleted and this cannot be undone.`
                                        : 'All tasks will also be deleted. This cannot be undone.';

                                    const ok = await confirm({
                                        title: 'Delete this project?',
                                        description,
                                        confirmText: 'Delete',
                                        variant: 'destructive',
                                    });
                                    if (!ok) return;
                                    router.delete(`/projects/${project.id}`);
                                }}
                                className="flex items-center gap-1.5 px-3 py-1.5 text-sm border border-red-200 rounded-lg text-red-600 hover:bg-red-50"
                            >
                                <Trash2 size={14} /> Delete
                            </button>
                            <Link href={`/tasks/create?project_id=${project.id}`} className="flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700">
                                <Plus size={14} /> Add Task
                            </Link>
                        </div>
                    </div>

                    {/* Progress */}
                    <div className="mt-4">
                        <div className="flex justify-between text-xs text-gray-500 mb-1.5">
                            <span>{project.completed_tasks ?? 0}/{project.total_tasks ?? 0} tasks completed</span>
                            <span className="font-medium">{project.completion_pct}%</span>
                        </div>
                        <div className="h-2 bg-gray-100 rounded-full">
                            <div className="h-full bg-indigo-500 rounded-full transition-all" style={{ width: `${project.completion_pct}%` }} />
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    {/* Tasks (tasks handled separately in a full implementation) */}
                    <div className="lg:col-span-2">
                        <div className="bg-white rounded-xl border border-gray-200 p-5">
                            <div className="flex items-center justify-between mb-3">
                                <h3 className="text-sm font-semibold text-gray-900">Quick Actions</h3>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <Link href={`/tasks?project_id=${project.id}`} className="px-3 py-2 bg-indigo-50 text-indigo-700 text-sm rounded-lg hover:bg-indigo-100">
                                    View All Tasks
                                </Link>
                                <Link href={`/projects/${project.id}/kanban`} className="px-3 py-2 bg-gray-50 text-gray-700 text-sm rounded-lg hover:bg-gray-100">
                                    Kanban Board
                                </Link>
                                <Link href={`/tasks/create?project_id=${project.id}&status=todo`} className="px-3 py-2 bg-gray-50 text-gray-700 text-sm rounded-lg hover:bg-gray-100">
                                    + Add Task
                                </Link>
                            </div>
                        </div>

                        {/* Activities */}
                        <div className="bg-white rounded-xl border border-gray-200 p-5 mt-4">
                            <div className="flex items-center gap-2 mb-4 pb-3 border-b border-gray-100">
                                <Activity size={16} className="text-gray-400" />
                                <h3 className="text-sm font-semibold text-gray-900">Project Activity</h3>
                            </div>
                            <ActivityLog activities={project.activities || []} />
                        </div>
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-4">
                        <div className="bg-white rounded-xl border border-gray-200 p-4">
                            <h3 className="text-sm font-semibold text-gray-900 mb-3">Details</h3>
                            <dl className="space-y-2 text-sm">
                                <div>
                                    <dt className="text-xs text-gray-500 mb-0.5">Priority</dt>
                                    <dd className={cn('inline-flex px-2 py-0.5 rounded-full text-xs font-medium capitalize', PRIORITY_COLORS[project.priority])}>
                                        {project.priority}
                                    </dd>
                                </div>
                                {project.manager && (
                                    <div>
                                        <dt className="text-xs text-gray-500 mb-0.5">Manager</dt>
                                        <dd className="font-medium text-gray-900">{project.manager.name}</dd>
                                    </div>
                                )}
                                {project.start_date && (
                                    <div>
                                        <dt className="text-xs text-gray-500 mb-0.5">Start Date</dt>
                                        <dd className="text-gray-700">{formatDate(project.start_date)}</dd>
                                    </div>
                                )}
                                {project.end_date && (
                                    <div>
                                        <dt className="text-xs text-gray-500 mb-0.5">Deadline</dt>
                                        <dd className="text-gray-700">{formatDate(project.end_date)}</dd>
                                    </div>
                                )}
                                {project.budget > 0 && (
                                    <div>
                                        <dt className="text-xs text-gray-500 mb-0.5">Budget</dt>
                                        <dd className="text-gray-700">
                                            {formatCurrency(project.budget_used)} / {formatCurrency(project.budget)}
                                        </dd>
                                    </div>
                                )}
                            </dl>
                        </div>

                        {/* Milestones */}
                        {project.milestones && project.milestones.length > 0 && (
                            <div className="bg-white rounded-xl border border-gray-200 p-4">
                                <h3 className="text-sm font-semibold text-gray-900 mb-3">Milestones</h3>
                                <div className="space-y-2">
                                    {project.milestones.map(m => (
                                        <div key={m.id} className="flex items-center justify-between">
                                            <span className="text-sm text-gray-700 truncate">{m.name}</span>
                                            <span className="text-xs text-gray-400 shrink-0 ml-2">{formatDate(m.due_date)}</span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

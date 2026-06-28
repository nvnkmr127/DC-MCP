import React, { useState } from 'react';
import { Button } from '@/Components/ui/Button';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useConfirm } from '@/hooks/useConfirm';
import { cn, formatDate, formatCurrency, PRIORITY_COLORS } from '@/lib/utils';
import type { Project, Task } from '@/types';
import { Kanban, Plus, BarChart, Edit, ArrowLeft, Trash2, Activity, AlertTriangle, DollarSign, GripVertical, CheckCircle2 } from 'lucide-react';
import { ActivityLog } from '@/Components/Shared/ActivityLog';
import { Breadcrumbs } from '@/Components/Shared/Breadcrumbs';

// Tabs
import KanbanTab from './Tabs/KanbanTab';
import MilestonesTab from './Tabs/MilestonesTab';
import BudgetTab from './Tabs/BudgetTab';

interface Props {
    project: Project & {
        completion_pct: number;
        total_tasks?: number;
        completed_tasks?: number;
        total_logged_hours?: number;
        client?: { id: string; name: string } | null;
        manager?: { id: string; name: string } | null;
        milestones?: Array<any>;
        sprints?: Array<any>;
        issues?: Array<any>;
        assets?: Array<any>;
        activities?: any[];
    };
    tasks: Task[];
    goals: any[];
    team: any[];
    financials: any;
    invoices: any[];
    expenses: any[];
    campaignBudgets: any[];
    retainers: any[];
}

const STATUS_STYLES: Record<string, string> = {
    planning:  'bg-gray-100 text-gray-700',
    active:    'bg-green-100 text-green-700',
    on_hold:   'bg-yellow-100 text-yellow-800',
    completed: 'bg-blue-100 text-blue-700',
    cancelled: 'bg-red-100 text-red-700',
};

const TABS = [
    { id: 'tasks', label: 'Tasks' },
    { id: 'sprints', label: 'Sprints' },
    { id: 'issues', label: 'Issues' },
    { id: 'milestones', label: 'Milestones' },
    { id: 'assets', label: 'Assets' },
    { id: 'budget', label: 'Budget' },
    { id: 'team', label: 'Team' },
    { id: 'settings', label: 'Settings' }
] as const;
export default function ProjectShow({ project, tasks, goals, team, financials, invoices, expenses, campaignBudgets, retainers }: Props) {
    const confirm = useConfirm();
    const [activeTab, setActiveTab] = useState<typeof TABS[number]['id']>('tasks');

    return (
        <AppLayout>
            <Head title={project.name} />

            <div className="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
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
                        <AlertTriangle size={20} className="text-rose-600 shrink-0 mt-0.5" />
                        <div>
                            <h3 className="text-sm font-semibold text-rose-800">High Budget Utilization Alert</h3>
                            <p className="text-sm text-rose-600 mt-0.5">
                                This project has consumed {Math.round((project.budget_used / project.budget) * 100)}% of its allocated budget ({formatCurrency(project.budget_used)} of {formatCurrency(project.budget)}). Please review the remaining scope.
                            </p>
                        </div>
                    </div>
                )}

                {/* Header */}
                <div className="bg-white rounded-xl border border-gray-200 p-6 mb-6">
                    <div className="flex items-start justify-between">
                        <div className="flex-1">
                            <div className="flex items-center gap-3 mb-1">
                                <h1 className="text-2xl font-bold text-gray-900">{project.name}</h1>
                                <span className={cn('px-2.5 py-0.5 rounded-full text-xs font-medium capitalize', STATUS_STYLES[project.status])}>
                                    {project.status.replace('_', ' ')}
                                </span>
                            </div>
                            {project.client && (
                                <Link href={`/clients/${project.client.id}`} className="text-sm font-medium text-indigo-600 hover:underline">
                                    {project.client.name}
                                </Link>
                            )}
                        </div>
                        <div className="flex items-center gap-2">
                            <Link href={`/tasks/create?project_id=${project.id}`} className="flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700">
                                <Plus size={16} /> Add Task
                            </Link>
                        </div>
                    </div>

                    {/* Progress */}
                    <div className="mt-6 flex items-center gap-4">
                        <div className="flex-1 max-w-md">
                            <div className="flex justify-between text-xs text-gray-500 mb-1.5">
                                <span>{project.completed_tasks ?? 0}/{project.total_tasks ?? 0} tasks completed</span>
                                <span className="font-medium">{project.completion_pct}%</span>
                            </div>
                            <div className="h-2 bg-gray-100 rounded-full overflow-hidden">
                                <div className="h-full bg-indigo-500 transition-all" style={{ width: `${project.completion_pct}%` }} />
                            </div>
                        </div>
                    </div>
                </div>

                {/* Tabs Navigation */}
                <div className="border-b border-gray-200 mb-6">
                    <nav className="-mb-px flex space-x-8 overflow-x-auto">
                        {TABS.map((tab) => (
                            <Button
                                key={tab.id}
                                onClick={() => setActiveTab(tab.id)}
                                className={cn(
                                    'whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm transition-colors',
                                    activeTab === tab.id
                                        ? 'border-indigo-500 text-indigo-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                )}
                            >
                                {tab.label}
                            </Button>
                        ))}
                    </nav>
                </div>

                {/* Tab Content */}
                <div className="min-h-[50vh]">
                    {activeTab === 'tasks' && (
                        <KanbanTab project={project} tasks={tasks} />
                    )}

                    {activeTab === 'milestones' && (
                        <MilestonesTab project={project} milestones={project.milestones || []} goals={goals} />
                    )}

                    {activeTab === 'budget' && (
                        <BudgetTab project={project as any} financials={financials} invoices={invoices} expenses={expenses} campaignBudgets={campaignBudgets} retainers={retainers} />
                    )}

                    {activeTab === 'sprints' && (
                        <div className="bg-white rounded-xl border border-gray-200 p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Sprints</h3>
                            {project.sprints && project.sprints.length > 0 ? (
                                <ul className="divide-y divide-gray-100">
                                    {project.sprints.map(s => (
                                        <li key={s.id} className="py-3 flex justify-between items-center">
                                            <div>
                                                <p className="font-medium text-gray-900">{s.name}</p>
                                                <p className="text-xs text-gray-500">{formatDate(s.start_date)} - {formatDate(s.end_date)}</p>
                                            </div>
                                            <span className="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-700 capitalize">{s.status}</span>
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <p className="text-sm text-gray-500 italic">No sprints found for this project.</p>
                            )}
                        </div>
                    )}

                    {activeTab === 'issues' && (
                        <div className="bg-white rounded-xl border border-gray-200 p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Issues</h3>
                            {project.issues && project.issues.length > 0 ? (
                                <ul className="divide-y divide-gray-100">
                                    {project.issues.map(i => (
                                        <li key={i.id} className="py-3 flex justify-between items-center">
                                            <div>
                                                <p className="font-medium text-gray-900">{i.title}</p>
                                                <p className="text-xs text-gray-500">{i.assignee?.name || 'Unassigned'}</p>
                                            </div>
                                            <span className="px-2 py-1 text-xs rounded-full bg-red-100 text-red-700 capitalize">{i.status}</span>
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <p className="text-sm text-gray-500 italic">No issues reported for this project.</p>
                            )}
                        </div>
                    )}

                    {activeTab === 'assets' && (
                        <div className="bg-white rounded-xl border border-gray-200 p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Assets</h3>
                            {project.assets && project.assets.length > 0 ? (
                                <ul className="divide-y divide-gray-100">
                                    {project.assets.map(a => (
                                        <li key={a.id} className="py-3 flex justify-between items-center">
                                            <div>
                                                <p className="font-medium text-gray-900">{a.title}</p>
                                                <p className="text-xs text-gray-500">Submitted by: {a.submitter?.name}</p>
                                            </div>
                                            <span className="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-700 capitalize">{a.status}</span>
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <p className="text-sm text-gray-500 italic">No assets submitted for this project.</p>
                            )}
                        </div>
                    )}

                    {activeTab === 'team' && (
                        <div className="bg-white rounded-xl border border-gray-200 p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Project Team</h3>
                            {team && team.length > 0 ? (
                                <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                                    {team.map(member => (
                                        <div key={member.id} className="flex items-center gap-3 p-3 border border-gray-100 rounded-lg">
                                            <div className="w-10 h-10 rounded-full bg-gradient-to-br from-indigo-400 to-violet-500 flex items-center justify-center text-sm font-bold text-white shadow-sm">
                                                {member.name[0]}
                                            </div>
                                            <div>
                                                <p className="font-medium text-gray-900 text-sm">{member.name}</p>
                                                {project.manager?.id === member.id && (
                                                    <span className="text-[10px] uppercase font-bold text-indigo-600 tracking-wider">Manager</span>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-sm text-gray-500 italic">No team members assigned.</p>
                            )}
                        </div>
                    )}

                    {activeTab === 'settings' && (
                        <div className="bg-white rounded-xl border border-gray-200 p-6 max-w-2xl">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Settings</h3>
                            <div className="space-y-4">
                                <Link href={`/projects/${project.id}/edit`} className="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none">
                                    <Edit size={16} /> Edit Project Details
                                </Link>
                                <div className="border-t border-gray-200 pt-4 mt-6">
                                    <h4 className="text-sm font-medium text-red-600 mb-2">Danger Zone</h4>
                                    <p className="text-sm text-gray-500 mb-3">Deleting a project will permanently remove it and all related tasks, issues, and assets.</p>
                                    <Button
                                        onClick={async () => {
                                            const ok = await confirm({
                                                title: 'Delete this project?',
                                                description: 'This action cannot be undone.',
                                                confirmText: 'Delete',
                                                variant: 'destructive',
                                            });
                                            if (ok) router.delete(`/projects/${project.id}`);
                                        }}
                                        className="inline-flex items-center gap-2 px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                                    >
                                        <Trash2 size={16} /> Delete Project
                                    </Button>
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}

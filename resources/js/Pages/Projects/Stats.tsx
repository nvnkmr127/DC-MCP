import React from 'react';
import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { cn, formatCurrency, formatDate } from '@/lib/utils';
import { ChevronLeft, CheckCircle, Clock, AlertCircle, XCircle, Circle, BarChart2 } from 'lucide-react';
import {
    BarChart, Bar, Cell, XAxis, YAxis, CartesianGrid, Tooltip,
    ResponsiveContainer, PieChart, Pie, Legend,
} from 'recharts';

interface Props {
    project: {
        id: string;
        name: string;
        status: string;
        budget: number;
        budget_used: number;
        start_date: string | null;
        end_date: string | null;
    };
    tasks_by_status: Record<string, number>;
}

const STATUS_CONFIG: Record<string, { label: string; color: string; icon: React.ElementType }> = {
    backlog:     { label: 'Backlog',      color: '#94a3b8', icon: Circle },
    todo:        { label: 'To Do',        color: '#60a5fa', icon: Circle },
    in_progress: { label: 'In Progress',  color: '#818cf8', icon: Clock },
    in_review:   { label: 'In Review',    color: '#fbbf24', icon: Clock },
    blocked:     { label: 'Blocked',      color: '#f87171', icon: AlertCircle },
    done:        { label: 'Done',         color: '#34d399', icon: CheckCircle },
    cancelled:   { label: 'Cancelled',    color: '#d1d5db', icon: XCircle },
};

const customTooltipStyle = {
    backgroundColor: '#fff',
    border: '1px solid #e5e7eb',
    borderRadius: '8px',
    fontSize: '12px',
    boxShadow: '0 4px 12px rgba(0,0,0,0.08)',
};

export default function ProjectStats({ project, tasks_by_status }: Props) {
    const total = Object.values(tasks_by_status).reduce((s, n) => s + n, 0);
    const done  = tasks_by_status['done'] ?? 0;
    const completionPct = total > 0 ? Math.round((done / total) * 100) : 0;

    const barData = Object.entries(STATUS_CONFIG).map(([key, cfg]) => ({
        name:  cfg.label,
        count: tasks_by_status[key] ?? 0,
        color: cfg.color,
    })).filter(d => d.count > 0);

    const pieData = barData.map(d => ({ name: d.name, value: d.count, color: d.color }));

    const budgetPct = project.budget > 0
        ? Math.min(100, Math.round((project.budget_used / project.budget) * 100))
        : 0;
    const budgetWarning = budgetPct >= 90;

    return (
        <AppLayout title={`${project.name} — Stats`}>
            <Head title={`${project.name} — Stats`} />

            {/* Sub-header */}
            <div className="flex items-center gap-3 mb-5">
                <Link
                    href={`/projects/${project.id}`}
                    className="flex items-center gap-1.5 text-[12px] text-gray-400 hover:text-gray-700 transition-colors"
                >
                    <ChevronLeft size={14} /> Back to project
                </Link>
                <span className="text-gray-200">·</span>
                <div className="flex items-center gap-1.5 text-[12px] text-gray-500 font-medium">
                    <BarChart2 size={13} /> Stats
                </div>
            </div>

            {/* KPI row */}
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                {[
                    { label: 'Total Tasks',  value: total,           sub: 'across all statuses' },
                    { label: 'Completed',    value: done,            sub: `${completionPct}% done` },
                    { label: 'In Progress',  value: (tasks_by_status['in_progress'] ?? 0) + (tasks_by_status['in_review'] ?? 0), sub: 'active work' },
                    { label: 'Blocked',      value: tasks_by_status['blocked'] ?? 0, sub: 'need attention', urgent: (tasks_by_status['blocked'] ?? 0) > 0 },
                ].map(kpi => (
                    <div
                        key={kpi.label}
                        className={cn(
                            'bg-white rounded-xl border p-4 shadow-[0_1px_3px_rgba(0,0,0,0.04)]',
                            kpi.urgent ? 'border-red-200' : 'border-gray-100',
                        )}
                    >
                        <p className="text-[11px] text-gray-500 font-medium uppercase tracking-wide mb-1">{kpi.label}</p>
                        <p className={cn('text-2xl font-bold', kpi.urgent ? 'text-red-600' : 'text-gray-900')}>{kpi.value}</p>
                        <p className="text-[11px] text-gray-400 mt-0.5">{kpi.sub}</p>
                    </div>
                ))}
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">
                {/* Bar chart */}
                <div className="lg:col-span-2 bg-white rounded-xl border border-gray-100 p-5 shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
                    <h3 className="text-[13px] font-semibold text-gray-900 mb-4">Tasks by Status</h3>
                    {barData.length === 0 ? (
                        <p className="text-[13px] text-gray-400 text-center py-12">No tasks yet.</p>
                    ) : (
                        <ResponsiveContainer width="100%" height={220}>
                            <BarChart data={barData} barSize={32}>
                                <CartesianGrid strokeDasharray="3 3" stroke="#f3f4f6" vertical={false} />
                                <XAxis dataKey="name" tick={{ fontSize: 11, fill: '#9ca3af' }} axisLine={false} tickLine={false} />
                                <YAxis tick={{ fontSize: 11, fill: '#9ca3af' }} axisLine={false} tickLine={false} allowDecimals={false} />
                                <Tooltip contentStyle={customTooltipStyle} cursor={{ fill: '#f9fafb' }} />
                                <Bar dataKey="count" name="Tasks" radius={[4, 4, 0, 0]}>
                                    {barData.map((entry, i) => (
                                        <Cell key={i} fill={entry.color} />
                                    ))}
                                </Bar>
                            </BarChart>
                        </ResponsiveContainer>
                    )}
                </div>

                {/* Right column */}
                <div className="space-y-4">
                    {/* Donut chart */}
                    {pieData.length > 0 && (
                        <div className="bg-white rounded-xl border border-gray-100 p-5 shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
                            <h3 className="text-[13px] font-semibold text-gray-900 mb-3">Distribution</h3>
                            <ResponsiveContainer width="100%" height={180}>
                                <PieChart>
                                    <Pie
                                        data={pieData}
                                        dataKey="value"
                                        nameKey="name"
                                        cx="50%"
                                        cy="50%"
                                        innerRadius={45}
                                        outerRadius={70}
                                        paddingAngle={2}
                                    >
                                        {pieData.map((entry, i) => (
                                            <Cell key={i} fill={entry.color} />
                                        ))}
                                    </Pie>
                                    <Tooltip contentStyle={customTooltipStyle} />
                                    <Legend iconSize={8} iconType="circle" wrapperStyle={{ fontSize: '11px' }} />
                                </PieChart>
                            </ResponsiveContainer>
                        </div>
                    )}

                    {/* Budget card */}
                    {project.budget > 0 && (
                        <div className="bg-white rounded-xl border border-gray-100 p-5 shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
                            <h3 className="text-[13px] font-semibold text-gray-900 mb-3">Budget</h3>
                            <div className="flex justify-between text-[12px] text-gray-500 mb-1.5">
                                <span>{formatCurrency(project.budget_used)} used</span>
                                <span className={cn('font-semibold', budgetWarning ? 'text-red-600' : 'text-gray-700')}>
                                    {budgetPct}%
                                </span>
                            </div>
                            <div className="h-2 bg-gray-100 rounded-full overflow-hidden">
                                <div
                                    className={cn('h-full rounded-full transition-all', budgetWarning ? 'bg-red-500' : 'bg-indigo-500')}
                                    style={{ width: `${budgetPct}%` }}
                                />
                            </div>
                            <p className="text-[11px] text-gray-400 mt-1.5">of {formatCurrency(project.budget)} total</p>
                        </div>
                    )}

                    {/* Timeline */}
                    {(project.start_date || project.end_date) && (
                        <div className="bg-white rounded-xl border border-gray-100 p-5 shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
                            <h3 className="text-[13px] font-semibold text-gray-900 mb-3">Timeline</h3>
                            <dl className="space-y-2 text-[12px]">
                                {project.start_date && (
                                    <div className="flex justify-between">
                                        <dt className="text-gray-400">Start</dt>
                                        <dd className="font-medium text-gray-700">{formatDate(project.start_date)}</dd>
                                    </div>
                                )}
                                {project.end_date && (
                                    <div className="flex justify-between">
                                        <dt className="text-gray-400">Deadline</dt>
                                        <dd className="font-medium text-gray-700">{formatDate(project.end_date)}</dd>
                                    </div>
                                )}
                            </dl>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}

import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate, formatCurrency, cn } from '@/lib/utils';
import type { Project, PaginatedResponse } from '@/types';
import { Plus, Search, Kanban, BarChart2, MoreHorizontal, ChevronRight, FolderOpen } from 'lucide-react';

const STATUS_CONFIG: Record<string, { label: string; dot: string; badge: string }> = {
    planning:  { label: 'Planning',  dot: 'bg-gray-400',    badge: 'bg-gray-100 text-gray-700' },
    active:    { label: 'Active',    dot: 'bg-emerald-500', badge: 'bg-emerald-50 text-emerald-700' },
    on_hold:   { label: 'On Hold',   dot: 'bg-yellow-400',  badge: 'bg-yellow-50 text-yellow-700' },
    completed: { label: 'Completed', dot: 'bg-blue-500',    badge: 'bg-blue-50 text-blue-700' },
    cancelled: { label: 'Cancelled', dot: 'bg-red-400',     badge: 'bg-red-50 text-red-600' },
};

interface Props {
    projects: PaginatedResponse<Project & { total_tasks: number; completed_tasks: number; completion_pct: number }>;
    filters: { status?: string; search?: string };
}

export default function ProjectsIndex({ projects, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    function handleSearch(e: React.FormEvent) {
        e.preventDefault();
        router.get('/projects', { search, status: filters.status }, { preserveState: true });
    }

    function filterStatus(status: string) {
        router.get('/projects', { search, status: status === filters.status ? '' : status }, { preserveState: true });
    }

    const statuses = ['active', 'planning', 'on_hold', 'completed', 'cancelled'];

    return (
        <AppLayout title="Projects">
            <Head title="Projects" />

            {/* ── Toolbar ── */}
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
                {/* Status filters */}
                <div className="flex items-center gap-1.5 flex-wrap">
                    <button
                        onClick={() => router.get('/projects', { search }, { preserveState: true })}
                        className={cn(
                            'px-3 py-1.5 rounded-lg text-[12px] font-medium transition-colors border',
                            !filters.status
                                ? 'bg-gray-900 text-white border-gray-900'
                                : 'bg-white text-gray-500 border-gray-200 hover:border-gray-300 hover:text-gray-700',
                        )}
                    >
                        All
                    </button>
                    {statuses.map((s) => {
                        const cfg = STATUS_CONFIG[s];
                        const active = filters.status === s;
                        return (
                            <button
                                key={s}
                                onClick={() => filterStatus(s)}
                                className={cn(
                                    'flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[12px] font-medium transition-colors border',
                                    active
                                        ? 'bg-gray-900 text-white border-gray-900'
                                        : 'bg-white text-gray-500 border-gray-200 hover:border-gray-300 hover:text-gray-700',
                                )}
                            >
                                <span className={cn('w-1.5 h-1.5 rounded-full', cfg.dot)} />
                                {cfg.label}
                            </button>
                        );
                    })}
                </div>

                {/* Search + New */}
                <div className="flex items-center gap-2 shrink-0">
                    <form onSubmit={handleSearch} className="relative">
                        <Search size={13} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
                        <input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="pl-8 pr-3 py-2 text-[13px] border border-gray-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent w-48 placeholder-gray-400"
                            placeholder="Search projects…"
                        />
                    </form>
                    <Link
                        href="/projects/create"
                        className="flex items-center gap-1.5 px-4 py-2 bg-indigo-600 text-white text-[13px] font-semibold rounded-lg hover:bg-indigo-700 transition-colors shadow-sm whitespace-nowrap"
                    >
                        <Plus size={15} /> New Project
                    </Link>
                </div>
            </div>

            {/* ── Empty state ── */}
            {projects.data.length === 0 && (
                <div className="bg-white rounded-2xl border border-dashed border-gray-200 p-16 text-center">
                    <div className="w-14 h-14 rounded-2xl bg-gray-50 flex items-center justify-center mx-auto mb-4">
                        <FolderOpen size={24} className="text-gray-300" />
                    </div>
                    <h3 className="text-[14px] font-semibold text-gray-800 mb-1">No projects found</h3>
                    <p className="text-[13px] text-gray-400 mb-5">
                        {filters.status || filters.search ? 'Try adjusting your filters.' : 'Create your first project to get started.'}
                    </p>
                    <Link
                        href="/projects/create"
                        className="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 text-white text-[13px] font-semibold rounded-lg hover:bg-indigo-700 transition-colors shadow-sm"
                    >
                        <Plus size={15} /> New Project
                    </Link>
                </div>
            )}

            {/* ── Grid ── */}
            {projects.data.length > 0 && (
                <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    {projects.data.map((project) => {
                        const cfg = STATUS_CONFIG[project.status] ?? STATUS_CONFIG.planning;
                        const pct = project.completion_pct ?? 0;
                        return (
                            <div
                                key={project.id}
                                className="group bg-white rounded-xl border border-gray-100 hover:border-gray-200 hover:shadow-[0_4px_20px_rgba(0,0,0,0.06)] transition-all duration-150 p-5"
                            >
                                {/* Header */}
                                <div className="flex items-start justify-between gap-3 mb-4">
                                    <div className="flex-1 min-w-0">
                                        <Link
                                            href={`/projects/${project.id}`}
                                            className="text-[14px] font-semibold text-gray-900 hover:text-indigo-600 transition-colors line-clamp-1 block"
                                        >
                                            {project.name}
                                        </Link>
                                        {project.client && (
                                            <p className="text-[11px] text-gray-400 mt-0.5 truncate">{project.client.name}</p>
                                        )}
                                    </div>
                                    <span className={cn('flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium shrink-0', cfg.badge)}>
                                        <span className={cn('w-1.5 h-1.5 rounded-full', cfg.dot)} />
                                        {cfg.label}
                                    </span>
                                </div>

                                {/* Progress */}
                                <div className="mb-4">
                                    <div className="flex items-center justify-between text-[11px] mb-1.5">
                                        <span className="text-gray-400">{project.completed_tasks} / {project.total_tasks} tasks</span>
                                        <span className="font-semibold text-gray-700">{pct}%</span>
                                    </div>
                                    <div className="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                        <div
                                            className={cn(
                                                'h-full rounded-full transition-all',
                                                pct >= 100 ? 'bg-emerald-500' : pct >= 50 ? 'bg-indigo-500' : 'bg-indigo-400',
                                            )}
                                            style={{ width: `${pct}%` }}
                                        />
                                    </div>
                                </div>

                                {/* Footer */}
                                <div className="flex items-center justify-between">
                                    <div className="text-[11px] text-gray-400">
                                        {project.end_date
                                            ? <span>Due <span className="font-medium text-gray-600">{formatDate(project.end_date)}</span></span>
                                            : <span>No deadline</span>
                                        }
                                    </div>
                                    <div className="flex items-center gap-1">
                                        {project.budget > 0 && (
                                            <span className="text-[11px] text-gray-400 mr-2">
                                                {formatCurrency(project.budget_used)} / {formatCurrency(project.budget)}
                                            </span>
                                        )}
                                        <Link
                                            href={`/projects/${project.id}/kanban`}
                                            className="p-1.5 rounded-lg text-gray-300 hover:text-indigo-600 hover:bg-indigo-50 transition-colors"
                                            title="Kanban board"
                                        >
                                            <Kanban size={14} />
                                        </Link>
                                        <Link
                                            href={`/projects/${project.id}`}
                                            className="p-1.5 rounded-lg text-gray-300 hover:text-indigo-600 hover:bg-indigo-50 transition-colors"
                                            title="View details"
                                        >
                                            <ChevronRight size={14} />
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}

            {/* Pagination */}
            {projects.meta && projects.meta.last_page > 1 && (
                <div className="flex items-center justify-between mt-6 pt-4 border-t border-gray-100">
                    <p className="text-[12px] text-gray-400">
                        {(projects.meta.current_page - 1) * projects.meta.per_page + 1}–{Math.min(projects.meta.current_page * projects.meta.per_page, projects.meta.total)} of {projects.meta.total} projects
                    </p>
                    <div className="flex items-center gap-1">
                        {Array.from({ length: projects.meta.last_page }, (_, i) => i + 1).map((p) => (
                            <button
                                key={p}
                                onClick={() => router.get('/projects', { ...filters, page: p }, { preserveState: true })}
                                className={cn(
                                    'w-8 h-8 rounded-lg text-[12px] font-medium transition-colors',
                                    p === projects.meta.current_page
                                        ? 'bg-indigo-600 text-white'
                                        : 'text-gray-500 hover:bg-gray-100',
                                )}
                            >
                                {p}
                            </button>
                        ))}
                    </div>
                </div>
            )}
        </AppLayout>
    );
}

import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { cn, formatDate } from '@/lib/utils';
import type { Client, Project } from '@/types';
import { ArrowLeft, Edit, Trash2, Globe, Mail, Phone, Building2, ExternalLink } from 'lucide-react';

interface Props {
    client: Client & {
        projects: Array<Project & { tasks_count: number }>;
    };
}

const TIER_CONFIG: Record<string, { label: string; cls: string }> = {
    standard:   { label: 'Standard',   cls: 'bg-gray-100 text-gray-600' },
    premium:    { label: 'Premium',    cls: 'bg-amber-50 text-amber-700' },
    enterprise: { label: 'Enterprise', cls: 'bg-violet-50 text-violet-700' },
};

const STATUS_CONFIG: Record<string, { label: string; cls: string; dot: string }> = {
    active:   { label: 'Active',   cls: 'bg-emerald-50 text-emerald-700', dot: 'bg-emerald-400' },
    prospect: { label: 'Prospect', cls: 'bg-blue-50 text-blue-700',       dot: 'bg-blue-400' },
    inactive: { label: 'Inactive', cls: 'bg-gray-100 text-gray-500',      dot: 'bg-gray-300' },
    churned:  { label: 'Churned',  cls: 'bg-red-50 text-red-600',         dot: 'bg-red-400' },
};

const PROJECT_STATUS_STYLES: Record<string, string> = {
    planning:  'bg-gray-100 text-gray-600',
    active:    'bg-emerald-50 text-emerald-700',
    on_hold:   'bg-yellow-50 text-yellow-700',
    completed: 'bg-blue-50 text-blue-700',
    cancelled: 'bg-red-50 text-red-600',
};

export default function ClientShow({ client }: Props) {
    const tier   = TIER_CONFIG[client.tier]   ?? TIER_CONFIG.standard;
    const status = STATUS_CONFIG[client.status] ?? STATUS_CONFIG.inactive;

    const activeProjects = client.projects.filter(p => p.status === 'active').length;

    return (
        <AppLayout title={client.name}>
            <Head title={client.name} />

            <div className="max-w-4xl mx-auto">
                {/* Breadcrumb */}
                <div className="flex items-center gap-2 text-[12px] text-gray-500 mb-4">
                    <Link href="/clients" className="hover:text-indigo-600 transition-colors">Clients</Link>
                    <span>/</span>
                    <span className="text-gray-900 font-medium">{client.name}</span>
                </div>

                {/* Header card */}
                <div className="bg-white rounded-xl border border-gray-100 p-6 mb-4 shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
                    <div className="flex items-start justify-between">
                        <div className="flex items-start gap-4">
                            {/* Avatar */}
                            <div className="w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-400 to-violet-500 flex items-center justify-center shrink-0">
                                <Building2 size={22} className="text-white" />
                            </div>
                            <div>
                                <div className="flex items-center gap-2.5 flex-wrap">
                                    <h1 className="text-[18px] font-bold text-gray-900">{client.name}</h1>
                                    <span className={cn('px-2 py-0.5 rounded-full text-[10px] font-semibold flex items-center gap-1', status.cls)}>
                                        <span className={cn('w-1.5 h-1.5 rounded-full', status.dot)} />
                                        {status.label}
                                    </span>
                                    <span className={cn('px-2 py-0.5 rounded-full text-[10px] font-semibold', tier.cls)}>
                                        {tier.label}
                                    </span>
                                </div>

                                {/* Contact info */}
                                <div className="flex flex-wrap items-center gap-4 mt-2">
                                    {client.email && (
                                        <a href={`mailto:${client.email}`} className="flex items-center gap-1.5 text-[12px] text-gray-500 hover:text-indigo-600 transition-colors">
                                            <Mail size={13} /> {client.email}
                                        </a>
                                    )}
                                    {client.phone && (
                                        <a href={`tel:${client.phone}`} className="flex items-center gap-1.5 text-[12px] text-gray-500 hover:text-indigo-600 transition-colors">
                                            <Phone size={13} /> {client.phone}
                                        </a>
                                    )}
                                    {client.website && (
                                        <a href={client.website} target="_blank" rel="noreferrer" className="flex items-center gap-1.5 text-[12px] text-gray-500 hover:text-indigo-600 transition-colors">
                                            <Globe size={13} /> {client.website.replace(/^https?:\/\//, '')}
                                            <ExternalLink size={10} />
                                        </a>
                                    )}
                                    {client.industry && (
                                        <span className="text-[12px] text-gray-400">{client.industry}</span>
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* Actions */}
                        <div className="flex items-center gap-2 ml-4 shrink-0">
                            <Link
                                href={`/projects/create?client_id=${client.id}`}
                                className="flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 text-white text-[12px] font-semibold rounded-lg hover:bg-indigo-700 transition-colors"
                            >
                                + New Project
                            </Link>
                            <Link
                                href={`/clients/${client.id}/edit`}
                                className="flex items-center gap-1.5 px-3 py-1.5 text-[12px] border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50 transition-colors"
                            >
                                <Edit size={13} /> Edit
                            </Link>
                            <button
                                onClick={() => {
                                    if (confirm('Delete this client? Their projects will not be deleted. This cannot be undone.')) {
                                        router.delete(`/clients/${client.id}`);
                                    }
                                }}
                                className="flex items-center gap-1.5 px-3 py-1.5 text-[12px] border border-red-200 rounded-lg text-red-600 hover:bg-red-50 transition-colors"
                            >
                                <Trash2 size={13} /> Delete
                            </button>
                        </div>
                    </div>

                    {/* Notes */}
                    {client.notes && (
                        <div className="mt-4 pt-4 border-t border-gray-50">
                            <p className="text-[12px] text-gray-500 font-medium mb-1">Notes</p>
                            <p className="text-[13px] text-gray-700 leading-relaxed">{client.notes}</p>
                        </div>
                    )}
                </div>

                {/* Stats strip */}
                <div className="grid grid-cols-3 gap-4 mb-4">
                    {[
                        { label: 'Total Projects', value: client.projects.length },
                        { label: 'Active Projects', value: activeProjects },
                        { label: 'Total Tasks',    value: client.projects.reduce((s, p) => s + (p.tasks_count ?? 0), 0) },
                    ].map(stat => (
                        <div key={stat.label} className="bg-white rounded-xl border border-gray-100 p-4 shadow-[0_1px_3px_rgba(0,0,0,0.04)] text-center">
                            <p className="text-2xl font-bold text-gray-900">{stat.value}</p>
                            <p className="text-[11px] text-gray-400 mt-0.5">{stat.label}</p>
                        </div>
                    ))}
                </div>

                {/* Projects list */}
                <div className="bg-white rounded-xl border border-gray-100 shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
                    <div className="px-5 py-3.5 border-b border-gray-50 flex items-center justify-between">
                        <h3 className="text-[13px] font-semibold text-gray-900">Projects</h3>
                        <Link
                            href={`/projects/create?client_id=${client.id}`}
                            className="text-[12px] text-indigo-600 hover:text-indigo-700 font-medium"
                        >
                            + Add project
                        </Link>
                    </div>

                    {client.projects.length === 0 ? (
                        <div className="py-12 text-center">
                            <p className="text-[13px] text-gray-400">No projects yet.</p>
                            <Link
                                href={`/projects/create?client_id=${client.id}`}
                                className="mt-2 inline-block text-[12px] text-indigo-600 hover:text-indigo-700"
                            >
                                Create the first project →
                            </Link>
                        </div>
                    ) : (
                        <div className="divide-y divide-gray-50">
                            {client.projects.map(project => (
                                <Link
                                    key={project.id}
                                    href={`/projects/${project.id}`}
                                    className="flex items-center justify-between px-5 py-3.5 hover:bg-gray-50 transition-colors group"
                                >
                                    <div>
                                        <p className="text-[13px] font-semibold text-gray-900 group-hover:text-indigo-600 transition-colors">
                                            {project.name}
                                        </p>
                                        <p className="text-[11px] text-gray-400 mt-0.5">
                                            {project.tasks_count ?? 0} task{project.tasks_count !== 1 ? 's' : ''}
                                            {project.end_date && ` · Due ${formatDate(project.end_date)}`}
                                        </p>
                                    </div>
                                    <span className={cn(
                                        'px-2 py-0.5 rounded-full text-[10px] font-semibold capitalize',
                                        PROJECT_STATUS_STYLES[project.status] ?? 'bg-gray-100 text-gray-500',
                                    )}>
                                        {project.status.replace(/_/g, ' ')}
                                    </span>
                                </Link>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}

import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { cn } from '@/lib/utils';
import type { Client, PaginatedResponse } from '@/types';
import { Plus, Search, Building2, Globe, Mail, Phone } from 'lucide-react';

const TIER_CONFIG: Record<string, { label: string; badge: string; dot: string }> = {
    standard:   { label: 'Standard',   badge: 'bg-gray-100 text-gray-600',    dot: 'bg-gray-400' },
    premium:    { label: 'Premium',    badge: 'bg-indigo-50 text-indigo-700', dot: 'bg-indigo-500' },
    enterprise: { label: 'Enterprise', badge: 'bg-violet-50 text-violet-700', dot: 'bg-violet-500' },
};

const STATUS_CONFIG: Record<string, { label: string; badge: string; dot: string }> = {
    active:   { label: 'Active',   badge: 'bg-emerald-50 text-emerald-700', dot: 'bg-emerald-500' },
    inactive: { label: 'Inactive', badge: 'bg-gray-100 text-gray-500',      dot: 'bg-gray-300' },
    prospect: { label: 'Prospect', badge: 'bg-yellow-50 text-yellow-700',   dot: 'bg-yellow-400' },
};

interface Props {
    clients: PaginatedResponse<Client & { projects_count: number }>;
    filters: { tier?: string; status?: string; search?: string };
}

export default function ClientsIndex({ clients, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    function handleSearch(e: React.FormEvent) {
        e.preventDefault();
        router.get('/clients', { ...filters, search }, { preserveState: true });
    }

    function filterBy(key: string, val: string) {
        router.get('/clients', { ...filters, [key]: (filters as any)[key] === val ? '' : val }, { preserveState: true });
    }

    const statusOptions = ['active', 'prospect', 'inactive'] as const;
    const tierOptions   = ['standard', 'premium', 'enterprise'] as const;

    return (
        <AppLayout title="Clients">
            <Head title="Clients" />

            {/* ── Toolbar ── */}
            <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6">
                <div className="flex items-center gap-1.5 flex-wrap">
                    {statusOptions.map((s) => {
                        const cfg = STATUS_CONFIG[s];
                        return (
                            <button
                                key={s}
                                onClick={() => filterBy('status', s)}
                                className={cn(
                                    'flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[12px] font-medium border transition-colors',
                                    filters.status === s
                                        ? 'bg-gray-900 text-white border-gray-900'
                                        : 'bg-white text-gray-500 border-gray-200 hover:border-gray-300 hover:text-gray-700',
                                )}
                            >
                                <span className={cn('w-1.5 h-1.5 rounded-full', cfg.dot)} />
                                {cfg.label}
                            </button>
                        );
                    })}
                    <div className="w-px h-5 bg-gray-200 mx-0.5" />
                    {tierOptions.map((t) => {
                        const cfg = TIER_CONFIG[t];
                        return (
                            <button
                                key={t}
                                onClick={() => filterBy('tier', t)}
                                className={cn(
                                    'flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[12px] font-medium border transition-colors',
                                    filters.tier === t
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

                <div className="flex items-center gap-2 shrink-0">
                    <form onSubmit={handleSearch} className="relative">
                        <Search size={13} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
                        <input
                            value={search}
                            onChange={e => setSearch(e.target.value)}
                            className="pl-8 pr-3 py-2 text-[13px] border border-gray-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent w-48 placeholder-gray-400"
                            placeholder="Search clients…"
                        />
                    </form>
                    <Link
                        href="/clients/create"
                        className="flex items-center gap-1.5 px-4 py-2 bg-indigo-600 text-white text-[13px] font-semibold rounded-lg hover:bg-indigo-700 transition-colors shadow-sm whitespace-nowrap"
                    >
                        <Plus size={14} /> New Client
                    </Link>
                </div>
            </div>

            {/* ── Empty state ── */}
            {clients.data.length === 0 && (
                <div className="bg-white rounded-2xl border border-dashed border-gray-200 p-16 text-center">
                    <div className="w-14 h-14 rounded-2xl bg-gray-50 flex items-center justify-center mx-auto mb-4">
                        <Building2 size={24} className="text-gray-300" />
                    </div>
                    <h3 className="text-[14px] font-semibold text-gray-800 mb-1">No clients found</h3>
                    <p className="text-[13px] text-gray-400 mb-5">
                        {filters.status || filters.tier || filters.search ? 'Try adjusting your filters.' : 'Add your first client to get started.'}
                    </p>
                    <Link
                        href="/clients/create"
                        className="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 text-white text-[13px] font-semibold rounded-lg hover:bg-indigo-700 transition-colors shadow-sm"
                    >
                        <Plus size={14} /> Add Client
                    </Link>
                </div>
            )}

            {/* ── Grid ── */}
            {clients.data.length > 0 && (
                <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    {clients.data.map((client) => {
                        const tierCfg   = TIER_CONFIG[client.tier ?? 'standard']   ?? TIER_CONFIG.standard;
                        const statusCfg = STATUS_CONFIG[client.status ?? 'active'] ?? STATUS_CONFIG.active;
                        return (
                            <div
                                key={client.id}
                                className="group bg-white rounded-xl border border-gray-100 hover:border-gray-200 hover:shadow-[0_4px_20px_rgba(0,0,0,0.06)] transition-all duration-150 p-5"
                            >
                                {/* Header */}
                                <div className="flex items-start justify-between gap-3 mb-4">
                                    <div className="flex items-center gap-3">
                                        <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-50 to-violet-50 flex items-center justify-center shrink-0">
                                            <Building2 size={18} className="text-indigo-400" />
                                        </div>
                                        <div className="min-w-0">
                                            <Link
                                                href={`/clients/${client.id}`}
                                                className="text-[14px] font-semibold text-gray-900 hover:text-indigo-600 transition-colors line-clamp-1 block"
                                            >
                                                {client.name}
                                            </Link>
                                            <p className="text-[11px] text-gray-400 mt-0.5">
                                                {client.projects_count} project{client.projects_count !== 1 ? 's' : ''}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex flex-col items-end gap-1 shrink-0">
                                        <span className={cn('flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold', statusCfg.badge)}>
                                            <span className={cn('w-1.5 h-1.5 rounded-full', statusCfg.dot)} />
                                            {statusCfg.label}
                                        </span>
                                        <span className={cn('px-2 py-0.5 rounded-full text-[10px] font-semibold', tierCfg.badge)}>
                                            {tierCfg.label}
                                        </span>
                                    </div>
                                </div>

                                {/* Contact info */}
                                <div className="space-y-1.5">
                                    {client.email && (
                                        <a
                                            href={`mailto:${client.email}`}
                                            className="flex items-center gap-2 text-[12px] text-gray-500 hover:text-indigo-600 transition-colors group/link"
                                        >
                                            <Mail size={12} className="shrink-0 text-gray-300 group-hover/link:text-indigo-400 transition-colors" />
                                            <span className="truncate">{client.email}</span>
                                        </a>
                                    )}
                                    {client.phone && (
                                        <div className="flex items-center gap-2 text-[12px] text-gray-500">
                                            <Phone size={12} className="shrink-0 text-gray-300" />
                                            <span>{client.phone}</span>
                                        </div>
                                    )}
                                    {client.website && (
                                        <a
                                            href={client.website}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="flex items-center gap-2 text-[12px] text-gray-500 hover:text-indigo-600 transition-colors group/link"
                                        >
                                            <Globe size={12} className="shrink-0 text-gray-300 group-hover/link:text-indigo-400 transition-colors" />
                                            <span className="truncate">{client.website.replace(/^https?:\/\//, '')}</span>
                                        </a>
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}
        </AppLayout>
    );
}

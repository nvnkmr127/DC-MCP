import React, { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { cn } from '@/lib/utils';
import type { Client, PaginatedResponse } from '@/types';
import { Plus, Search, Building2, Globe, Mail, Phone, DollarSign } from 'lucide-react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/Components/ui/Dialog';
import { Button } from '@/Components/ui/Button';
import { Input, Label, TextArea } from '@/Components/ui/Input';
import { Badge } from '@/Components/ui/Badge';
import { CLIENT_STATUS_CONFIG, CLIENT_TIER_CONFIG } from '@/lib/constants';

const fmt = (n: number) => '₹' + new Intl.NumberFormat('en-IN', { maximumFractionDigits: 0 }).format(n);

function UpsellModal({ client, onClose }: { client: Client & { projects_count: number; upsell_potential?: number | null; upsell_notes?: string | null; upsell_flagged?: boolean }; onClose: () => void }) {
    const form = useForm({
        upsell_notes: client.upsell_notes ?? '',
        upsell_potential: client.upsell_potential ? String(client.upsell_potential) : '',
    });
    return (
        <Dialog open={true} onOpenChange={(open) => { if (!open) onClose(); }}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>Flag for Upsell — {client.name}</DialogTitle>
                </DialogHeader>
                <form onSubmit={e => {
                    e.preventDefault();
                    form.patch(`/clients/${client.id}/upsell`, { onSuccess: onClose });
                }} className="space-y-4">
                    <div>
                        <Label>Upsell Opportunity Notes</Label>
                        <TextArea
                            value={form.data.upsell_notes}
                            onChange={e => form.setData('upsell_notes', e.target.value)}
                            rows={3}
                            placeholder="Why is this client ready for an upsell? What services?"
                            className="mt-1"
                        />
                    </div>
                    <div>
                        <Label>Potential Value (₹/month)</Label>
                        <Input
                            type="number"
                            value={form.data.upsell_potential}
                            onChange={e => form.setData('upsell_potential', e.target.value)}
                            placeholder="e.g. 50000"
                            className="mt-1"
                        />
                    </div>
                    <div className="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="ghost" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            loading={form.processing}
                            className="bg-amber-500 hover:bg-amber-600 focus:ring-amber-500"
                        >
                            Flag for Upsell
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

interface Props {
    clients: PaginatedResponse<Client & { projects_count: number; upsell_flagged?: boolean; upsell_potential?: number | null; upsell_notes?: string | null }>;
    filters: { tier?: string; status?: string; search?: string };
}

export default function ClientsIndex({ clients, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [upsellClient, setUpsellClient] = useState<(Client & { projects_count: number; upsell_potential?: number | null; upsell_notes?: string | null; upsell_flagged?: boolean }) | null>(null);

    const upsellClients = clients.data.filter(c => c.upsell_flagged);
    const upsellPotential = upsellClients.reduce((s, c) => s + (c.upsell_potential ?? 0), 0);

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

            {/* ── Upsell banner ── */}
            {upsellClients.length > 0 && (
                <div className="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 mb-4 flex items-center gap-3">
                    <DollarSign size={16} className="text-amber-600 shrink-0" />
                    <p className="text-sm text-amber-800 font-medium">
                         <strong>{upsellClients.length}</strong> client{upsellClients.length > 1 ? 's' : ''} flagged for upsell
                        {upsellPotential > 0 && <> — <strong>{fmt(upsellPotential)}</strong>/mo potential</>}
                    </p>
                </div>
            )}


            {/* ── Toolbar ── */}
            <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6">
                <div className="flex items-center gap-1.5 flex-wrap">
                    {statusOptions.map((s) => {
                        const cfg = CLIENT_STATUS_CONFIG[s];
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
                        const cfg = CLIENT_TIER_CONFIG[t];
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
                    <Button
                        onClick={() => router.visit('/clients/create')}
                        size="sm"
                    >
                        <Plus size={14} className="mr-1" /> New Client
                    </Button>
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
                    <Button
                        onClick={() => router.visit('/clients/create')}
                        size="md"
                    >
                        <Plus size={14} className="mr-1" /> Add Client
                    </Button>
                </div>
            )}

            {upsellClient && <UpsellModal client={upsellClient} onClose={() => setUpsellClient(null)} />}

            {/* ── Grid ── */}
            {clients.data.length > 0 && (
                <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    {clients.data.map((client) => {
                        const tierCfg   = CLIENT_TIER_CONFIG[client.tier ?? 'standard']   ?? CLIENT_TIER_CONFIG.standard;
                        const statusCfg = CLIENT_STATUS_CONFIG[client.status ?? 'active'] ?? CLIENT_STATUS_CONFIG.active;
                        return (
                            <div
                                key={client.id}
                                className={cn(
                                    'group bg-white rounded-xl border hover:shadow-[0_4px_20px_rgba(0,0,0,0.06)] transition-all duration-150 p-5',
                                    client.upsell_flagged ? 'border-amber-200 hover:border-amber-300' : 'border-gray-100 hover:border-gray-200',
                                )}
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
                                        <Badge
                                            variant={client.status === 'active' ? 'emerald' : client.status === 'prospect' ? 'amber' : 'gray'}
                                            showDot
                                            dotClassName={statusCfg.dot}
                                        >
                                            {statusCfg.label}
                                        </Badge>
                                        <Badge
                                            variant={client.tier === 'enterprise' ? 'violet' : client.tier === 'premium' ? 'indigo' : 'gray'}
                                        >
                                            {tierCfg.label}
                                        </Badge>
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

                                {/* Upsell footer */}
                                <div className="mt-3 pt-3 border-t border-gray-50 flex items-center justify-between">
                                    {client.upsell_flagged ? (
                                        <span className="flex items-center gap-1 text-[11px] font-medium text-amber-600">
                                            <DollarSign size={11} />
                                            {client.upsell_potential ? fmt(client.upsell_potential) + '/mo' : 'Upsell flagged'}
                                        </span>
                                    ) : <span />}
                                    <Button
                                        onClick={() => setUpsellClient(client)}
                                        variant="ghost"
                                        size="sm"
                                        className={cn(
                                            'flex items-center gap-1 text-[11px] font-medium px-2.5 py-1 rounded-lg transition-colors h-7',
                                            client.upsell_flagged
                                                ? 'text-amber-600 bg-amber-50 hover:bg-amber-100 hover:text-amber-700'
                                                : 'text-gray-400 hover:text-amber-600 hover:bg-amber-50 opacity-0 group-hover:opacity-100',
                                        )}
                                    >
                                        <DollarSign size={11} />
                                        {client.upsell_flagged ? 'Edit Upsell' : 'Flag Upsell'}
                                    </Button>
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}
        </AppLayout>
    );
}

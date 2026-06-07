import React, { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useConfirm } from '@/hooks/useConfirm';
import { cn, formatDate } from '@/lib/utils';
import type { Client, Project } from '@/types';
import { ArrowLeft, Edit, Trash2, Globe, Mail, Phone, Building2, ExternalLink, Plus, X, MessageSquare, Phone as PhoneIcon, AtSign, Star } from 'lucide-react';

interface Communication {
    id: string; type: string; contact_person: string | null; subject: string; notes: string;
    outcome: string | null; next_action: string | null; next_action_date: string | null;
    communicated_at: string; logged_by: string | null;
}

const COMM_TYPE_STYLES: Record<string, string> = {
    call:     'bg-blue-100 text-blue-700',
    email:    'bg-violet-100 text-violet-700',
    whatsapp: 'bg-green-100 text-green-700',
    meeting:  'bg-amber-100 text-amber-700',
    linkedin: 'bg-sky-100 text-sky-700',
    other:    'bg-gray-100 text-gray-600',
};

interface Props {
    client: Client & {
        projects: Array<Project & { tasks_count: number }>;
        success_score: number | null;
    };
    communications: Communication[];
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

function AddCommModal({ clientId, onClose }: { clientId: string; onClose: () => void }) {
    const form = useForm({
        type: 'call', contact_person: '', subject: '', notes: '',
        outcome: '', next_action: '', next_action_date: '',
        communicated_at: new Date().toISOString().slice(0, 10),
    });
    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 space-y-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-[15px] font-bold text-gray-900">Log Communication</h2>
                    <button onClick={onClose}><X size={16} className="text-gray-400" /></button>
                </div>
                <form onSubmit={e => { e.preventDefault(); form.post(`/clients/${clientId}/communications`, { onSuccess: onClose }); }}
                    className="space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Type *</label>
                            <select value={form.data.type} onChange={e => form.setData('type', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                {['call', 'email', 'whatsapp', 'meeting', 'linkedin', 'other'].map(t =>
                                    <option key={t} value={t}>{t}</option>
                                )}
                            </select>
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Date *</label>
                            <input type="date" value={form.data.communicated_at}
                                onChange={e => form.setData('communicated_at', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                        </div>
                    </div>
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Contact Person</label>
                        <input type="text" value={form.data.contact_person}
                            onChange={e => form.setData('contact_person', e.target.value)}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Subject *</label>
                        <input type="text" value={form.data.subject} onChange={e => form.setData('subject', e.target.value)}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Notes *</label>
                        <textarea value={form.data.notes} onChange={e => form.setData('notes', e.target.value)} rows={3}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 resize-none" />
                    </div>
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Outcome</label>
                        <input type="text" value={form.data.outcome} onChange={e => form.setData('outcome', e.target.value)}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Next Action</label>
                            <input type="text" value={form.data.next_action}
                                onChange={e => form.setData('next_action', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Next Action Date</label>
                            <input type="date" value={form.data.next_action_date}
                                onChange={e => form.setData('next_action_date', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                        </div>
                    </div>
                    <div className="flex justify-end gap-2 pt-2">
                        <button type="button" onClick={onClose} className="px-4 py-2 text-sm text-gray-600">Cancel</button>
                        <button type="submit" disabled={form.processing || !form.data.subject || !form.data.notes}
                            className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                            {form.processing ? 'Saving…' : 'Log'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

function ScoreModal({ clientId, current, onClose }: { clientId: string; current: number | null; onClose: () => void }) {
    const form = useForm({ overall_score: String(current ?? 0) });
    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-2xl shadow-xl w-full max-w-xs p-6 space-y-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-[15px] font-bold text-gray-900">Update Success Score</h2>
                    <button onClick={onClose}><X size={16} className="text-gray-400" /></button>
                </div>
                <form onSubmit={e => { e.preventDefault(); form.post(`/clients/${clientId}/success-score`, { onSuccess: onClose }); }} className="space-y-3">
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Score (0–100)</label>
                        <input type="number" min={0} max={100} value={form.data.overall_score}
                            onChange={e => form.setData('overall_score', e.target.value)}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div className="flex justify-end gap-2">
                        <button type="button" onClick={onClose} className="px-4 py-2 text-sm text-gray-600">Cancel</button>
                        <button type="submit" disabled={form.processing}
                            className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                            {form.processing ? 'Saving…' : 'Save'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export default function ClientShow({ client, communications }: Props) {
    const [activeTab, setActiveTab] = useState<'projects' | 'comms'>('projects');
    const [commOpen, setCommOpen] = useState(false);
    const [scoreOpen, setScoreOpen] = useState(false);
    const confirm = useConfirm();
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
                                onClick={async () => {
                                    const ok = await confirm({
                                        title: 'Delete this client?',
                                        description: 'Their projects will not be deleted. This cannot be undone.',
                                        confirmText: 'Delete',
                                        variant: 'destructive',
                                    });
                                    if (!ok) return;
                                    router.delete(`/clients/${client.id}`);
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

                {/* Tab bar */}
                <div className="flex gap-1 mb-4">
                    {(['projects', 'comms'] as const).map(tab => (
                        <button key={tab} onClick={() => setActiveTab(tab)}
                            className={cn('px-4 py-1.5 text-[13px] font-medium rounded-lg transition-colors',
                                activeTab === tab ? 'bg-indigo-600 text-white' : 'text-gray-500 hover:bg-gray-100'
                            )}>
                            {tab === 'projects' ? 'Projects' : `Communications (${communications.length})`}
                        </button>
                    ))}
                </div>

                {/* Stats strip */}
                <div className="grid grid-cols-4 gap-4 mb-4">
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
                    <button onClick={() => setScoreOpen(true)}
                        className="bg-white rounded-xl border border-gray-100 p-4 shadow-[0_1px_3px_rgba(0,0,0,0.04)] text-center hover:border-indigo-200 transition-colors group">
                        <div className="flex items-center justify-center gap-1 mb-0.5">
                            <Star size={14} className={client.success_score !== null ? 'text-amber-400 fill-amber-400' : 'text-gray-300'} />
                            <p className="text-2xl font-bold text-gray-900">{client.success_score ?? '—'}</p>
                        </div>
                        <p className="text-[11px] text-gray-400">Success Score</p>
                    </button>
                </div>

                {activeTab === 'projects' ? (
                    <div className="bg-white rounded-xl border border-gray-100 shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
                        <div className="px-5 py-3.5 border-b border-gray-50 flex items-center justify-between">
                            <h3 className="text-[13px] font-semibold text-gray-900">Projects</h3>
                            <Link href={`/projects/create?client_id=${client.id}`}
                                className="text-[12px] text-indigo-600 hover:text-indigo-700 font-medium">
                                + Add project
                            </Link>
                        </div>
                        {client.projects.length === 0 ? (
                            <div className="py-12 text-center">
                                <p className="text-[13px] text-gray-400">No projects yet.</p>
                                <Link href={`/projects/create?client_id=${client.id}`}
                                    className="mt-2 inline-block text-[12px] text-indigo-600 hover:text-indigo-700">
                                    Create the first project →
                                </Link>
                            </div>
                        ) : (
                            <div className="divide-y divide-gray-50">
                                {client.projects.map(project => (
                                    <Link key={project.id} href={`/projects/${project.id}`}
                                        className="flex items-center justify-between px-5 py-3.5 hover:bg-gray-50 transition-colors group">
                                        <div>
                                            <p className="text-[13px] font-semibold text-gray-900 group-hover:text-indigo-600 transition-colors">
                                                {project.name}
                                            </p>
                                            <p className="text-[11px] text-gray-400 mt-0.5">
                                                {project.tasks_count ?? 0} task{project.tasks_count !== 1 ? 's' : ''}
                                                {project.end_date && ` · Due ${formatDate(project.end_date)}`}
                                            </p>
                                        </div>
                                        <span className={cn('px-2 py-0.5 rounded-full text-[10px] font-semibold capitalize',
                                            PROJECT_STATUS_STYLES[project.status] ?? 'bg-gray-100 text-gray-500')}>
                                            {project.status.replace(/_/g, ' ')}
                                        </span>
                                    </Link>
                                ))}
                            </div>
                        )}
                    </div>
                ) : (
                    <div className="bg-white rounded-xl border border-gray-100 shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
                        <div className="px-5 py-3.5 border-b border-gray-50 flex items-center justify-between">
                            <h3 className="text-[13px] font-semibold text-gray-900">Communication Log</h3>
                            <button onClick={() => setCommOpen(true)}
                                className="flex items-center gap-1 text-[12px] text-indigo-600 hover:text-indigo-700 font-medium">
                                <Plus size={12} /> Log
                            </button>
                        </div>
                        {communications.length === 0 ? (
                            <div className="py-12 text-center">
                                <MessageSquare size={28} className="text-gray-200 mx-auto mb-2" />
                                <p className="text-[13px] text-gray-400">No communications logged yet.</p>
                                <button onClick={() => setCommOpen(true)}
                                    className="mt-2 text-[12px] text-indigo-600 hover:text-indigo-700">
                                    Log the first one →
                                </button>
                            </div>
                        ) : (
                            <div className="divide-y divide-gray-50">
                                {communications.map(c => (
                                    <div key={c.id} className="px-5 py-3.5">
                                        <div className="flex items-start justify-between gap-2">
                                            <div className="flex-1 min-w-0">
                                                <div className="flex items-center gap-2 flex-wrap">
                                                    <span className={cn('text-[10px] px-2 py-0.5 rounded-full font-medium', COMM_TYPE_STYLES[c.type])}>
                                                        {c.type}
                                                    </span>
                                                    <p className="text-[13px] font-semibold text-gray-900">{c.subject}</p>
                                                </div>
                                                {c.contact_person && <p className="text-[11px] text-gray-400 mt-0.5">with {c.contact_person}</p>}
                                                <p className="text-[12px] text-gray-600 mt-1">{c.notes}</p>
                                                {c.outcome && <p className="text-[11px] text-emerald-600 mt-1">→ {c.outcome}</p>}
                                                {c.next_action && (
                                                    <p className="text-[11px] text-amber-600 mt-1">
                                                        Next: {c.next_action}{c.next_action_date ? ` by ${c.next_action_date}` : ''}
                                                    </p>
                                                )}
                                            </div>
                                            <div className="text-right shrink-0">
                                                <p className="text-[11px] text-gray-400">{c.communicated_at}</p>
                                                {c.logged_by && <p className="text-[10px] text-gray-300">{c.logged_by}</p>}
                                                <button onClick={async () => {
                                                    const ok = await confirm({
                                                        title: 'Delete this entry?',
                                                        description: 'This cannot be undone.',
                                                        confirmText: 'Delete',
                                                        variant: 'destructive',
                                                    });
                                                    if (!ok) return;
                                                    router.delete(`/client-communications/${c.id}`);
                                                }}
                                                    className="text-[10px] text-gray-300 hover:text-rose-400 mt-1">delete</button>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                )}

                {commOpen && <AddCommModal clientId={client.id} onClose={() => setCommOpen(false)} />}
                {scoreOpen && <ScoreModal clientId={client.id} current={client.success_score} onClose={() => setScoreOpen(false)} />}
            </div>
        </AppLayout>
    );
}

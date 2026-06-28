import React, { useState } from 'react';
import { Button } from '@/Components/ui/Button';
import { Head, Link, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import ProposalsTab from '../Agreements/ProposalsTab';
import SowTab from '../Agreements/SowTab';
import { useConfirm } from '@/hooks/useConfirm';
import { cn, formatDate } from '@/lib/utils';
import type { Client, Project } from '@/types';
import { Breadcrumbs } from '@/Components/Shared/Breadcrumbs';
import { ArrowLeft, Edit, Trash2, Globe, Mail, Phone, Building2, ExternalLink, Plus, X, MessageSquare, Phone as PhoneIcon, AtSign, Star, Sparkles, Send, ChevronDown, Smile } from 'lucide-react';

interface Metric { key: string; value: string; }

function ReportModal({ clientId, onClose }: { clientId: string; onClose: () => void }) {
    const form = useForm({ client_id: clientId, month_year: new Date().toISOString().slice(0, 7), highlights: '', challenges: '' });
    const [metrics, setMetrics] = useState<Metric[]>([{ key: '', value: '' }]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const metricsObj = Object.fromEntries(metrics.filter(m => m.key).map(m => [m.key, m.value]));
        form.transform(d => ({ ...d, metrics: metricsObj }));
        form.post('/client-updates', { onSuccess: onClose });
    };

    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 space-y-4 max-h-[90vh] overflow-y-auto">
                <div className="flex items-center justify-between">
                    <h2 className="text-[15px] font-bold text-gray-900">New Report</h2>
                    <Button onClick={onClose}><X size={16} className="text-gray-400" /></Button>
                </div>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Month *</label>
                            <input type="month" value={form.data.month_year} onChange={e => form.setData('month_year', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                        </div>
                    </div>
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Highlights</label>
                        <textarea value={form.data.highlights} onChange={e => form.setData('highlights', e.target.value)} rows={3}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 resize-none" />
                    </div>
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Challenges</label>
                        <textarea value={form.data.challenges} onChange={e => form.setData('challenges', e.target.value)} rows={2}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 resize-none" />
                    </div>
                    <div>
                        <div className="flex items-center justify-between mb-1">
                            <label className="text-xs text-gray-500 font-medium">Metrics (key / value)</label>
                            <Button type="button" onClick={() => setMetrics(m => [...m, { key: '', value: '' }])}
                                className="text-xs text-indigo-600 font-medium flex items-center gap-1"><Plus size={12} /> Add</Button>
                        </div>
                        {metrics.map((m, i) => (
                            <div key={i} className="flex gap-2 mb-1.5">
                                <input type="text" placeholder="Metric name" value={m.key}
                                    onChange={e => { const n = [...metrics]; n[i].key = e.target.value; setMetrics(n); }}
                                    className="flex-1 border border-gray-300 rounded-lg px-2 py-1.5 text-xs focus:ring-1 focus:ring-indigo-500" />
                                <input type="text" placeholder="Value" value={m.value}
                                    onChange={e => { const n = [...metrics]; n[i].value = e.target.value; setMetrics(n); }}
                                    className="flex-1 border border-gray-300 rounded-lg px-2 py-1.5 text-xs focus:ring-1 focus:ring-indigo-500" />
                                <Button type="button" onClick={() => setMetrics(m => m.filter((_, j) => j !== i))} className="text-gray-400 hover:text-rose-500">
                                    <X size={16} />
                                </Button>
                            </div>
                        ))}
                    </div>
                    <div className="flex justify-end gap-2 pt-1">
                        <Button type="button" onClick={onClose} variant="ghost" >Cancel</Button>
                        <Button type="submit" disabled={form.processing}
                            className="disabled:opacity-50" >
                            {form.processing ? 'Creating…' : 'Create Report'}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}

interface Communication {
    id: string; type: string; contact_person: string | null; subject: string; notes: string;
    outcome: string | null; next_action: string | null; next_action_date: string | null;
    communicated_at: string; logged_by: string | null;
}

const COMM_TYPE_STYLES: Record<string, string> = {
    call:     'bg-blue-100 text-blue-700',
    email:    'bg-violet-100 text-violet-700',
    whatsapp: 'bg-green-100 text-green-700',
    meeting:  'bg--100 text--800',
    linkedin: 'bg-sky-100 text-sky-700',
    other:    'bg-gray-100 text-gray-700',
};

interface Props {
    client: Client & {
        projects: Array<Project & { tasks_count: number }>;
        success_score: number | null;
    };
    communications: Communication[];
}

const TIER_CONFIG: Record<string, { label: string; cls: string }> = {
    standard:   { label: 'Standard',   cls: 'bg-gray-100 text-gray-700' },
    premium:    { label: 'Premium',    cls: 'bg--50 text--800' },
    enterprise: { label: 'Enterprise', cls: 'bg-violet-50 text-violet-700' },
};

const STATUS_CONFIG: Record<string, { label: string; cls: string; dot: string }> = {
    active:   { label: 'Active',   cls: 'bg-emerald-50 text-emerald-700', dot: 'bg-emerald-400' },
    prospect: { label: 'Prospect', cls: 'bg-blue-50 text-blue-700',       dot: 'bg-blue-400' },
    inactive: { label: 'Inactive', cls: 'bg-gray-100 text-gray-700',      dot: 'bg-gray-300' },
    churned:  { label: 'Churned',  cls: 'bg--50 text--700',         dot: 'bg-red-400' },
};

const PROJECT_STATUS_STYLES: Record<string, string> = {
    planning:  'bg-gray-100 text-gray-700',
    active:    'bg-emerald-50 text-emerald-700',
    on_hold:   'bg--50 text--800',
    completed: 'bg-blue-50 text-blue-700',
    cancelled: 'bg--50 text--700',
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
                    <Button onClick={onClose}><X size={16} className="text-gray-400" /></Button>
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
                        <Button type="button" onClick={onClose} variant="ghost" >Cancel</Button>
                        <Button type="submit" disabled={form.processing || !form.data.subject || !form.data.notes}
                            className="disabled:opacity-50" >
                            {form.processing ? 'Saving…' : 'Log'}
                        </Button>
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
                    <Button onClick={onClose}><X size={16} className="text-gray-400" /></Button>
                </div>
                <form onSubmit={e => { e.preventDefault(); form.post(`/clients/${clientId}/success-score`, { onSuccess: onClose }); }} className="space-y-3">
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Score (0–100)</label>
                        <input type="number" min={0} max={100} value={form.data.overall_score}
                            onChange={e => form.setData('overall_score', e.target.value)}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div className="flex justify-end gap-2">
                        <Button type="button" onClick={onClose} variant="ghost" >Cancel</Button>
                        <Button type="submit" disabled={form.processing}
                            className="disabled:opacity-50" >
                            {form.processing ? 'Saving…' : 'Save'}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export default function ClientShow({ client, communications, proposals, sows, canReview, reports = [], surveys = [], invoices = [] }: any) {
    const [activeTab, setActiveTab] = useState<'overview' | 'projects' | 'comms' | 'documents' | 'invoicing' | 'surveys'>('overview');
    const [documentSubTab, setDocumentSubTab] = useState<'proposals'|'sows'|'reports'>('proposals');
    const [commOpen, setCommOpen] = useState(false);
    const [scoreOpen, setScoreOpen] = useState(false);
    const [reportModalOpen, setReportModalOpen] = useState(false);
    const [expandedReportId, setExpandedReportId] = useState<string | null>(null);
    const confirm = useConfirm();
    const tier   = TIER_CONFIG[client.tier]   ?? TIER_CONFIG.standard;
    const status = STATUS_CONFIG[client.status] ?? STATUS_CONFIG.inactive;

    const activeProjects = client.projects.filter(p => p.status === 'active').length;

    const handleSendSurvey = async () => {
        const ok = await confirm({
            title: 'Send NPS Survey?',
            description: `This will generate and send an NPS survey request for ${client.name}.`,
            confirmText: 'Send',
        });
        if (ok) {
            router.post('/client-surveys/send', { client_id: client.id });
        }
    };

    return (
        <AppLayout title={client.name}>
            <Head title={client.name} />

            <div className="max-w-4xl mx-auto">
                {/* Breadcrumb */}
                <div className="mb-4">
                    <Breadcrumbs items={[
                        { label: 'Clients', href: '/clients' },
                        { label: client.name }
                    ]} />
                </div>

                {/* Header card */}
                <div className="bg-white rounded-xl border border-gray-100 p-6 mb-4 shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
                    <div className="flex items-start justify-between">
                        <div className="flex items-start gap-4">
                            {/* Avatar */}
                            <div className="w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-400 to-violet-500 flex items-center justify-center shrink-0">
                                <Building2 size={24} className="text-white" />
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
                                            <Mail size={16} /> {client.email}
                                        </a>
                                    )}
                                    {client.phone && (
                                        <a href={`tel:${client.phone}`} className="flex items-center gap-1.5 text-[12px] text-gray-500 hover:text-indigo-600 transition-colors">
                                            <Phone size={16} /> {client.phone}
                                        </a>
                                    )}
                                    {client.website && (
                                        <a href={client.website} target="_blank" rel="noreferrer" className="flex items-center gap-1.5 text-[12px] text-gray-500 hover:text-indigo-600 transition-colors">
                                            <Globe size={16} /> {client.website.replace(/^https?:\/\//, '')}
                                            <ExternalLink size={12} />
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
                                <Edit size={16} /> Edit
                            </Link>
                            <Button
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
                                <Trash2 size={16} /> Delete
                            </Button>
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
                <div className="flex gap-1 mb-4 overflow-x-auto pb-1">
                    {(['overview', 'projects', 'documents', 'invoicing', 'surveys', 'comms'] as const).map(tab => (
                        <Button key={tab} onClick={() => setActiveTab(tab)}
                            className={cn('px-4 py-1.5 text-[13px] font-medium rounded-lg transition-colors whitespace-nowrap',
                                activeTab === tab ? 'bg-indigo-600 text-white' : 'text-gray-500 hover:bg-gray-100'
                            )}>
                            {tab === 'overview' ? 'Overview' : 
                             tab === 'projects' ? 'Projects' : 
                             tab === 'documents' ? 'Documents' : 
                             tab === 'invoicing' ? 'Invoicing' : 
                             tab === 'surveys' ? `NPS Feedback (${surveys.filter((s:any) => s.nps_score !== null).length})` : 
                             `Activity (${communications.length})`}
                        </Button>
                    ))}
                </div>

                {activeTab === 'overview' && (
                    <div className="space-y-4 mb-4">
                        <div className="grid grid-cols-4 gap-4">
                            {[
                                { label: 'Total Projects', value: client.projects.length },
                                { label: 'Active Projects', value: activeProjects },
                                { label: 'Total Tasks',    value: client.projects.reduce((s: any, p: any) => s + (p.tasks_count ?? 0), 0) },
                            ].map(stat => (
                                <div key={stat.label} className="bg-white rounded-xl border border-gray-100 p-4 shadow-[0_1px_3px_rgba(0,0,0,0.04)] text-center">
                                    <p className="text-2xl font-bold text-gray-900">{stat.value}</p>
                                    <p className="text-[11px] text-gray-400 mt-0.5">{stat.label}</p>
                                </div>
                            ))}
                            <Button onClick={() => setScoreOpen(true)}
                                className="bg-white rounded-xl border border-gray-100 p-4 shadow-[0_1px_3px_rgba(0,0,0,0.04)] text-center hover:border-indigo-200 transition-colors group">
                                <div className="flex items-center justify-center gap-1 mb-0.5">
                                    <Star size={16} className={client.success_score !== null ? 'text-amber-400 fill-amber-400' : 'text-gray-300'} />
                                    <p className="text-2xl font-bold text-gray-900">{client.success_score ?? '—'}</p>
                                </div>
                                <p className="text-[11px] text-gray-400">Success Score</p>
                            </Button>
                        </div>
                        
                        <div className="bg-white rounded-xl border border-gray-100 p-6 shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
                            <h3 className="text-[13px] font-semibold text-gray-900 mb-3">Client Overview</h3>
                            {client.notes ? (
                                <p className="text-[13px] text-gray-700 leading-relaxed">{client.notes}</p>
                            ) : (
                                <p className="text-[13px] text-gray-400 italic">No notes available for this client.</p>
                            )}
                        </div>
                    </div>
                )}

                {activeTab === 'projects' && (
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
                                            PROJECT_STATUS_STYLES[project.status] ?? 'bg-gray-100 text-gray-700')}>
                                            {project.status.replace(/_/g, ' ')}
                                        </span>
                                    </Link>
                                ))}
                            </div>
                        )}
                    </div>
                )}
                
                {activeTab === 'documents' && (
                    <div className="space-y-6">
                        <div className="flex space-x-4 mb-4">
                            <Button onClick={() => setDocumentSubTab('proposals')} className={cn("px-4 py-2 text-sm rounded-md", documentSubTab === 'proposals' ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-gray-600 hover:bg-gray-50')}>Proposals</Button>
                            <Button onClick={() => setDocumentSubTab('sows')} className={cn("px-4 py-2 text-sm rounded-md", documentSubTab === 'sows' ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-gray-600 hover:bg-gray-50')}>Statements of Work</Button>
                            <Button onClick={() => setDocumentSubTab('reports')} className={cn("px-4 py-2 text-sm rounded-md", documentSubTab === 'reports' ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-gray-600 hover:bg-gray-50')}>Reports & Updates</Button>
                        </div>
                        {documentSubTab === 'proposals' && <ProposalsTab proposals={proposals} stats={{}} clients={[]} />}
                        {documentSubTab === 'sows' && <SowTab sows={sows} clients={[]} retainers={[]} canReview={canReview} />}
                        {documentSubTab === 'reports' && (
                            <div className="bg-white rounded-xl border border-gray-100 shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
                                <div className="px-5 py-3.5 border-b border-gray-50 flex items-center justify-between">
                                    <h3 className="text-[13px] font-semibold text-gray-900">Monthly Updates / Reports</h3>
                                    <Button onClick={() => setReportModalOpen(true)}
                                        className="flex items-center gap-1 text-[12px] text-indigo-600 hover:text-indigo-700 font-medium">
                                        <Plus size={12} /> New Report
                                    </Button>
                                </div>
                                {reports.length === 0 ? (
                                    <div className="py-12 text-center text-sm text-gray-400">
                                        No monthly updates logged for this client yet.
                                    </div>
                                ) : (
                                    <div className="divide-y divide-gray-50">
                                        {reports.map((r: any) => (
                                            <div key={r.id} className="p-5">
                                                <div className="flex items-center justify-between gap-4">
                                                    <div>
                                                        <div className="flex items-center gap-2">
                                                            <p className="text-sm font-semibold text-gray-900">
                                                                {new Date(r.month_year + '-01').toLocaleString('en-IN', { month: 'long', year: 'numeric' })}
                                                            </p>
                                                            <span className={cn('px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase', 
                                                                r.status === 'draft' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'
                                                            )}>
                                                                {r.status}
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div className="flex items-center gap-2">
                                                        {r.status === 'draft' && (
                                                            <>
                                                                <Button onClick={() => router.post(`/client-updates/${r.id}/draft`)}
                                                                    className="flex items-center gap-1 px-2.5 py-1 border border-indigo-200 text-indigo-600 text-xs font-medium rounded-lg hover:bg-indigo-50">
                                                                    <Sparkles size={12} /> AI Draft
                                                                </Button>
                                                                <Button onClick={() => router.post(`/client-updates/${r.id}/send`)}
                                                                    className="flex items-center gap-1 px-2.5 py-1 border border-emerald-200 text-emerald-600 text-xs font-medium rounded-lg hover:bg-emerald-50">
                                                                    <Send size={12} /> Send
                                                                </Button>
                                                            </>
                                                        )}
                                                        <Button onClick={() => setExpandedReportId(expandedReportId === r.id ? null : r.id)}
                                                            className="p-1 text-gray-400 hover:text-gray-600">
                                                            <ChevronDown size={16} className={cn('transition-transform', expandedReportId === r.id && 'rotate-180')} />
                                                        </Button>
                                                        <Button onClick={async () => {
                                                            const ok = await confirm({
                                                                title: 'Delete report?',
                                                                description: 'This action cannot be undone.',
                                                                confirmText: 'Delete',
                                                                variant: 'destructive',
                                                            });
                                                            if (!ok) return;
                                                            router.delete(`/client-updates/${r.id}`);
                                                        }}
                                                            className="p-1 text-gray-400 hover:text-rose-500">
                                                            <Trash2 size={16} />
                                                        </Button>
                                                    </div>
                                                </div>
                                                {(expandedReportId === r.id || reports.length === 1) && (
                                                    <div className="mt-4 pt-4 border-t border-gray-50 space-y-3">
                                                        {r.highlights && (
                                                            <div>
                                                                <p className="text-xs font-semibold text-gray-500 mb-0.5">Highlights</p>
                                                                <p className="text-sm text-gray-700 leading-relaxed">{r.highlights}</p>
                                                            </div>
                                                        )}
                                                        {r.challenges && (
                                                            <div>
                                                                <p className="text-xs font-semibold text-gray-500 mb-0.5">Challenges</p>
                                                                <p className="text-sm text-gray-700 leading-relaxed">{r.challenges}</p>
                                                            </div>
                                                        )}
                                                        {Object.keys(r.metrics ?? {}).length > 0 && (
                                                            <div>
                                                                <p className="text-xs font-semibold text-gray-500 mb-1.5">Metrics</p>
                                                                <div className="grid grid-cols-3 gap-2">
                                                                    {Object.entries(r.metrics).map(([k, v]: any) => (
                                                                        <div key={k} className="bg-gray-50 rounded-lg p-2">
                                                                            <p className="text-[10px] text-gray-500">{k}</p>
                                                                            <p className="text-xs font-semibold text-gray-900">{v}</p>
                                                                        </div>
                                                                    ))}
                                                                </div>
                                                            </div>
                                                        )}
                                                    </div>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        )}
                    </div>
                )}

                {activeTab === 'comms' && (
                    <div className="bg-white rounded-xl border border-gray-100 shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
                        <div className="px-5 py-3.5 border-b border-gray-50 flex items-center justify-between">
                            <h3 className="text-[13px] font-semibold text-gray-900">Communication Log</h3>
                            <Button onClick={() => setCommOpen(true)}
                                className="flex items-center gap-1 text-[12px] text-indigo-600 hover:text-indigo-700 font-medium">
                                <Plus size={12} /> Log
                            </Button>
                        </div>
                        {communications.length === 0 ? (
                            <div className="py-12 text-center">
                                <MessageSquare size={32} className="text-gray-200 mx-auto mb-2" />
                                <p className="text-[13px] text-gray-400">No communications logged yet.</p>
                                <Button onClick={() => setCommOpen(true)}
                                    className="mt-2 text-[12px] text-indigo-600 hover:text-indigo-700">
                                    Log the first one →
                                </Button>
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
                                                <Button onClick={async () => {
                                                    const ok = await confirm({
                                                        title: 'Delete this entry?',
                                                        description: 'This cannot be undone.',
                                                        confirmText: 'Delete',
                                                        variant: 'destructive',
                                                    });
                                                    if (!ok) return;
                                                    router.delete(`/client-communications/${c.id}`);
                                                }}
                                                    className="text-[10px] text-gray-300 hover:text-rose-400 mt-1">delete</Button>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                )}

                {activeTab === 'invoicing' && (
                    <div className="bg-white rounded-xl border border-gray-100 shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
                        <div className="p-4 border-b border-gray-50">
                            <h3 className="text-[13px] font-semibold text-gray-900">Invoices</h3>
                        </div>
                        {invoices.length === 0 ? (
                            <p className="text-[13px] text-gray-400 p-8 text-center">No invoices linked for this client.</p>
                        ) : (
                            <div className="divide-y divide-gray-50">
                                {invoices.map((inv: any) => (
                                    <div key={inv.id} className="px-5 py-3.5 flex items-center justify-between">
                                        <div>
                                            <p className="text-[13px] font-semibold text-gray-900">{inv.invoice_number}</p>
                                            <p className="text-[11px] text-gray-500 mt-0.5">
                                                Issued {inv.issue_date} {inv.due_date ? `· Due ${inv.due_date}` : ''}
                                            </p>
                                        </div>
                                        <div className="text-right">
                                            <p className="text-[13px] font-semibold text-gray-900">
                                                {new Intl.NumberFormat('en-US', { style: 'currency', currency: inv.currency }).format(inv.amount)}
                                            </p>
                                            <span className={cn('text-[10px] font-semibold px-2 py-0.5 rounded-full uppercase mt-1 inline-block', 
                                                inv.status === 'paid' ? 'bg-emerald-100 text-emerald-700' : 
                                                inv.status === 'unpaid' ? 'bg-amber-100 text-amber-700' : 
                                                inv.status === 'overdue' ? 'bg-rose-100 text-rose-700' :
                                                'bg-gray-100 text-gray-700'
                                            )}>
                                                {inv.status}
                                            </span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                )}

                {activeTab === 'surveys' && (() => {
                    const responded = surveys.filter((s: any) => s.nps_score !== null);
                    const promoters = responded.filter((s: any) => s.nps_score >= 9).length;
                    const passives = responded.filter((s: any) => s.nps_score >= 7 && s.nps_score <= 8).length;
                    const detractors = responded.filter((s: any) => s.nps_score < 7).length;
                    const avgNps = responded.length > 0 ? (responded.reduce((acc: number, s: any) => acc + (s.nps_score || 0), 0) / responded.length).toFixed(1) : '—';
                    return (
                        <div className="space-y-4">
                            {/* NPS Stats Grid */}
                            <div className="grid grid-cols-4 gap-4">
                                <div className="bg-white rounded-xl border border-gray-100 p-4 text-center shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
                                    <p className="text-2xl font-bold text-gray-900">{avgNps}</p>
                                    <p className="text-[11px] text-gray-500 mt-0.5 font-medium">Avg NPS Score</p>
                                </div>
                                <div className="bg-emerald-50/50 rounded-xl border border-emerald-100 p-4 text-center">
                                    <p className="text-2xl font-bold text-emerald-700">{promoters}</p>
                                    <p className="text-[11px] text-emerald-600 mt-0.5 font-medium">Promoters (9-10)</p>
                                </div>
                                <div className="bg-amber-50/50 rounded-xl border border-amber-100 p-4 text-center">
                                    <p className="text-2xl font-bold text-amber-700">{passives}</p>
                                    <p className="text-[11px] text-amber-600 mt-0.5 font-medium">Passives (7-8)</p>
                                </div>
                                <div className="bg-rose-50/50 rounded-xl border border-rose-100 p-4 text-center">
                                    <p className="text-2xl font-bold text-rose-700">{detractors}</p>
                                    <p className="text-[11px] text-rose-600 mt-0.5 font-medium">Detractors (0-6)</p>
                                </div>
                            </div>

                            {/* Survey List */}
                            <div className="bg-white rounded-xl border border-gray-100 shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
                                <div className="px-5 py-3.5 border-b border-gray-50 flex items-center justify-between">
                                    <h3 className="text-[13px] font-semibold text-gray-900">NPS Feedback History</h3>
                                    <Button onClick={handleSendSurvey}
                                        className="flex items-center gap-1" variant="ghost" size="icon" >
                                        <Send size={12} /> Send NPS Survey
                                    </Button>
                                </div>
                                {surveys.length === 0 ? (
                                    <div className="py-12 text-center text-sm text-gray-400">
                                        <Smile size={24} className="text-gray-300 mx-auto mb-2" />
                                        No NPS surveys sent to this client yet.
                                    </div>
                                ) : (
                                    <div className="divide-y divide-gray-50">
                                        {surveys.map((s: any) => (
                                            <div key={s.id} className="px-5 py-3.5 flex items-center gap-4 justify-between">
                                                <div className="min-w-0 flex-1">
                                                    <p className="text-[11px] text-gray-400 font-medium">
                                                        Sent {new Date(s.sent_at).toLocaleDateString('en-IN')}
                                                        {s.responded_at ? ` · Responded ${new Date(s.responded_at).toLocaleDateString('en-IN')}` : ''}
                                                    </p>
                                                    {s.feedback && (
                                                        <p className="text-xs text-gray-600 mt-1 italic font-medium leading-relaxed">
                                                            "{s.feedback}"
                                                        </p>
                                                    )}
                                                </div>
                                                <div className="flex items-center gap-3 shrink-0">
                                                    {s.nps_score !== null && (
                                                        <span className={cn('w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shadow-[0_1px_2px_rgba(0,0,0,0.05)]', 
                                                            s.nps_score >= 9 ? 'text-emerald-700 bg-emerald-100' : s.nps_score >= 7 ? 'text-amber-700 bg-amber-100' : 'text-rose-700 bg-rose-100'
                                                        )}>
                                                            {s.nps_score}
                                                        </span>
                                                    )}
                                                    <span className={cn('px-2 py-0.5 rounded-full text-[10px] font-semibold capitalize', 
                                                        s.status === 'sent' ? 'bg-blue-50 text-blue-700 border border-blue-100' : 
                                                        s.status === 'responded' ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-gray-100 text-gray-700'
                                                    )}>
                                                        {s.status}
                                                    </span>
                                                    <Button onClick={async () => {
                                                        const ok = await confirm({
                                                            title: 'Delete survey?',
                                                            description: 'This action cannot be undone.',
                                                            confirmText: 'Delete',
                                                            variant: 'destructive',
                                                        });
                                                        if (!ok) return;
                                                        router.delete(`/client-surveys/${s.id}`);
                                                    }}
                                                        className="p-1 text-gray-400 hover:text-rose-500 rounded hover:bg-rose-50 transition-colors">
                                                        <Trash2 size={16} />
                                                    </Button>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </div>
                    );
                })()}

                {commOpen && <AddCommModal clientId={client.id} onClose={() => setCommOpen(false)} />}
                {scoreOpen && <ScoreModal clientId={client.id} current={client.success_score} onClose={() => setScoreOpen(false)} />}
                {reportModalOpen && <ReportModal clientId={client.id} onClose={() => setReportModalOpen(false)} />}
            </div>
        </AppLayout>
    );
}

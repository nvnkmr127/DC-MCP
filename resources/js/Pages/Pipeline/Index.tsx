import React, { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Plus, X, TrendingUp, Users, DollarSign, ArrowRight, Phone, Mail } from 'lucide-react';
import { cn } from '@/lib/utils';

type Stage = 'lead' | 'meeting_scheduled' | 'proposal_sent' | 'negotiation' | 'won' | 'lost';

interface Prospect {
    id: string; company_name: string; contact_name: string | null; contact_email: string | null;
    source: string; stage: Stage; estimated_value: number; currency: string;
    probability: number; weighted_value: number; services_interested: string[] | null;
    expected_close_date: string | null; lost_reason: string | null; notes: string | null;
    activities_count: number; last_activity_at: string | null;
    assignee: { id: string; name: string } | null;
    client: { id: string; name: string } | null;
    proposals: { id: string; title: string; status: string; total_value: number }[];
}
interface Props {
    prospects: Prospect[];
    byStage: Record<Stage, Prospect[]>;
    totalPipeline: number;
    weightedPipeline: number;
    team: { id: string; name: string }[];
}

const fmt = (n: number) =>
    '₹' + new Intl.NumberFormat('en-IN', { maximumFractionDigits: 0 }).format(n);

const STAGES: { key: Stage; label: string; color: string; bg: string; header: string }[] = [
    { key: 'lead',              label: 'Lead',              color: 'text-slate-700',  bg: 'bg-slate-50',  header: 'bg-slate-100 border-slate-200' },
    { key: 'meeting_scheduled', label: 'Meeting',           color: 'text-blue-700',   bg: 'bg-blue-50',   header: 'bg-blue-100 border-blue-200' },
    { key: 'proposal_sent',     label: 'Proposal Sent',     color: 'text-violet-700', bg: 'bg-violet-50', header: 'bg-violet-100 border-violet-200' },
    { key: 'negotiation',       label: 'Negotiation',       color: 'text-orange-700', bg: 'bg-orange-50', header: 'bg-orange-100 border-orange-200' },
    { key: 'won',               label: 'Won',               color: 'text-emerald-700',bg: 'bg-emerald-50',header: 'bg-emerald-100 border-emerald-200' },
    { key: 'lost',              label: 'Lost',              color: 'text-rose-700',   bg: 'bg-rose-50',   header: 'bg-rose-100 border-rose-200' },
];

const STAGE_FLOW: Stage[] = ['lead', 'meeting_scheduled', 'proposal_sent', 'negotiation', 'won'];

function ProspectCard({ prospect }: { prospect: Prospect }) {
    const nextStage = STAGE_FLOW[STAGE_FLOW.indexOf(prospect.stage) + 1];

    return (
        <div className="bg-white rounded-xl border border-gray-200 p-4 space-y-2 shadow-sm hover:shadow-md transition-shadow">
            <div className="flex items-start justify-between gap-2">
                <p className="font-semibold text-gray-900 text-sm leading-tight">{prospect.company_name}</p>
                <span className="text-xs font-medium text-gray-500 shrink-0">{prospect.probability}%</span>
            </div>
            {prospect.contact_name && (
                <p className="text-xs text-gray-500">{prospect.contact_name}</p>
            )}
            <div className="flex items-center gap-3 text-xs text-gray-500">
                {prospect.contact_email && (
                    <span className="flex items-center gap-1"><Mail className="w-3 h-3" />{prospect.contact_email}</span>
                )}
            </div>
            <div className="pt-1 border-t border-gray-100 flex items-center justify-between">
                <div>
                    <p className="text-sm font-bold text-gray-900">{fmt(prospect.estimated_value)}</p>
                    <p className="text-xs text-gray-400">Weighted: {fmt(prospect.weighted_value)}</p>
                </div>
                {nextStage && (
                    <button
                        onClick={() => router.patch(`/prospects/${prospect.id}`, { stage: nextStage })}
                        className="flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-800 font-medium"
                    >
                        Move <ArrowRight className="w-3 h-3" />
                    </button>
                )}
            </div>
            {prospect.expected_close_date && (
                <p className="text-xs text-gray-400">Close: {prospect.expected_close_date}</p>
            )}

            {/* Client Linking / Proposals Section */}
            <div className="pt-2 mt-2 border-t border-gray-100">
                {prospect.client ? (
                    <div className="space-y-2">
                        <div className="flex items-center justify-between">
                            <span className="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Linked Client</span>
                            <a href={`/clients/${prospect.client.id}`} className="text-xs font-medium text-indigo-600 hover:text-indigo-800">
                                {prospect.client.name}
                            </a>
                        </div>
                        {prospect.proposals && prospect.proposals.length > 0 && (
                            <div className="space-y-1">
                                {prospect.proposals.map(prop => (
                                    <div key={prop.id} className="flex items-center justify-between bg-gray-50 p-1.5 rounded text-xs">
                                        <span className="truncate max-w-[100px]" title={prop.title}>{prop.title}</span>
                                        <span className={cn('px-1.5 py-0.5 rounded-full text-[9px] font-medium uppercase', 
                                            prop.status === 'accepted' ? 'bg-green-100 text-green-700' : 
                                            prop.status === 'sent' ? 'bg-blue-100 text-blue-700' : 'bg-gray-200 text-gray-700'
                                        )}>
                                            {prop.status}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                ) : (
                    <button
                        onClick={() => router.post(`/prospects/${prospect.id}/convert`)}
                        className="w-full py-1.5 text-xs font-medium text-gray-600 bg-gray-50 border border-gray-200 rounded hover:bg-gray-100 hover:text-gray-900 transition-colors"
                    >
                        Convert to Client
                    </button>
                )}
            </div>

            {prospect.assignee && (
                <div className="flex items-center gap-1.5 text-xs text-gray-500 pt-1">
                    <div className="w-5 h-5 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-semibold">
                        {prospect.assignee.name[0]}
                    </div>
                    {prospect.assignee.name}
                </div>
            )}
        </div>
    );
}

export default function PipelineIndex({ prospects, byStage, totalPipeline, weightedPipeline, team }: Props) {
    const [showCreate, setShowCreate] = useState(false);

    const form = useForm({
        company_name: '', contact_name: '', contact_email: '', contact_phone: '',
        source: 'inbound', stage: 'lead' as Stage, estimated_value: '',
        probability: '20', assigned_to: '', notes: '', expected_close_date: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/prospects', { onSuccess: () => { setShowCreate(false); form.reset(); } });
    };

    return (
        <AppLayout>
            <Head title="Sales Pipeline" />

            <div className="max-w-[1400px] mx-auto px-4 py-6 space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Sales Pipeline</h1>
                        <p className="text-sm text-gray-500 mt-0.5">Track prospects from lead to close</p>
                    </div>
                    <button
                        onClick={() => setShowCreate(true)}
                        className="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors"
                    >
                        <Plus className="w-4 h-4" /> Add Prospect
                    </button>
                </div>

                {/* Stats */}
                <div className="grid grid-cols-3 gap-4">
                    {[
                        { label: 'Total Pipeline', value: fmt(totalPipeline), icon: DollarSign, color: 'text-violet-600', bg: 'bg-violet-50' },
                        { label: 'Weighted Pipeline', value: fmt(weightedPipeline), icon: TrendingUp, color: 'text-indigo-600', bg: 'bg-indigo-50' },
                        { label: 'Active Deals', value: String(prospects.filter(p => !['won','lost'].includes(p.stage)).length), icon: Users, color: 'text-blue-600', bg: 'bg-blue-50' },
                    ].map(({ label, value, icon: Icon, color, bg }) => (
                        <div key={label} className="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-4">
                            <div className={cn('w-10 h-10 rounded-xl flex items-center justify-center', bg)}>
                                <Icon className={cn('w-5 h-5', color)} />
                            </div>
                            <div>
                                <p className="text-xs text-gray-500">{label}</p>
                                <p className="text-lg font-bold text-gray-900">{value}</p>
                            </div>
                        </div>
                    ))}
                </div>

                {/* Kanban */}
                <div className="overflow-x-auto pb-4">
                    <div className="flex gap-4 min-w-max">
                        {STAGES.map(({ key, label, header }) => (
                            <div key={key} className="w-64 shrink-0">
                                <div className={cn('flex items-center justify-between px-3 py-2 rounded-t-xl border', header)}>
                                    <span className="text-xs font-semibold uppercase tracking-wide">{label}</span>
                                    <span className="text-xs font-bold">{(byStage[key] ?? []).length}</span>
                                </div>
                                <div className="bg-gray-50 rounded-b-xl border border-t-0 border-gray-200 p-2 space-y-2 min-h-32">
                                    {(byStage[key] ?? []).map(p => (
                                        <ProspectCard key={p.id} prospect={p} />
                                    ))}
                                    {(byStage[key] ?? []).length === 0 && (
                                        <p className="text-xs text-gray-400 text-center py-6">No deals</p>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>

            {/* Create Modal */}
            {showCreate && (
                <div className="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
                    <div className="bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
                        <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100 sticky top-0 bg-white">
                            <h2 className="text-base font-semibold text-gray-900">Add Prospect</h2>
                            <button onClick={() => setShowCreate(false)} className="text-gray-400 hover:text-gray-600">
                                <X className="w-5 h-5" />
                            </button>
                        </div>
                        <form onSubmit={submit} className="px-6 py-4 space-y-4">
                            <div className="grid grid-cols-2 gap-3">
                                <div className="col-span-2">
                                    <label className="block text-xs font-medium text-gray-700 mb-1">Company Name *</label>
                                    <input type="text" value={form.data.company_name} onChange={e => form.setData('company_name', e.target.value)}
                                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                                    {form.errors.company_name && <p className="text-xs text-rose-500 mt-1">{form.errors.company_name}</p>}
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-gray-700 mb-1">Contact Name</label>
                                    <input type="text" value={form.data.contact_name} onChange={e => form.setData('contact_name', e.target.value)}
                                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-gray-700 mb-1">Contact Email</label>
                                    <input type="email" value={form.data.contact_email} onChange={e => form.setData('contact_email', e.target.value)}
                                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-gray-700 mb-1">Source</label>
                                    <select value={form.data.source} onChange={e => form.setData('source', e.target.value)}
                                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                        {['referral','inbound','outbound','cold','event','social'].map(s => (
                                            <option key={s} value={s} className="capitalize">{s.charAt(0).toUpperCase() + s.slice(1)}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-gray-700 mb-1">Stage</label>
                                    <select value={form.data.stage} onChange={e => form.setData('stage', e.target.value as Stage)}
                                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                        {STAGES.slice(0, 4).map(s => <option key={s.key} value={s.key}>{s.label}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-gray-700 mb-1">Est. Value (₹)</label>
                                    <input type="number" min="0" value={form.data.estimated_value} onChange={e => form.setData('estimated_value', e.target.value)}
                                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-gray-700 mb-1">Probability (%)</label>
                                    <input type="number" min="0" max="100" value={form.data.probability} onChange={e => form.setData('probability', e.target.value)}
                                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-gray-700 mb-1">Assigned To</label>
                                    <select value={form.data.assigned_to} onChange={e => form.setData('assigned_to', e.target.value)}
                                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                        <option value="">Unassigned</option>
                                        {team.map(m => <option key={m.id} value={m.id}>{m.name}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-gray-700 mb-1">Expected Close</label>
                                    <input type="date" value={form.data.expected_close_date} onChange={e => form.setData('expected_close_date', e.target.value)}
                                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                                </div>
                                <div className="col-span-2">
                                    <label className="block text-xs font-medium text-gray-700 mb-1">Notes</label>
                                    <textarea rows={3} value={form.data.notes} onChange={e => form.setData('notes', e.target.value)}
                                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 resize-none" />
                                </div>
                            </div>
                            <div className="flex justify-end gap-3 pt-2">
                                <button type="button" onClick={() => setShowCreate(false)} className="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancel</button>
                                <button type="submit" disabled={form.processing}
                                    className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                                    {form.processing ? 'Saving…' : 'Add Prospect'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}

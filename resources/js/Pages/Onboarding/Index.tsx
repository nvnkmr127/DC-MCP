import React, { useState } from 'react';
import { Button } from '@/Components/ui/Button';
import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { UserCheck, ChevronRight, CheckCircle2, Circle, ArrowRight, Plus, Star } from 'lucide-react';
import { cn } from '@/lib/utils';

const STAGES = [
    'prospect_won',
    'kickoff_scheduled',
    'kickoff_done',
    'access_shared',
    'sow_signed',
    'first_deliverable_sent',
    'active',
] as const;

const STAGE_LABELS: Record<string, string> = {
    prospect_won:           'Prospect Won',
    kickoff_scheduled:      'Kickoff Scheduled',
    kickoff_done:           'Kickoff Done',
    access_shared:          'Access Shared',
    sow_signed:             'SOW Signed',
    first_deliverable_sent: 'First Deliverable',
    active:                 'Active',
};

const STAGE_COLORS: Record<string, string> = {
    prospect_won:           'bg-purple-50 border-purple-200',
    kickoff_scheduled:      'bg-blue-50 border-blue-200',
    kickoff_done:           'bg-sky-50 border-sky-200',
    access_shared:          'bg-teal-50 border-teal-200',
    sow_signed:             'bg-amber-50 border-amber-200',
    first_deliverable_sent: 'bg-orange-50 border-orange-200',
    active:                 'bg-emerald-50 border-emerald-200',
};

const STAGE_HEADER_COLORS: Record<string, string> = {
    prospect_won:           'text-purple-700 bg-purple-100',
    kickoff_scheduled:      'text-blue-700 bg-blue-100',
    kickoff_done:           'text-sky-700 bg-sky-100',
    access_shared:          'text-teal-700 bg-teal-100',
    sow_signed:             'text-amber-700 bg-amber-100',
    first_deliverable_sent: 'text-orange-700 bg-orange-100',
    active:                 'text-emerald-700 bg-emerald-100',
};

interface ChecklistItem { key: string; label: string; done: boolean; }

interface Onboarding {
    id: string;
    client_id: string;
    stage: string;
    checklist: ChecklistItem[];
    target_go_live: string | null;
    nps_score: number | null;
    days_in_stage: number;
    client: { id: string; name: string; company_name: string | null; };
}

interface Props {
    onboardings: Onboarding[];
    byStage: Record<string, Onboarding[]>;
    clients: { id: string; name: string; company_name: string | null; }[];
    totalActive: number;
    stalled: number;
}

function ChecklistPanel({ onboarding }: { onboarding: Onboarding }) {
    const progress = onboarding.checklist.length > 0
        ? Math.round((onboarding.checklist.filter(i => i.done).length / onboarding.checklist.length) * 100)
        : 0;

    return (
        <div className="space-y-1.5 mt-2">
            <div className="flex items-center justify-between mb-1">
                <span className="text-xs text-gray-500">{progress}% done</span>
                <span className="text-xs text-gray-400">{onboarding.checklist.filter(i => i.done).length}/{onboarding.checklist.length}</span>
            </div>
            <div className="w-full bg-gray-100 rounded-full h-1.5 mb-2">
                <div className={cn('h-1.5 rounded-full', progress === 100 ? 'bg-emerald-500' : progress > 50 ? 'bg-amber-400' : 'bg-indigo-500')}
                    style={{ width: `${progress}%` }} />
            </div>
            {onboarding.checklist.map(item => (
                <Button key={item.key} onClick={() => router.post(`/onboarding/${onboarding.id}/checklist`, { key: item.key })}
                    className="flex items-center gap-2 w-full text-left hover:bg-white/60 rounded px-1 py-0.5 group">
                    {item.done
                        ? <CheckCircle2 className="w-3.5 h-3.5 text-emerald-500 flex-shrink-0" />
                        : <Circle className="w-3.5 h-3.5 text-gray-300 group-hover:text-gray-400 flex-shrink-0" />
                    }
                    <span className={cn('text-xs', item.done ? 'line-through text-gray-400' : 'text-gray-700')}>{item.label}</span>
                </Button>
            ))}
        </div>
    );
}

function OnboardingCard({ onboarding, isLast }: { onboarding: Onboarding; isLast: boolean }) {
    const [expanded, setExpanded] = useState(false);
    const [npsOpen, setNpsOpen] = useState(false);
    const stalled = onboarding.days_in_stage > 5;
    const stageIdx = STAGES.indexOf(onboarding.stage as typeof STAGES[number]);

    return (
        <div className={cn('bg-white rounded-lg border p-3 space-y-2', stalled ? 'border-rose-200' : 'border-gray-200')}>
            <div className="flex items-start justify-between gap-2">
                <div>
                    <p className="font-semibold text-gray-900 text-sm leading-tight">
                        {onboarding.client.company_name || onboarding.client.name}
                    </p>
                    {stalled && (
                        <span className="text-xs text-rose-500 font-medium">{onboarding.days_in_stage}d in stage</span>
                    )}
                </div>
                <div className="flex items-center gap-1">
                    {onboarding.stage !== 'active' && !isLast && (
                        <Button onClick={() => router.post(`/onboarding/${onboarding.id}/advance`)}
                            className="flex items-center gap-1 px-2 py-1 bg-indigo-600 text-white text-xs rounded-lg hover:bg-indigo-700 font-medium">
                            <ArrowRight className="w-3 h-3" /> Next
                        </Button>
                    )}
                    {onboarding.stage === 'active' && (
                        <Button onClick={() => setNpsOpen(!npsOpen)}
                            className="flex items-center gap-1 px-2 py-1 bg-amber-500 text-white text-xs rounded-lg hover:bg-amber-600 font-medium">
                            <Star className="w-3 h-3" /> NPS
                        </Button>
                    )}
                </div>
            </div>

            {npsOpen && (
                <NpsInput onboardingId={onboarding.id} current={onboarding.nps_score} onClose={() => setNpsOpen(false)} />
            )}

            <Button onClick={() => setExpanded(!expanded)}
                className="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                {expanded ? 'Hide checklist' : 'Show checklist'}
            </Button>

            {expanded && <ChecklistPanel onboarding={onboarding} />}
        </div>
    );
}

function NpsInput({ onboardingId, current, onClose }: { onboardingId: string; current: number | null; onClose: () => void }) {
    const form = useForm({ nps_score: String(current ?? '') });
    return (
        <form onSubmit={e => { e.preventDefault(); form.post(`/onboarding/${onboardingId}/nps`, { onSuccess: onClose }); }}
            className="flex items-center gap-2 bg-amber-50 rounded-lg p-2">
            <span className="text-xs text-amber-700 font-medium">NPS (0-10):</span>
            <input type="number" min="0" max="10" value={form.data.nps_score}
                onChange={e => form.setData('nps_score', e.target.value)}
                className="w-16 border border-amber-200 rounded px-2 py-1 text-xs focus:ring-1 focus:ring-amber-400" />
            <Button type="submit" disabled={form.processing}
                className="disabled:opacity-50" variant="ghost" size="sm" >
                Save
            </Button>
            <Button type="button" onClick={onClose} variant="ghost" size="sm" >Cancel</Button>
        </form>
    );
}

function AddOnboardingModal({ clients, onClose }: { clients: Props['clients']; onClose: () => void }) {
    const form = useForm({ client_id: '', target_go_live: '' });
    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-4">
                <h2 className="text-lg font-bold text-gray-900">Add Client to Onboarding</h2>
                <form onSubmit={e => {
                    e.preventDefault();
                    form.post('/onboarding', { onSuccess: onClose });
                }} className="space-y-3">
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Client</label>
                        <select value={form.data.client_id} onChange={e => form.setData('client_id', e.target.value)}
                            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mt-1 focus:ring-2 focus:ring-indigo-500">
                            <option value="">Select client…</option>
                            {clients.map(c => (
                                <option key={c.id} value={c.id}>{c.company_name || c.name}</option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Target Go-Live Date</label>
                        <input type="date" value={form.data.target_go_live} onChange={e => form.setData('target_go_live', e.target.value)}
                            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mt-1 focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div className="flex justify-end gap-2 pt-2">
                        <Button type="button" onClick={onClose} variant="ghost" >Cancel</Button>
                        <Button type="submit" disabled={form.processing || !form.data.client_id}
                            className="disabled:opacity-50" >
                            {form.processing ? 'Adding…' : 'Add Client'}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export default function OnboardingIndex({ onboardings, byStage, clients, totalActive, stalled }: Props) {
    const [addOpen, setAddOpen] = useState(false);

    return (
        <AppLayout>
            <Head title="Client Onboarding" />
            <div className="px-4 py-6 space-y-6 max-w-screen-2xl mx-auto">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Client Onboarding</h1>
                        <p className="text-sm text-gray-500 mt-0.5">Track clients through the 7-stage onboarding pipeline</p>
                    </div>
                    <Button onClick={() => setAddOpen(true)}
                        className="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                        <Plus className="w-4 h-4" /> Add Client
                    </Button>
                </div>

                {/* Stats */}
                <div className="grid grid-cols-3 gap-4 max-w-lg">
                    <div className="bg-white rounded-xl border border-gray-200 p-4 text-center">
                        <p className="text-xs text-gray-500">Total Active</p>
                        <p className="text-2xl font-bold text-gray-900 mt-1">{totalActive}</p>
                    </div>
                    <div className="bg-white rounded-xl border border-gray-200 p-4 text-center">
                        <p className="text-xs text-gray-500">Stalled (&gt;5d)</p>
                        <p className="text-2xl font-bold text-rose-600 mt-1">{stalled}</p>
                    </div>
                    <div className="bg-white rounded-xl border border-gray-200 p-4 text-center">
                        <p className="text-xs text-gray-500">Fully Active</p>
                        <p className="text-2xl font-bold text-emerald-600 mt-1">{byStage['active']?.length ?? 0}</p>
                    </div>
                </div>

                {/* Kanban */}
                <div className="overflow-x-auto pb-4">
                    <div className="flex gap-3 min-w-max">
                        {STAGES.map((stage, idx) => {
                            const cards = byStage[stage] ?? [];
                            return (
                                <div key={stage} className={cn('w-64 rounded-xl border p-3 space-y-3', STAGE_COLORS[stage])}>
                                    <div className="flex items-center justify-between">
                                        <span className={cn('text-xs font-semibold px-2 py-1 rounded-full', STAGE_HEADER_COLORS[stage])}>
                                            {STAGE_LABELS[stage]}
                                        </span>
                                        <span className="text-xs text-gray-500 font-medium">{cards.length}</span>
                                    </div>
                                    {cards.length === 0 && (
                                        <div className="text-center py-6">
                                            <UserCheck className="w-6 h-6 text-gray-300 mx-auto mb-1" />
                                            <p className="text-xs text-gray-400">No clients</p>
                                        </div>
                                    )}
                                    {cards.map(ob => (
                                        <OnboardingCard key={ob.id} onboarding={ob} isLast={idx === STAGES.length - 1} />
                                    ))}
                                </div>
                            );
                        })}
                    </div>
                </div>

                {addOpen && <AddOnboardingModal clients={clients} onClose={() => setAddOpen(false)} />}
            </div>
        </AppLayout>
    );
}

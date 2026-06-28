import React, { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { cn } from '@/lib/utils';
import { Plus, X, Star, ChevronRight, Users, Briefcase, Search } from 'lucide-react';

interface Candidate {
    id: string; name: string; email: string | null; phone: string | null;
    source: string; stage: string; rating: number | null; notes: string | null;
    rejected_reason: string | null; hired_at: string | null;
}
interface Opening {
    id: string; title: string; department: string | null; status: string;
    salary_min: number | null; salary_max: number | null; target_date: string | null;
    candidates_count: number; candidates: Candidate[];
}
interface Props { openings: Opening[]; }

const STAGES = ['applied', 'screening', 'interview_1', 'interview_2', 'offer', 'hired', 'rejected'];
const STAGE_LABELS: Record<string, string> = {
    applied: 'Applied', screening: 'Screening', interview_1: 'Interview 1',
    interview_2: 'Interview 2', offer: 'Offer', hired: 'Hired', rejected: 'Rejected',
};
const STAGE_COLORS: Record<string, string> = {
    applied: 'bg-gray-100 text-gray-700', screening: 'bg-blue-100 text-blue-700',
    interview_1: 'bg-indigo-100 text-indigo-700', interview_2: 'bg-violet-100 text-violet-700',
    offer: 'bg--100 text--800', hired: 'bg-emerald-100 text-emerald-700',
    rejected: 'bg--100 text--700',
};
const STATUS_COLORS: Record<string, string> = {
    open: 'bg-emerald-100 text-emerald-700', on_hold: 'bg--100 text--800', closed: 'bg-gray-100 text-gray-700',
};

function OpeningModal({ onClose }: { onClose: () => void }) {
    const form = useForm({ title: '', department: '', salary_min: '', salary_max: '', description: '', target_date: '' });
    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-3">
                <div className="flex items-center justify-between">
                    <h2 className="text-[15px] font-bold text-gray-900">Add Job Opening</h2>
                    <button onClick={onClose}><X size={16} className="text-gray-400" /></button>
                </div>
                <form onSubmit={e => { e.preventDefault(); form.post('/hiring/openings', { onSuccess: onClose }); }} className="space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                        <div className="col-span-2">
                            <label className="text-xs text-gray-500 font-medium">Title *</label>
                            <input type="text" value={form.data.title} onChange={e => form.setData('title', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Department</label>
                            <input type="text" value={form.data.department} onChange={e => form.setData('department', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Target Date</label>
                            <input type="date" value={form.data.target_date} onChange={e => form.setData('target_date', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Salary Min (₹)</label>
                            <input type="number" value={form.data.salary_min} onChange={e => form.setData('salary_min', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Salary Max (₹)</label>
                            <input type="number" value={form.data.salary_max} onChange={e => form.setData('salary_max', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                        </div>
                    </div>
                    <div className="flex justify-end gap-2 pt-1">
                        <button type="button" onClick={onClose} className="px-4 py-2 text-sm text-gray-600">Cancel</button>
                        <button type="submit" disabled={form.processing || !form.data.title}
                            className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                            {form.processing ? 'Adding…' : 'Add Opening'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

function CandidateModal({ opening, onClose }: { opening: Opening; onClose: () => void }) {
    const form = useForm({ name: '', email: '', phone: '', source: 'direct', notes: '' });
    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-3">
                <div className="flex items-center justify-between">
                    <h2 className="text-[15px] font-bold text-gray-900">Add Candidate — {opening.title}</h2>
                    <button onClick={onClose}><X size={16} className="text-gray-400" /></button>
                </div>
                <form onSubmit={e => { e.preventDefault(); form.post(`/hiring/openings/${opening.id}/candidates`, { onSuccess: onClose }); }} className="space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                        <div className="col-span-2">
                            <label className="text-xs text-gray-500 font-medium">Name *</label>
                            <input type="text" value={form.data.name} onChange={e => form.setData('name', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Email</label>
                            <input type="email" value={form.data.email} onChange={e => form.setData('email', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Phone</label>
                            <input type="text" value={form.data.phone} onChange={e => form.setData('phone', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div className="col-span-2">
                            <label className="text-xs text-gray-500 font-medium">Source</label>
                            <select value={form.data.source} onChange={e => form.setData('source', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                {['referral', 'job_portal', 'linkedin', 'direct', 'other'].map(s => (
                                    <option key={s} value={s}>{s.replace('_', ' ')}</option>
                                ))}
                            </select>
                        </div>
                        <div className="col-span-2">
                            <label className="text-xs text-gray-500 font-medium">Notes</label>
                            <textarea value={form.data.notes} onChange={e => form.setData('notes', e.target.value)} rows={3}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 resize-none" />
                        </div>
                    </div>
                    <div className="flex justify-end gap-2 pt-1">
                        <button type="button" onClick={onClose} className="px-4 py-2 text-sm text-gray-600">Cancel</button>
                        <button type="submit" disabled={form.processing || !form.data.name}
                            className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                            {form.processing ? 'Adding…' : 'Add Candidate'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export default function HiringIndex({ openings }: Props) {
    const [newOpen, setNewOpen] = useState(false);
    const [selectedOpening, setSelectedOpening] = useState<Opening | null>(openings[0] ?? null);
    const [addCandidate, setAddCandidate] = useState<Opening | null>(null);
    const [expandedCandidate, setExpandedCandidate] = useState<string | null>(null);
    const [searchQuery, setSearchQuery] = useState('');
    const [stageFilter, setStageFilter] = useState<string>('');

    const formatSalary = (n: number) => '₹' + new Intl.NumberFormat('en-IN', { maximumFractionDigits: 0 }).format(n);

    return (
        <AppLayout title="Hiring Pipeline">
            <Head title="Hiring Pipeline" />
            <div className="flex gap-5 h-[calc(100vh-130px)]">
                {/* Left panel */}
                <div className="w-72 flex-shrink-0 flex flex-col">
                    <div className="flex items-center justify-between mb-3">
                        <h1 className="text-base font-bold text-gray-900">Job Openings</h1>
                        <button onClick={() => setNewOpen(true)}
                            className="flex items-center gap-1 px-2.5 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-lg hover:bg-indigo-700">
                            <Plus size={12} /> Add
                        </button>
                    </div>
                    <div className="overflow-y-auto space-y-2 flex-1">
                        {openings.map(o => (
                            <button key={o.id} onClick={() => setSelectedOpening(o)}
                                className={cn('w-full text-left bg-white rounded-xl border p-3.5 hover:border-indigo-200 transition-all',
                                    selectedOpening?.id === o.id ? 'border-indigo-300 shadow-sm' : 'border-gray-200')}>
                                <div className="flex items-start justify-between gap-2 mb-1.5">
                                    <p className="text-sm font-semibold text-gray-900 leading-tight">{o.title}</p>
                                    <span className={cn('px-1.5 py-0.5 rounded text-[10px] font-semibold shrink-0', STATUS_COLORS[o.status] ?? STATUS_COLORS.open)}>
                                        {o.status.replace('_', ' ')}
                                    </span>
                                </div>
                                {o.department && <p className="text-xs text-gray-500 mb-1">{o.department}</p>}
                                {(o.salary_min || o.salary_max) && (
                                    <p className="text-xs text-gray-500">
                                        {o.salary_min ? formatSalary(o.salary_min) : '?'} – {o.salary_max ? formatSalary(o.salary_max) : '?'} / yr
                                    </p>
                                )}
                                <div className="flex items-center gap-1 mt-2">
                                    <Users size={11} className="text-gray-400" />
                                    <span className="text-xs text-gray-500">{o.candidates_count} candidate{o.candidates_count !== 1 ? 's' : ''}</span>
                                </div>
                            </button>
                        ))}
                        {openings.length === 0 && (
                            <div className="bg-white rounded-xl border border-dashed border-gray-200 p-6 text-center">
                                <Briefcase size={20} className="text-gray-300 mx-auto mb-2" />
                                <p className="text-xs text-gray-400">No openings yet</p>
                            </div>
                        )}
                    </div>
                </div>

                {/* Right panel — pipeline */}
                {selectedOpening && (
                    <div className="flex-1 min-w-0 flex flex-col">
                        <div className="flex items-center justify-between mb-3">
                            <div>
                                <h2 className="text-base font-bold text-gray-900">{selectedOpening.title}</h2>
                                <p className="text-xs text-gray-500">{selectedOpening.candidates.length} candidates</p>
                            </div>
                            <div className="flex items-center gap-2">
                                <div className="relative">
                                    <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 w-3.5 h-3.5" />
                                    <input
                                        type="text"
                                        placeholder="Search candidates..."
                                        value={searchQuery}
                                        onChange={e => setSearchQuery(e.target.value)}
                                        className="pl-8 pr-3 py-1.5 border border-gray-200 rounded-lg text-xs focus:ring-2 focus:ring-indigo-500 w-48"
                                    />
                                </div>
                                <select 
                                    value={stageFilter} 
                                    onChange={e => setStageFilter(e.target.value)}
                                    className="border border-gray-200 rounded-lg text-xs py-1.5 pl-3 pr-8 focus:ring-2 focus:ring-indigo-500"
                                >
                                    <option value="">All Stages</option>
                                    {STAGES.map(s => <option key={s} value={s}>{STAGE_LABELS[s]}</option>)}
                                </select>
                                <button onClick={() => setAddCandidate(selectedOpening)}
                                    className="flex items-center gap-1.5 px-3 py-1.5 border border-gray-200 text-sm text-gray-600 rounded-lg hover:bg-gray-50">
                                    <Plus size={13} /> Add
                                </button>
                            </div>
                        </div>
                        <div className="flex gap-3 overflow-x-auto pb-3 flex-1">
                            {STAGES.filter(s => !stageFilter || s === stageFilter).map(stage => {
                                const cols = selectedOpening.candidates.filter(c => 
                                    c.stage === stage &&
                                    (c.name || '').toLowerCase().includes(searchQuery.toLowerCase())
                                );
                                return (
                                    <div key={stage} className="flex-shrink-0 w-48">
                                        <div className="flex items-center justify-between mb-2">
                                            <p className="text-xs font-semibold text-gray-600">{STAGE_LABELS[stage]}</p>
                                            <span className="text-xs text-gray-400">{cols.length}</span>
                                        </div>
                                        <div className="space-y-2">
                                            {cols.map(c => (
                                                <div key={c.id}
                                                    className="bg-white rounded-xl border border-gray-200 p-3 cursor-pointer hover:border-indigo-200 transition-all"
                                                    onClick={() => setExpandedCandidate(expandedCandidate === c.id ? null : c.id)}>
                                                    <p className="text-xs font-semibold text-gray-900">{c.name}</p>
                                                    <span className={cn('inline-block mt-1 px-1.5 py-0.5 rounded text-[9px] font-medium', STAGE_COLORS[c.source] ?? 'bg-gray-100 text-gray-700')}>
                                                        {c.source}
                                                    </span>
                                                    {c.rating && (
                                                        <div className="flex gap-0.5 mt-1.5">
                                                            {[1,2,3,4,5].map(i => (
                                                                <Star key={i} size={9} className={i <= c.rating! ? 'text-amber-400 fill-amber-400' : 'text-gray-200'} />
                                                            ))}
                                                        </div>
                                                    )}
                                                    {expandedCandidate === c.id && (
                                                        <div className="mt-2 pt-2 border-t border-gray-100 space-y-1.5">
                                                            {c.email && <p className="text-[10px] text-gray-500">{c.email}</p>}
                                                            {c.phone && <p className="text-[10px] text-gray-500">{c.phone}</p>}
                                                            {c.notes && <p className="text-[10px] text-gray-600">{c.notes}</p>}
                                                            <div className="flex flex-wrap gap-1 pt-1">
                                                                {STAGES.filter(s => s !== c.stage).slice(0, 2).map(s => (
                                                                    <button key={s}
                                                                        onClick={e => { e.stopPropagation(); router.patch(`/hiring/candidates/${c.id}`, { stage: s }); }}
                                                                        className="px-1.5 py-0.5 text-[9px] border border-gray-200 rounded text-gray-500 hover:bg-gray-50">
                                                                        → {STAGE_LABELS[s]}
                                                                    </button>
                                                                ))}
                                                            </div>
                                                        </div>
                                                    )}
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                )}
            </div>

            {newOpen && <OpeningModal onClose={() => setNewOpen(false)} />}
            {addCandidate && <CandidateModal opening={addCandidate} onClose={() => setAddCandidate(null)} />}
        </AppLayout>
    );
}

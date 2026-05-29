import React, { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { cn } from '@/lib/utils';
import { Plus, X, ChevronDown, UserX } from 'lucide-react';

interface Assignment { id: string; project: { id: string; name: string } | null; agreed_rate: number | null; start_date: string | null; end_date: string | null; hours_worked: number; total_paid: number; status: string; notes: string | null; }
interface Freelancer { id: string; name: string; email: string | null; phone: string | null; skill_set: string | null; status: string; rate_per_hour: number | null; payment_method: string | null; notes: string | null; assignments_count: number; assignments: Assignment[]; }
interface Project { id: string; name: string; }
interface Props { freelancers: Freelancer[]; projects: Project[]; }

const STATUS_STYLES: Record<string, string> = { active: 'bg-emerald-100 text-emerald-700', inactive: 'bg-gray-100 text-gray-500', blacklisted: 'bg-rose-100 text-rose-600' };

const fmt = (n: number) => '₹' + new Intl.NumberFormat('en-IN', { maximumFractionDigits: 0 }).format(n);

function FreelancerModal({ onClose }: { onClose: () => void }) {
    const form = useForm({ name: '', email: '', phone: '', skill_set: '', rate_per_hour: '', payment_method: '', notes: '' });
    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-3">
                <div className="flex items-center justify-between">
                    <h2 className="text-[15px] font-bold text-gray-900">Add Freelancer</h2>
                    <button onClick={onClose}><X size={16} className="text-gray-400" /></button>
                </div>
                <form onSubmit={e => { e.preventDefault(); form.post('/freelancers', { onSuccess: onClose }); }} className="space-y-3">
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
                            <label className="text-xs text-gray-500 font-medium">Skills (comma-separated)</label>
                            <input type="text" value={form.data.skill_set} onChange={e => form.setData('skill_set', e.target.value)}
                                placeholder="e.g. React, Node.js, Design"
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Rate/hr (₹)</label>
                            <input type="number" value={form.data.rate_per_hour} onChange={e => form.setData('rate_per_hour', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Payment Method</label>
                            <input type="text" value={form.data.payment_method} onChange={e => form.setData('payment_method', e.target.value)}
                                placeholder="UPI, NEFT, etc."
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                        </div>
                    </div>
                    <div className="flex justify-end gap-2 pt-1">
                        <button type="button" onClick={onClose} className="px-4 py-2 text-sm text-gray-600">Cancel</button>
                        <button type="submit" disabled={form.processing || !form.data.name}
                            className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                            {form.processing ? 'Adding…' : 'Add Freelancer'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

function AssignModal({ freelancer, projects, onClose }: { freelancer: Freelancer; projects: Project[]; onClose: () => void }) {
    const form = useForm({ project_id: '', agreed_rate: '', start_date: '', end_date: '', notes: '' });
    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-3">
                <div className="flex items-center justify-between">
                    <h2 className="text-[15px] font-bold text-gray-900">Assign {freelancer.name}</h2>
                    <button onClick={onClose}><X size={16} className="text-gray-400" /></button>
                </div>
                <form onSubmit={e => { e.preventDefault(); form.post(`/freelancers/${freelancer.id}/assignments`, { onSuccess: onClose }); }} className="space-y-3">
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Project</label>
                        <select value={form.data.project_id} onChange={e => form.setData('project_id', e.target.value)}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                            <option value="">No specific project</option>
                            {projects.map(p => <option key={p.id} value={p.id}>{p.name}</option>)}
                        </select>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Agreed Rate (₹)</label>
                            <input type="number" value={form.data.agreed_rate} onChange={e => form.setData('agreed_rate', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div />
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Start Date</label>
                            <input type="date" value={form.data.start_date} onChange={e => form.setData('start_date', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 font-medium">End Date</label>
                            <input type="date" value={form.data.end_date} onChange={e => form.setData('end_date', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                        </div>
                    </div>
                    <div className="flex justify-end gap-2 pt-1">
                        <button type="button" onClick={onClose} className="px-4 py-2 text-sm text-gray-600">Cancel</button>
                        <button type="submit" disabled={form.processing}
                            className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                            {form.processing ? 'Assigning…' : 'Assign'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export default function FreelancersIndex({ freelancers, projects }: Props) {
    const [addOpen, setAddOpen] = useState(false);
    const [assignFor, setAssignFor] = useState<Freelancer | null>(null);
    const [expandedId, setExpandedId] = useState<string | null>(null);

    return (
        <AppLayout title="Freelancers">
            <Head title="Freelancers" />
            <div className="max-w-4xl space-y-5">
                <div className="flex items-center justify-between">
                    <h1 className="text-lg font-bold text-gray-900">Freelancer Management</h1>
                    <button onClick={() => setAddOpen(true)}
                        className="flex items-center gap-2 px-3 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                        <Plus size={14} /> Add Freelancer
                    </button>
                </div>

                <div className="space-y-3">
                    {freelancers.length === 0 && (
                        <div className="bg-white rounded-xl border border-dashed border-gray-200 px-5 py-10 text-center text-sm text-gray-400">
                            No freelancers yet.
                        </div>
                    )}
                    {freelancers.map(f => (
                        <div key={f.id} className="bg-white rounded-xl border border-gray-200">
                            <div className="px-5 py-4 flex items-center gap-4">
                                <div className="w-9 h-9 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-600 font-bold text-sm shrink-0">
                                    {f.name[0]}
                                </div>
                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center gap-2">
                                        <p className="text-sm font-semibold text-gray-900">{f.name}</p>
                                        <span className={cn('px-1.5 py-0.5 rounded text-[10px] font-semibold capitalize', STATUS_STYLES[f.status] ?? STATUS_STYLES.active)}>
                                            {f.status}
                                        </span>
                                    </div>
                                    <p className="text-xs text-gray-500 mt-0.5">
                                        {f.skill_set ?? 'No skills listed'}
                                        {f.rate_per_hour ? ` · ${fmt(f.rate_per_hour)}/hr` : ''}
                                    </p>
                                </div>
                                <div className="flex items-center gap-2 shrink-0">
                                    <span className="text-xs text-gray-500">{f.assignments_count} assignments</span>
                                    <button onClick={() => setAssignFor(f)}
                                        className="px-2.5 py-1.5 border border-gray-200 text-xs text-gray-600 rounded-lg hover:bg-gray-50">
                                        Assign
                                    </button>
                                    <button onClick={() => setExpandedId(expandedId === f.id ? null : f.id)}
                                        className="p-1.5 text-gray-400 hover:text-gray-600">
                                        <ChevronDown size={14} className={cn('transition-transform', expandedId === f.id && 'rotate-180')} />
                                    </button>
                                </div>
                            </div>
                            {expandedId === f.id && (
                                <div className="border-t border-gray-100 px-5 py-3">
                                    {f.assignments.length === 0 ? (
                                        <p className="text-xs text-gray-400">No assignments yet.</p>
                                    ) : (
                                        <div className="space-y-2">
                                            {f.assignments.map(a => (
                                                <div key={a.id} className="flex items-center justify-between text-xs">
                                                    <div>
                                                        <span className="font-medium text-gray-800">{a.project?.name ?? 'General'}</span>
                                                        <span className="text-gray-500 ml-2">{a.start_date} → {a.end_date ?? '—'}</span>
                                                    </div>
                                                    <div className="flex items-center gap-3 text-gray-500">
                                                        <span>{a.hours_worked}h worked</span>
                                                        <span>{fmt(a.total_paid)} paid</span>
                                                        <span className={cn('px-1.5 py-0.5 rounded capitalize', a.status === 'completed' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600')}>
                                                            {a.status}
                                                        </span>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            </div>
            {addOpen && <FreelancerModal onClose={() => setAddOpen(false)} />}
            {assignFor && <AssignModal freelancer={assignFor} projects={projects} onClose={() => setAssignFor(null)} />}
        </AppLayout>
    );
}

import React, { useState } from 'react';
import { Button } from '@/Components/ui/Button';
import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useConfirm } from '@/hooks/useConfirm';
import { cn } from '@/lib/utils';
import { Plus, X, Edit2, Trash2, ToggleLeft, ToggleRight } from 'lucide-react';
import { PageHelp } from '@/Components/Shared/PageHelp';

interface RateCard { id: string; service_name: string; category: string | null; description: string | null; unit: string; rate: number; currency: string; is_active: boolean; sort_order: number; }
interface Props { rateCards: RateCard[]; }

const UNITS = ['hour', 'post', 'campaign', 'month', 'project', 'word', 'video', 'other'];
const fmt = (n: number) => '₹' + new Intl.NumberFormat('en-IN', { maximumFractionDigits: 0 }).format(n);

function RateModal({ onClose }: { onClose: () => void }) {
    const form = useForm({ service_name: '', category: '', description: '', unit: 'hour', rate: '', is_active: true });
    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-3">
                <div className="flex items-center justify-between">
                    <h2 className="text-[15px] font-bold text-gray-900">Add Rate</h2>
                    <Button onClick={onClose}><X size={16} className="text-gray-400" /></Button>
                </div>
                <form onSubmit={e => { e.preventDefault(); form.post('/rate-cards', { onSuccess: onClose }); }} className="space-y-3">
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Service Name *</label>
                        <input type="text" value={form.data.service_name} onChange={e => form.setData('service_name', e.target.value)}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Category</label>
                            <input type="text" value={form.data.category} onChange={e => form.setData('category', e.target.value)}
                                placeholder="e.g. SEO, Social Media"
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Unit</label>
                            <select value={form.data.unit} onChange={e => form.setData('unit', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                {UNITS.map(u => <option key={u} value={u}>{u}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Rate (₹) *</label>
                            <input type="number" value={form.data.rate} onChange={e => form.setData('rate', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                        </div>
                    </div>
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Description</label>
                        <textarea value={form.data.description} onChange={e => form.setData('description', e.target.value)} rows={2}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 resize-none" />
                    </div>
                    <div className="flex justify-end gap-2 pt-1">
                        <Button type="button" onClick={onClose} variant="ghost" >Cancel</Button>
                        <Button type="submit" disabled={form.processing || !form.data.service_name || !form.data.rate}
                            className="disabled:opacity-50" >
                            {form.processing ? 'Adding…' : 'Add Rate'}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export default function RateCardsIndex({ rateCards }: Props) {
    const [modalOpen, setModalOpen] = useState(false);
    const [editingId, setEditingId] = useState<string | null>(null);
    const [editData, setEditData] = useState<Partial<RateCard>>({});
    const confirm = useConfirm();

    const categories = [...new Set(rateCards.map(r => r.category ?? 'Uncategorized'))];

    const saveEdit = (id: string) => {
        router.patch(`/rate-cards/${id}`, editData, { onSuccess: () => setEditingId(null) });
    };

    return (
        <AppLayout title="Rate Card">
            <Head title="Rate Card" />
            <div className="max-w-4xl space-y-5">
                <div className="flex items-center justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-lg font-bold text-gray-900">Rate Card</h1>
                            <PageHelp content="Rate cards map roles to hourly rates for project billing. By default, projects use standard rates unless a custom rate card is applied." />
                        </div>
                        <p className="text-xs text-gray-500 mt-0.5">{rateCards.length} services · {categories.length} categories</p>
                    </div>
                    <Button onClick={() => setModalOpen(true)}
                        className="flex items-center gap-2 px-3 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                        <Plus size={14} /> New Rate
                    </Button>
                </div>

                {categories.map(cat => {
                    const items = rateCards.filter(r => (r.category ?? 'Uncategorized') === cat);
                    return (
                        <div key={cat}>
                            <h2 className="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">{cat}</h2>
                            <div className="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
                                {items.map(r => (
                                    <div key={r.id} className={cn('px-4 py-3 flex items-center gap-4', !r.is_active && 'opacity-50')}>
                                        {editingId === r.id ? (
                                            <>
                                                <input type="text" defaultValue={r.service_name}
                                                    onChange={e => setEditData(d => ({ ...d, service_name: e.target.value }))}
                                                    className="flex-1 border border-gray-300 rounded px-2 py-1 text-sm focus:ring-1 focus:ring-indigo-500" />
                                                <input type="number" defaultValue={r.rate}
                                                    onChange={e => setEditData(d => ({ ...d, rate: parseFloat(e.target.value) }))}
                                                    className="w-28 border border-gray-300 rounded px-2 py-1 text-sm focus:ring-1 focus:ring-indigo-500" />
                                                <Button onClick={() => saveEdit(r.id)} className="px-3 py-1 bg-indigo-600 text-white text-xs rounded-lg">Save</Button>
                                                <Button onClick={() => setEditingId(null)} className="text-gray-400"><X size={13} /></Button>
                                            </>
                                        ) : (
                                            <>
                                                <div className="flex-1 min-w-0">
                                                    <p className="text-sm font-medium text-gray-900">{r.service_name}</p>
                                                    {r.description && <p className="text-xs text-gray-500">{r.description}</p>}
                                                </div>
                                                <p className="text-sm font-semibold text-gray-900">{fmt(r.rate)} / {r.unit}</p>
                                                <div className="flex items-center gap-1.5">
                                                    <Button onClick={() => router.patch(`/rate-cards/${r.id}`, { is_active: !r.is_active })}
                                                        className="text-gray-400 hover:text-indigo-600 transition-colors">
                                                        {r.is_active ? <ToggleRight size={18} className="text-indigo-600" /> : <ToggleLeft size={18} />}
                                                    </Button>
                                                    <Button onClick={() => { setEditingId(r.id); setEditData({}); }}
                                                        className="p-1 text-gray-400 hover:text-indigo-600 rounded hover:bg-indigo-50 transition-colors">
                                                        <Edit2 size={13} />
                                                    </Button>
                                                    <Button onClick={async () => {
                                                        const ok = await confirm({
                                                            title: 'Delete rate?',
                                                            description: 'This cannot be undone.',
                                                            confirmText: 'Delete',
                                                            variant: 'destructive',
                                                        });
                                                        if (!ok) return;
                                                        router.delete(`/rate-cards/${r.id}`);
                                                    }}
                                                        className="p-1 text-gray-400 hover:text-rose-500 rounded hover:bg-rose-50 transition-colors">
                                                        <Trash2 size={13} />
                                                    </Button>
                                                </div>
                                            </>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </div>
                    );
                })}

                {rateCards.length === 0 && (
                    <div className="bg-white rounded-xl border border-dashed border-gray-200 px-5 py-10 text-center text-sm text-gray-400">
                        No rates defined yet.
                    </div>
                )}
            </div>
            {modalOpen && <RateModal onClose={() => setModalOpen(false)} />}
        </AppLayout>
    );
}

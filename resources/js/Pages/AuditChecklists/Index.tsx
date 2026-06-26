import React, { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { cn } from '@/lib/utils';
import { Plus, X, ChevronDown, Bug } from 'lucide-react';
import { toast } from 'sonner';

interface CheckItem { id?: string; label: string; checked: boolean; notes?: string; }
interface Checklist {
    id: string; title: string; type: string; status: string; due_date: string | null;
    items: CheckItem[];
    client: { id: string; name: string } | null;
}
interface Client { id: string; name: string; }
interface User { id: string; name: string; }
interface Props { checklists: Checklist[]; clients: Client[]; users: User[]; filters: Record<string, string>; }

const TYPE_STYLES: Record<string, string> = {
    seo: 'bg-green-100 text-green-700', social: 'bg-pink-100 text-pink-700', ads: 'bg--100 text--800',
    content: 'bg-blue-100 text-blue-700', website: 'bg-violet-100 text-violet-700', general: 'bg-gray-100 text-gray-700',
};

function ChecklistModal({ clients, users, onClose }: { clients: Client[]; users: User[]; onClose: () => void }) {
    const form = useForm({ title: '', type: 'general', client_id: '', assigned_to: '', due_date: '' });
    const [items, setItems] = useState([{ label: '' }]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const itemsList = items.filter(i => i.label).map(i => ({ label: i.label, checked: false }));
        form.transform(d => ({ ...d, items: itemsList }));
        form.post('/audit-checklists', { onSuccess: onClose });
    };

    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-3 max-h-[90vh] overflow-y-auto">
                <div className="flex items-center justify-between">
                    <h2 className="text-[15px] font-bold text-gray-900">New Checklist</h2>
                    <button onClick={onClose}><X size={16} className="text-gray-400" /></button>
                </div>
                <form onSubmit={submit} className="space-y-3">
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Title *</label>
                        <input type="text" value={form.data.title} onChange={e => form.setData('title', e.target.value)}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Type</label>
                            <select value={form.data.type} onChange={e => form.setData('type', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                {['seo', 'social', 'ads', 'content', 'website', 'general'].map(t => <option key={t} value={t}>{t}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Due Date</label>
                            <input type="date" value={form.data.due_date} onChange={e => form.setData('due_date', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Client</label>
                            <select value={form.data.client_id} onChange={e => form.setData('client_id', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                <option value="">No client</option>
                                {clients.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Assigned To</label>
                            <select value={form.data.assigned_to} onChange={e => form.setData('assigned_to', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                <option value="">No one</option>
                                {users.map(u => <option key={u.id} value={u.id}>{u.name}</option>)}
                            </select>
                        </div>
                    </div>
                    <div>
                        <div className="flex items-center justify-between mb-1">
                            <label className="text-xs text-gray-500 font-medium">Checklist Items</label>
                            <button type="button" onClick={() => setItems(i => [...i, { label: '' }])}
                                className="text-xs text-indigo-600 font-medium flex items-center gap-1"><Plus size={11} /> Add</button>
                        </div>
                        {items.map((item, i) => (
                            <div key={i} className="flex gap-2 mb-1.5">
                                <input type="text" placeholder={`Item ${i + 1}`} value={item.label}
                                    onChange={e => { const n = [...items]; n[i].label = e.target.value; setItems(n); }}
                                    className="flex-1 border border-gray-300 rounded-lg px-2 py-1.5 text-xs focus:ring-1 focus:ring-indigo-500" />
                                <button type="button" onClick={() => setItems(it => it.filter((_, j) => j !== i))} className="text-gray-400 hover:text-rose-500">
                                    <X size={13} />
                                </button>
                            </div>
                        ))}
                    </div>
                    <div className="flex justify-end gap-2 pt-1">
                        <button type="button" onClick={onClose} className="px-4 py-2 text-sm text-gray-600">Cancel</button>
                        <button type="submit" disabled={form.processing || !form.data.title}
                            className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                            {form.processing ? 'Creating…' : 'Create'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export default function AuditChecklistsIndex({ checklists, clients, users, filters }: Props) {
    const [modalOpen, setModalOpen] = useState(false);
    const [expandedId, setExpandedId] = useState<string | null>(null);
    const [typeFilter, setTypeFilter] = useState(filters.type ?? '');

    const filtered = typeFilter ? checklists.filter(c => c.type === typeFilter) : checklists;

    const toggleItem = (checklist: Checklist, itemIdx: number) => {
        const newItems = checklist.items.map((it, i) => i === itemIdx ? { ...it, checked: !it.checked } : it);
        router.patch(`/audit-checklists/${checklist.id}`, { items: newItems as any }, { preserveScroll: true });
    };

    return (
        <AppLayout title="Audit Checklists">
            <Head title="Audit Checklists" />
            <div className="max-w-4xl space-y-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-lg font-bold text-gray-900">Audit Checklists</h1>
                    <button onClick={() => setModalOpen(true)}
                        className="flex items-center gap-2 px-3 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                        <Plus size={14} /> New Checklist
                    </button>
                </div>

                <div className="flex items-center gap-2 flex-wrap">
                    {['', 'seo', 'social', 'ads', 'content', 'website', 'general'].map(t => (
                        <button key={t} onClick={() => setTypeFilter(t)}
                            className={cn('px-3 py-1.5 text-xs rounded-lg font-medium capitalize transition-colors',
                                typeFilter === t ? 'bg-indigo-600 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50')}>
                            {t === '' ? 'All' : t}
                        </button>
                    ))}
                </div>

                <div className="space-y-3">
                    {filtered.length === 0 && (
                        <div className="bg-white rounded-xl border border-dashed border-gray-200 px-5 py-10 text-center text-sm text-gray-400">
                            No checklists found.
                        </div>
                    )}
                    {filtered.map(cl => {
                        const done = cl.items.filter(i => i.checked).length;
                        const total = cl.items.length;
                        const pct = total > 0 ? Math.round((done / total) * 100) : 0;
                        return (
                            <div key={cl.id} className="bg-white rounded-xl border border-gray-200">
                                <div className="px-4 py-3.5 flex items-center gap-3 cursor-pointer" onClick={() => setExpandedId(expandedId === cl.id ? null : cl.id)}>
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center gap-2 mb-1">
                                            <p className="text-sm font-semibold text-gray-900">{cl.title}</p>
                                            <span className={cn('px-1.5 py-0.5 rounded text-[10px] font-semibold capitalize', TYPE_STYLES[cl.type] ?? TYPE_STYLES.general)}>
                                                {cl.type}
                                            </span>
                                        </div>
                                        <p className="text-xs text-gray-500">
                                            {cl.client?.name ?? 'Internal'}
                                            {cl.due_date ? ` · Due ${new Date(cl.due_date).toLocaleDateString('en-IN')}` : ''}
                                        </p>
                                        <div className="mt-2 flex items-center gap-2">
                                            <div className="flex-1 bg-gray-100 rounded-full h-1.5">
                                                <div className="bg-indigo-500 h-1.5 rounded-full" style={{ width: `${pct}%` }} />
                                            </div>
                                            <span className="text-xs text-gray-500">{done}/{total}</span>
                                        </div>
                                    </div>
                                    <ChevronDown size={13} className={cn('text-gray-400 transition-transform shrink-0', expandedId === cl.id && 'rotate-180')} />
                                </div>
                                {expandedId === cl.id && (
                                    <div className="border-t border-gray-100 px-4 pb-4 pt-3 space-y-2">
                                        {cl.items.map((item, idx) => (
                                            <div key={idx} className="flex items-center justify-between group">
                                                <label className="flex items-center gap-2.5 cursor-pointer flex-1">
                                                    <input type="checkbox" checked={item.checked}
                                                        onChange={() => toggleItem(cl, idx)}
                                                        className="rounded text-indigo-600 focus:ring-indigo-500" />
                                                    <span className={cn('text-sm', item.checked ? 'line-through text-gray-400' : 'text-gray-700')}>
                                                        {item.label}
                                                    </span>
                                                </label>
                                                {!item.checked && (
                                                    <button 
                                                        onClick={(e) => {
                                                            e.preventDefault();
                                                            router.post('/issues', {
                                                                title: `Audit Failure: ${item.label}`,
                                                                description: `Failed on checklist: ${cl.title}`,
                                                                type: 'bug',
                                                                priority: 'medium',
                                                                client_id: cl.client?.id || '',
                                                                audit_checklist_id: cl.id,
                                                                source: 'internal'
                                                            }, { preserveScroll: true, onSuccess: () => toast.success('Issue logged') });
                                                        }}
                                                        className="opacity-0 group-hover:opacity-100 px-2 py-1 bg-red-50 text-red-600 text-[10px] font-bold rounded hover:bg-red-100 transition-opacity flex items-center gap-1"
                                                    >
                                                        <Bug size={10} /> Log Issue
                                                    </button>
                                                )}
                                            </div>
                                        ))}
                                        {cl.items.length === 0 && <p className="text-xs text-gray-400">No items in checklist.</p>}
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
            </div>
            {modalOpen && <ChecklistModal clients={clients} users={users} onClose={() => setModalOpen(false)} />}
        </AppLayout>
    );
}

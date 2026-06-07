import React, { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useConfirm } from '@/hooks/useConfirm';
import { cn } from '@/lib/utils';
import { Plus, X, Trash2, CheckCircle, FileX } from 'lucide-react';

interface CreditNote {
    id: string; credit_note_number: string; issue_date: string;
    amount: number; reason: string; status: string; applied_at: string | null;
    client: { id: string; name: string } | null;
    invoice: { id: string; number: string } | null;
}
interface Client { id: string; name: string; company: string | null; }
interface Invoice { id: string; invoice_number: string; client_id: string; }
interface Props { creditNotes: CreditNote[]; clients: Client[]; invoices: Invoice[]; }

const STATUS_STYLES: Record<string, string> = {
    draft:   'bg-gray-100 text-gray-600',
    issued:  'bg-blue-100 text-blue-700',
    applied: 'bg-emerald-100 text-emerald-700',
};

const fmt = (n: number) => '₹' + new Intl.NumberFormat('en-IN', { maximumFractionDigits: 0 }).format(n);

function CreditNoteModal({ clients, invoices, onClose }: { clients: Client[]; invoices: Invoice[]; onClose: () => void }) {
    const form = useForm({ client_id: '', invoice_id: '', issue_date: new Date().toISOString().slice(0, 10), amount: '', reason: '' });
    const filteredInvoices = form.data.client_id ? invoices.filter(i => i.client_id === form.data.client_id) : invoices;

    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-3">
                <div className="flex items-center justify-between">
                    <h2 className="text-[15px] font-bold text-gray-900">New Credit Note</h2>
                    <button onClick={onClose}><X size={16} className="text-gray-400" /></button>
                </div>
                <form onSubmit={e => { e.preventDefault(); form.post('/credit-notes', { onSuccess: onClose }); }} className="space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Client *</label>
                            <select value={form.data.client_id} onChange={e => form.setData('client_id', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                <option value="">Select client…</option>
                                {clients.map(c => <option key={c.id} value={c.id}>{c.company ?? c.name}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Invoice (optional)</label>
                            <select value={form.data.invoice_id} onChange={e => form.setData('invoice_id', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                <option value="">No invoice</option>
                                {filteredInvoices.map(i => <option key={i.id} value={i.id}>{i.invoice_number}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Issue Date *</label>
                            <input type="date" value={form.data.issue_date} onChange={e => form.setData('issue_date', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Amount (₹) *</label>
                            <input type="number" value={form.data.amount} onChange={e => form.setData('amount', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                        </div>
                    </div>
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Reason *</label>
                        <textarea value={form.data.reason} onChange={e => form.setData('reason', e.target.value)} rows={3}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 resize-none" />
                    </div>
                    <div className="flex justify-end gap-2 pt-1">
                        <button type="button" onClick={onClose} className="px-4 py-2 text-sm text-gray-600">Cancel</button>
                        <button type="submit" disabled={form.processing || !form.data.client_id || !form.data.amount || !form.data.reason}
                            className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                            {form.processing ? 'Creating…' : 'Create Credit Note'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export default function CreditNotesIndex({ creditNotes, clients, invoices }: Props) {
    const [modalOpen, setModalOpen] = useState(false);
    const confirm = useConfirm();

    const totalDraft   = creditNotes.filter(cn => cn.status === 'draft').reduce((s, cn) => s + cn.amount, 0);
    const totalIssued  = creditNotes.filter(cn => cn.status === 'issued').reduce((s, cn) => s + cn.amount, 0);
    const totalApplied = creditNotes.filter(cn => cn.status === 'applied').reduce((s, cn) => s + cn.amount, 0);

    return (
        <AppLayout title="Credit Notes">
            <Head title="Credit Notes" />
            <div className="max-w-4xl space-y-5">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-lg font-bold text-gray-900">Credit Notes</h1>
                        <p className="text-xs text-gray-500 mt-0.5">{creditNotes.length} total</p>
                    </div>
                    <button onClick={() => setModalOpen(true)}
                        className="flex items-center gap-2 px-3 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                        <Plus size={14} /> New Credit Note
                    </button>
                </div>

                <div className="grid grid-cols-3 gap-4">
                    {[
                        { label: 'Draft', value: totalDraft, cls: 'text-gray-700' },
                        { label: 'Issued', value: totalIssued, cls: 'text-blue-700' },
                        { label: 'Applied', value: totalApplied, cls: 'text-emerald-700' },
                    ].map(s => (
                        <div key={s.label} className="bg-white rounded-xl border border-gray-200 p-4 text-center">
                            <p className={cn('text-xl font-bold', s.cls)}>{fmt(s.value)}</p>
                            <p className="text-xs text-gray-400 mt-0.5">{s.label}</p>
                        </div>
                    ))}
                </div>

                <div className="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
                    {creditNotes.length === 0 && (
                        <div className="px-5 py-12 text-center">
                            <FileX size={24} className="text-gray-300 mx-auto mb-2" />
                            <p className="text-sm text-gray-400">No credit notes yet.</p>
                        </div>
                    )}
                    {creditNotes.map(creditNote => (
                        <div key={creditNote.id} className="px-5 py-4 flex items-start gap-4">
                            <div className="flex-1 min-w-0">
                                <div className="flex items-center gap-2 mb-1">
                                    <p className="text-sm font-semibold text-gray-900">{creditNote.credit_note_number}</p>
                                    <span className={cn('px-2 py-0.5 rounded text-[10px] font-semibold capitalize', STATUS_STYLES[creditNote.status] ?? STATUS_STYLES.draft)}>
                                        {creditNote.status}
                                    </span>
                                </div>
                                <p className="text-xs text-gray-500">
                                    {creditNote.client?.name ?? '—'}{creditNote.invoice ? ` · ${creditNote.invoice.number}` : ''}
                                    {' · '}{creditNote.issue_date}
                                </p>
                                <p className="text-xs text-gray-500 mt-0.5 line-clamp-2">{creditNote.reason}</p>
                                {creditNote.applied_at && <p className="text-xs text-emerald-600 mt-0.5">Applied {creditNote.applied_at}</p>}
                            </div>
                            <p className="text-sm font-semibold text-gray-900 shrink-0">{fmt(creditNote.amount)}</p>
                            <div className="flex items-center gap-1.5 shrink-0">
                                {creditNote.status !== 'applied' && (
                                    <button onClick={async () => {
                                        const ok = await confirm({ title: 'Mark this credit note as applied?', confirmText: 'Mark applied' });
                                        if (!ok) return;
                                        router.post(`/credit-notes/${creditNote.id}/apply`);
                                    }}
                                        className="p-1 text-gray-400 hover:text-emerald-600 rounded hover:bg-emerald-50 transition-colors" title="Apply">
                                        <CheckCircle size={14} />
                                    </button>
                                )}
                                <button onClick={async () => {
                                    const ok = await confirm({
                                        title: 'Delete credit note?',
                                        description: 'This cannot be undone.',
                                        confirmText: 'Delete',
                                        variant: 'destructive',
                                    });
                                    if (!ok) return;
                                    router.delete(`/credit-notes/${creditNote.id}`);
                                }}
                                    className="p-1 text-gray-400 hover:text-rose-500 rounded hover:bg-rose-50 transition-colors">
                                    <Trash2 size={14} />
                                </button>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
            {modalOpen && <CreditNoteModal clients={clients} invoices={invoices} onClose={() => setModalOpen(false)} />}
        </AppLayout>
    );
}

import React, { useState } from 'react';
import { Button } from '@/Components/ui/Button';
import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { cn } from '@/lib/utils';
import { Plus, X, ChevronDown, Trash2 } from 'lucide-react';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/Components/ui/Table";

interface LineItem { description: string; quantity: number; unit_price: number; total: number; }
interface PO { id: string; po_number: string; issue_date: string; expected_delivery: string | null; total_amount: number; status: string; notes: string | null; line_items: LineItem[]; vendor_id: string | null; }
interface Vendor { id: string; vendor_name: string; }
interface Props { purchaseOrders: PO[]; vendors: Vendor[]; }

const STATUS_STYLES: Record<string, string> = {
    draft: 'bg-gray-100 text-gray-700',
    sent: 'bg-blue-100 text-blue-700',
    acknowledged: 'bg-indigo-100 text-indigo-700',
    received: 'bg-emerald-100 text-emerald-700',
    cancelled: 'bg-rose-100 text-rose-700',
};

const fmt = (n: number) => '₹' + new Intl.NumberFormat('en-IN', { maximumFractionDigits: 0 }).format(n);

function POModal({ vendors, onClose }: { vendors: Vendor[]; onClose: () => void }) {
    const form = useForm({ vendor_id: '', issue_date: new Date().toISOString().slice(0, 10), expected_delivery: '', notes: '' });
    const [items, setItems] = useState([{ description: '', quantity: '1', unit_price: '' }]);

    const total = items.reduce((s, i) => s + (parseFloat(i.quantity || '0') * parseFloat(i.unit_price || '0')), 0);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const lineItems = items.filter(i => i.description).map(i => ({ description: i.description, quantity: parseFloat(i.quantity || '0'), unit_price: parseFloat(i.unit_price || '0') }));
        form.transform(d => ({ ...d, line_items: lineItems }));
        form.post('/purchase-orders', { onSuccess: onClose });
    };

    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 space-y-4 max-h-[90vh] overflow-y-auto">
                <div className="flex items-center justify-between">
                    <h2 className="text-[15px] font-bold text-gray-900">New Purchase Order</h2>
                    <Button onClick={onClose}><X size={16} className="text-gray-400" /></Button>
                </div>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Vendor</label>
                            <select value={form.data.vendor_id} onChange={e => form.setData('vendor_id', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                <option value="">No vendor</option>
                                {vendors.map(v => <option key={v.id} value={v.id}>{v.vendor_name}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Issue Date *</label>
                            <input type="date" value={form.data.issue_date} onChange={e => form.setData('issue_date', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Expected Delivery</label>
                            <input type="date" value={form.data.expected_delivery} onChange={e => form.setData('expected_delivery', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                        </div>
                    </div>

                    <div>
                        <div className="flex items-center justify-between mb-1">
                            <label className="text-xs text-gray-500 font-medium">Line Items *</label>
                            <Button type="button" onClick={() => setItems(i => [...i, { description: '', quantity: '1', unit_price: '' }])}
                                className="text-xs text-indigo-600 font-medium flex items-center gap-1"><Plus size={12} /> Add</Button>
                        </div>
                        {items.map((item, i) => (
                            <div key={i} className="grid grid-cols-12 gap-2 mb-1.5 items-center">
                                <input type="text" placeholder="Description" value={item.description}
                                    onChange={e => { const n = [...items]; n[i].description = e.target.value; setItems(n); }}
                                    className="col-span-6 border border-gray-300 rounded-lg px-2 py-1.5 text-xs focus:ring-1 focus:ring-indigo-500" />
                                <input type="number" placeholder="Qty" value={item.quantity}
                                    onChange={e => { const n = [...items]; n[i].quantity = e.target.value; setItems(n); }}
                                    className="col-span-2 border border-gray-300 rounded-lg px-2 py-1.5 text-xs focus:ring-1 focus:ring-indigo-500" />
                                <input type="number" placeholder="Price (₹)" value={item.unit_price}
                                    onChange={e => { const n = [...items]; n[i].unit_price = e.target.value; setItems(n); }}
                                    className="col-span-3 border border-gray-300 rounded-lg px-2 py-1.5 text-xs focus:ring-1 focus:ring-indigo-500" />
                                <Button type="button" onClick={() => setItems(it => it.filter((_, j) => j !== i))} className="col-span-1 text-gray-400 hover:text-rose-500 flex justify-center">
                                    <X size={16} />
                                </Button>
                            </div>
                        ))}
                        <div className="text-right mt-1">
                            <span className="text-sm font-semibold text-gray-900">Total: {fmt(total)}</span>
                        </div>
                    </div>

                    <div>
                        <label className="text-xs text-gray-500 font-medium">Notes</label>
                        <textarea value={form.data.notes} onChange={e => form.setData('notes', e.target.value)} rows={2}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 resize-none" />
                    </div>

                    <div className="flex justify-end gap-2 pt-1">
                        <Button type="button" onClick={onClose} variant="ghost" >Cancel</Button>
                        <Button type="submit" disabled={form.processing}
                            className="disabled:opacity-50" >
                            {form.processing ? 'Creating…' : 'Create PO'}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export default function PurchaseOrdersIndex({ purchaseOrders, vendors }: Props) {
    const [modalOpen, setModalOpen] = useState(false);
    const [expandedId, setExpandedId] = useState<string | null>(null);

    return (
        <AppLayout title="Purchase Orders">
            <Head title="Purchase Orders" />
            <div className="max-w-4xl space-y-5">
                <div className="flex items-center justify-between">
                    <h1 className="text-lg font-bold text-gray-900">Purchase Orders</h1>
                    <Button onClick={() => setModalOpen(true)}
                        className="flex items-center gap-2 px-3 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                        <Plus size={16} /> New PO
                    </Button>
                </div>

                <div className="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
                    {purchaseOrders.length === 0 && (
                        <div className="px-5 py-10 text-center text-sm text-gray-400">No purchase orders yet.</div>
                    )}
                    {purchaseOrders.map(po => (
                        <div key={po.id}>
                            <div className="px-5 py-4 flex items-center gap-4 cursor-pointer" onClick={() => setExpandedId(expandedId === po.id ? null : po.id)}>
                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center gap-2">
                                        <p className="text-sm font-semibold text-gray-900">{po.po_number}</p>
                                        <span className={cn('px-2 py-0.5 rounded text-[10px] font-semibold capitalize', STATUS_STYLES[po.status] ?? STATUS_STYLES.draft)}>
                                            {po.status}
                                        </span>
                                    </div>
                                    <p className="text-xs text-gray-500 mt-0.5">
                                        {po.issue_date}
                                        {po.expected_delivery ? ` · Due ${po.expected_delivery}` : ''}
                                    </p>
                                </div>
                                <p className="text-sm font-semibold text-gray-900 shrink-0">{fmt(po.total_amount)}</p>
                                <select value={po.status}
                                    onClick={e => e.stopPropagation()}
                                    onChange={e => router.post(`/purchase-orders/${po.id}/status`, { status: e.target.value })}
                                    className="text-xs border border-gray-200 rounded px-1.5 py-1 text-gray-500 bg-white focus:ring-1 focus:ring-indigo-500">
                                    {['draft', 'sent', 'acknowledged', 'received', 'cancelled'].map(s => (
                                        <option key={s} value={s}>{s}</option>
                                    ))}
                                </select>
                                <ChevronDown size={16} className={cn('text-gray-400 transition-transform', expandedId === po.id && 'rotate-180')} />
                            </div>
                            {expandedId === po.id && (
                                <div className="border-t border-gray-100 px-5 py-3">
                                    <Table className="w-full text-xs">
                                        <TableHeader>
                                            <TableRow className="text-gray-500">
                                                <TableHead className="text-left pb-1">Description</TableHead>
                                                <TableHead className="text-right pb-1">Qty</TableHead>
                                                <TableHead className="text-right pb-1">Unit Price</TableHead>
                                                <TableHead className="text-right pb-1">Total</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody className="divide-y divide-gray-50">
                                            {(po.line_items ?? []).map((li, i) => (
                                                <TableRow key={i} className="text-gray-700">
                                                    <TableCell className="py-1">{li.description}</TableCell>
                                                    <TableCell className="py-1 text-right">{li.quantity}</TableCell>
                                                    <TableCell className="py-1 text-right">{fmt(li.unit_price)}</TableCell>
                                                    <TableCell className="py-1 text-right font-medium">{fmt(li.total)}</TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                    {po.notes && <p className="mt-2 text-xs text-gray-500">{po.notes}</p>}
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            </div>
            {modalOpen && <POModal vendors={vendors} onClose={() => setModalOpen(false)} />}
        </AppLayout>
    );
}

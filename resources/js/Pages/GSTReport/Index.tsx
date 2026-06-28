import React from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { ChevronLeft, ChevronRight, Download } from 'lucide-react';
import { toast } from 'sonner';
import { cn } from '@/lib/utils';

interface GSTInvoice {
    id: string; invoice_number: string; client_name: string; client_gstin: string | null;
    amount: number; gst_rate: number; gst_amount: number; supply_type: string; month_year: string;
}
interface Props { invoices: GSTInvoice[]; month: string; }

const fmt = (n: number) => '₹' + new Intl.NumberFormat('en-IN', { maximumFractionDigits: 0 }).format(n);

function prevMonth(m: string) {
    const [y, mo] = m.split('-').map(Number);
    const d = new Date(y, mo - 2, 1);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}
function nextMonth(m: string) {
    const [y, mo] = m.split('-').map(Number);
    const d = new Date(y, mo, 1);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}
function monthLabel(m: string) {
    const [y, mo] = m.split('-').map(Number);
    return new Date(y, mo - 1, 1).toLocaleString('en-IN', { month: 'long', year: 'numeric' });
}

export default function GSTReportIndex({ invoices, month }: Props) {
    function navigate(m: string) {
        router.get('/gst-report', { month: m }, { preserveState: false });
    }

    const totalTaxable = invoices.reduce((s, i) => s + i.amount, 0);
    const totalGST     = invoices.reduce((s, i) => s + i.gst_amount, 0);
    const totalCGST    = invoices.filter(i => i.supply_type === 'intra').reduce((s, i) => s + i.gst_amount / 2, 0);
    const totalSGST    = totalCGST;
    const totalIGST    = invoices.filter(i => i.supply_type === 'inter').reduce((s, i) => s + i.gst_amount, 0);

    function downloadCSV() {
        const headers = ['Invoice #', 'Client', 'GSTIN', 'Taxable Value', 'GST Rate', 'CGST', 'SGST', 'IGST', 'Total'];
        const rows = invoices.map(inv => {
            const cgst = inv.supply_type === 'intra' ? inv.gst_amount / 2 : 0;
            const sgst = cgst;
            const igst = inv.supply_type === 'inter' ? inv.gst_amount : 0;
            return [inv.invoice_number, inv.client_name, inv.client_gstin ?? '', inv.amount, inv.gst_rate, cgst, sgst, igst, inv.amount + inv.gst_amount];
        });
        const csv = [headers, ...rows].map(r => r.join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = `GST-Report-${month}.csv`;
        a.click();
        toast.success('Download ready');
    }

    return (
        <AppLayout title="GST Report">
            <Head title="GST Report" />
            <div className="max-w-6xl mx-auto px-4 py-6 space-y-6">
                <div className="flex items-center justify-between flex-wrap gap-3">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">GST Report</h1>
                        <p className="text-sm text-gray-500 mt-0.5">Monthly GST summary for filing</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <div className="flex items-center gap-1 bg-white border border-gray-200 rounded-xl p-1">
                            <button onClick={() => navigate(prevMonth(month))} className="p-2 rounded-lg hover:bg-gray-100 text-gray-700">
                                <ChevronLeft size={15} />
                            </button>
                            <span className="px-3 text-sm font-semibold text-gray-800 min-w-[150px] text-center">{monthLabel(month)}</span>
                            <button onClick={() => navigate(nextMonth(month))} className="p-2 rounded-lg hover:bg-gray-100 text-gray-700">
                                <ChevronRight size={15} />
                            </button>
                        </div>
                        <button onClick={downloadCSV} className="flex items-center gap-1.5 px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700">
                            <Download size={14} /> Download CSV
                        </button>
                    </div>
                </div>

                {invoices.length === 0 ? (
                    <div className="bg-white rounded-xl border border-dashed border-gray-300 p-12 text-center">
                        <p className="text-gray-400 text-sm">No GST invoices for {monthLabel(month)}.</p>
                    </div>
                ) : (
                    <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
                                    <tr>
                                        {['Client', 'GSTIN', 'Taxable Value', 'GST Rate', 'CGST', 'SGST', 'IGST', 'Total'].map(h => (
                                            <th key={h} className="px-4 py-3 text-left font-medium">{h}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {invoices.map(inv => {
                                        const cgst = inv.supply_type === 'intra' ? inv.gst_amount / 2 : 0;
                                        const sgst = cgst;
                                        const igst = inv.supply_type === 'inter' ? inv.gst_amount : 0;
                                        return (
                                            <tr key={inv.id} className="hover:bg-gray-50">
                                                <td className="px-4 py-3 font-medium text-gray-900">{inv.client_name}</td>
                                                <td className="px-4 py-3 text-gray-500 font-mono text-xs">{inv.client_gstin ?? '—'}</td>
                                                <td className="px-4 py-3">{fmt(inv.amount)}</td>
                                                <td className="px-4 py-3">{inv.gst_rate}%</td>
                                                <td className="px-4 py-3">{cgst > 0 ? fmt(cgst) : '—'}</td>
                                                <td className="px-4 py-3">{sgst > 0 ? fmt(sgst) : '—'}</td>
                                                <td className="px-4 py-3">{igst > 0 ? fmt(igst) : '—'}</td>
                                                <td className="px-4 py-3 font-semibold">{fmt(inv.amount + inv.gst_amount)}</td>
                                            </tr>
                                        );
                                    })}
                                    <tr className="bg-gray-50 font-semibold border-t-2 border-gray-200">
                                        <td className="px-4 py-3 text-gray-900" colSpan={2}>Total</td>
                                        <td className="px-4 py-3">{fmt(totalTaxable)}</td>
                                        <td className="px-4 py-3">—</td>
                                        <td className="px-4 py-3">{fmt(totalCGST)}</td>
                                        <td className="px-4 py-3">{fmt(totalSGST)}</td>
                                        <td className="px-4 py-3">{fmt(totalIGST)}</td>
                                        <td className="px-4 py-3">{fmt(totalTaxable + totalGST)}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

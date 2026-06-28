import React from 'react';
import { Button } from '@/Components/ui/Button';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { ChevronLeft, ChevronRight, Download } from 'lucide-react';
import { toast } from 'sonner';
import { cn } from '@/lib/utils';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/Components/ui/Table";

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
                            <Button onClick={() => navigate(prevMonth(month))} className="p-2 rounded-lg hover:bg-gray-100 text-gray-700">
                                <ChevronLeft size={16} />
                            </Button>
                            <span className="px-3 text-sm font-semibold text-gray-800 min-w-[150px] text-center">{monthLabel(month)}</span>
                            <Button onClick={() => navigate(nextMonth(month))} className="p-2 rounded-lg hover:bg-gray-100 text-gray-700">
                                <ChevronRight size={16} />
                            </Button>
                        </div>
                        <Button onClick={downloadCSV} className="flex items-center gap-1.5" >
                            <Download size={16} /> Download CSV
                        </Button>
                    </div>
                </div>

                {invoices.length === 0 ? (
                    <div className="bg-white rounded-xl border border-dashed border-gray-300 p-12 text-center">
                        <p className="text-gray-400 text-sm">No GST invoices for {monthLabel(month)}.</p>
                    </div>
                ) : (
                    <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                        <div className="overflow-x-auto">
                            <Table className="w-full text-sm">
                                <TableHeader className="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
                                    <TableRow>
                                        {['Client', 'GSTIN', 'Taxable Value', 'GST Rate', 'CGST', 'SGST', 'IGST', 'Total'].map(h => (
                                            <TableHead key={h} className="px-4 py-3 text-left font-medium">{h}</TableHead>
                                        ))}
                                    </TableRow>
                                </TableHeader>
                                <TableBody className="divide-y divide-gray-100">
                                    {invoices.map(inv => {
                                        const cgst = inv.supply_type === 'intra' ? inv.gst_amount / 2 : 0;
                                        const sgst = cgst;
                                        const igst = inv.supply_type === 'inter' ? inv.gst_amount : 0;
                                        return (
                                            <TableRow key={inv.id} className="hover:bg-gray-50">
                                                <TableCell className="px-4 py-3 font-medium text-gray-900">{inv.client_name}</TableCell>
                                                <TableCell className="px-4 py-3 text-gray-500 font-mono text-xs">{inv.client_gstin ?? '—'}</TableCell>
                                                <TableCell className="px-4 py-3">{fmt(inv.amount)}</TableCell>
                                                <TableCell className="px-4 py-3">{inv.gst_rate}%</TableCell>
                                                <TableCell className="px-4 py-3">{cgst > 0 ? fmt(cgst) : '—'}</TableCell>
                                                <TableCell className="px-4 py-3">{sgst > 0 ? fmt(sgst) : '—'}</TableCell>
                                                <TableCell className="px-4 py-3">{igst > 0 ? fmt(igst) : '—'}</TableCell>
                                                <TableCell className="px-4 py-3 font-semibold">{fmt(inv.amount + inv.gst_amount)}</TableCell>
                                            </TableRow>
                                        );
                                    })}
                                    <TableRow className="bg-gray-50 font-semibold border-t-2 border-gray-200">
                                        <TableCell className="px-4 py-3 text-gray-900" colSpan={2}>Total</TableCell>
                                        <TableCell className="px-4 py-3">{fmt(totalTaxable)}</TableCell>
                                        <TableCell className="px-4 py-3">—</TableCell>
                                        <TableCell className="px-4 py-3">{fmt(totalCGST)}</TableCell>
                                        <TableCell className="px-4 py-3">{fmt(totalSGST)}</TableCell>
                                        <TableCell className="px-4 py-3">{fmt(totalIGST)}</TableCell>
                                        <TableCell className="px-4 py-3">{fmt(totalTaxable + totalGST)}</TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

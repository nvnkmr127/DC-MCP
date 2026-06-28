import React from 'react';
import { Button } from '@/Components/ui/Button';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { cn } from '@/lib/utils';
import { ArrowLeft, Send, CheckCircle, XCircle, FileText } from 'lucide-react';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/Components/ui/Table";

interface Proposal {
    id: string; title: string; status: string; valid_until: string | null;
    total_value: number; subtotal: number; discount: number; tax_amount: number;
    notes: string | null; sent_at: string | null; accepted_at: string | null;
    client: { id: string; name: string } | null;
    line_items: Array<{ id: string; service: string; description: string | null; unit_price: number; quantity: number; frequency: string; }>;
}
interface Props { proposal: Proposal; }

const STATUS_STYLES: Record<string, string> = {
    draft: 'bg-gray-100 text-gray-700',
    sent: 'bg-blue-100 text-blue-700',
    accepted: 'bg-emerald-100 text-emerald-700',
    rejected: 'bg-rose-100 text-rose-700',
    expired: 'bg--100 text--800',
};
const FREQ_LABELS: Record<string, string> = { one_time: 'One Time', monthly: 'Monthly', quarterly: 'Quarterly', annual: 'Annual' };

const fmt = (n: number) => '₹' + new Intl.NumberFormat('en-IN', { maximumFractionDigits: 0 }).format(n);

export default function ProposalShow({ proposal }: Props) {
    return (
        <AppLayout title={proposal.title}>
            <Head title={proposal.title} />
            <div className="max-w-3xl space-y-5">
                <Link href="/proposals" className="flex items-center gap-1.5 text-sm text-gray-500 hover:text-indigo-600 w-fit">
                    <ArrowLeft size={16} /> Back to Proposals
                </Link>

                <div className="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <h1 className="text-xl font-bold text-gray-900">{proposal.title}</h1>
                            <p className="text-sm text-gray-500 mt-0.5">
                                {proposal.client?.name ?? '—'}
                                {proposal.valid_until ? ` · Valid until ${new Date(proposal.valid_until).toLocaleDateString('en-IN')}` : ''}
                            </p>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className={cn('px-2.5 py-1 rounded-full text-xs font-semibold capitalize', STATUS_STYLES[proposal.status] ?? STATUS_STYLES.draft)}>
                                {proposal.status}
                            </span>
                            {proposal.status === 'draft' && (
                                <Button onClick={() => router.post(`/proposals/${proposal.id}/send`)}
                                    className="flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">
                                    <Send size={16} /> Send
                                </Button>
                            )}
                            {proposal.status === 'sent' && (
                                <>
                                    <Button onClick={() => router.post(`/proposals/${proposal.id}/accept`)}
                                        className="flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700">
                                        <CheckCircle size={16} /> Accept
                                    </Button>
                                    <Button onClick={() => router.post(`/proposals/${proposal.id}/reject`)}
                                        className="flex items-center gap-1.5 px-3 py-1.5 bg-rose-600 text-white text-sm font-medium rounded-lg hover:bg-rose-700">
                                        <XCircle size={16} /> Reject
                                    </Button>
                                </>
                            )}
                            {proposal.status === 'accepted' && (
                                <Button onClick={() => router.post(`/proposals/${proposal.id}/convert-to-sow`)}
                                    className="flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                                    <FileText size={16} /> Generate SOW
                                </Button>
                            )}
                        </div>
                    </div>

                    <Table className="w-full">
                        <TableHeader>
                            <TableRow className="border-b border-gray-100">
                                <TableHead className="text-left text-xs font-semibold text-gray-500 pb-2">Service</TableHead>
                                <TableHead className="text-right text-xs font-semibold text-gray-500 pb-2">Qty</TableHead>
                                <TableHead className="text-right text-xs font-semibold text-gray-500 pb-2">Unit Price</TableHead>
                                <TableHead className="text-right text-xs font-semibold text-gray-500 pb-2">Frequency</TableHead>
                                <TableHead className="text-right text-xs font-semibold text-gray-500 pb-2">Total</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody className="divide-y divide-gray-50">
                            {proposal.line_items.map(li => (
                                <TableRow key={li.id}>
                                    <TableCell className="py-3">
                                        <p className="text-sm font-medium text-gray-900">{li.service}</p>
                                        {li.description && <p className="text-xs text-gray-500">{li.description}</p>}
                                    </TableCell>
                                    <TableCell className="py-3 text-right text-sm text-gray-600">{li.quantity}</TableCell>
                                    <TableCell className="py-3 text-right text-sm text-gray-600">{fmt(li.unit_price)}</TableCell>
                                    <TableCell className="py-3 text-right text-xs text-gray-500">{FREQ_LABELS[li.frequency] ?? li.frequency}</TableCell>
                                    <TableCell className="py-3 text-right text-sm font-medium text-gray-900">{fmt(li.unit_price * li.quantity)}</TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                        <tfoot>
                            <TableRow className="border-t border-gray-200">
                                <TableCell colSpan={4} className="pt-3 text-right text-sm font-bold text-gray-900">Total</TableCell>
                                <TableCell className="pt-3 text-right text-sm font-bold text-gray-900">{fmt(proposal.total_value)}</TableCell>
                            </TableRow>
                        </tfoot>
                    </Table>

                    {proposal.notes && (
                        <div className="pt-4 border-t border-gray-100">
                            <p className="text-xs text-gray-500 font-medium mb-1">Notes</p>
                            <p className="text-sm text-gray-700 leading-relaxed">{proposal.notes}</p>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}

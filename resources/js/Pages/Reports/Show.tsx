import React, { useState } from 'react';
import { Button } from '@/Components/ui/Button';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { ArrowLeft, Download, Mail, RefreshCw, FileText, Calendar, Check, Send, MessageSquare } from 'lucide-react';
import { cn } from '@/lib/utils';
import axios from 'axios';
import { toast } from 'sonner';
import { CommentsSection } from '@/Pages/Tasks/Partials/CommentsSection';

interface Report {
    id: string;
    title: string;
    type: string;
    template: string;
    status: string;
    date_from: string;
    date_to: string;
    generated_file_path: string | null;
    recipients: string[];
    created_at: string;
    project?: { name: string } | null;
    client?: { name: string } | null;
    generated_by?: { name: string } | null;
    comments?: any[];
}

interface Props {
    report: Report;
}

export default function ReportsShow({ report }: Props) {
    const [sending, setSending] = useState(false);
    const [downloading, setDownloading] = useState(false);
    const [recipientInput, setRecipientInput] = useState('');

    const handleRegenerate = () => {
        toast.info('Regeneration dispatched...');
        axios.post(`/api/v1/reports/${report.id}/generate`)
            .then(() => {
                toast.success('Dispatched background generator job!');
                router.reload();
            });
    };

    const handleSendEmail = (e: React.FormEvent) => {
        e.preventDefault();
        if (!recipientInput) return;
        setSending(true);

        axios.post(`/api/v1/reports/${report.id}/send`, {
            recipients: [recipientInput]
        })
        .then(() => {
            toast.success('Report emailed successfully!');
            setRecipientInput('');
            router.reload();
        })
        .catch(err => {
            toast.error(err.response?.data?.message ?? 'Failed to send report.');
        })
        .finally(() => {
            setSending(false);
        });
    };

    const handleDownload = async () => {
        setDownloading(true);
        try {
            const response = await axios.get(`/api/v1/reports/${report.id}/download`, {
                responseType: 'blob',
            });
            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', `${report.title}.pdf`);
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);
            toast.success('Download ready');
        } catch (error) {
            toast.error('Failed to download PDF.');
        } finally {
            setDownloading(false);
        }
    };

    return (
        <AppLayout title={report.title}>
            <Head title={report.title} />

            {/* Back action */}
            <div className="mb-6 flex items-center justify-between">
                <Link
                    href="/internal-reports"
                    className="inline-flex items-center gap-1.5 text-xs font-bold text-gray-500 hover:text-gray-900"
                >
                    <ArrowLeft size={13} /> Back to reports
                </Link>
                
                <div className="flex gap-2">
                    <Button
                        onClick={handleRegenerate}
                        className="flex items-center gap-1" 
                    variant="ghost" size="sm" >
                        <RefreshCw size={12} /> Regenerate
                    </Button>
                    {report.status === 'ready' && (
                        <Button
                            onClick={handleDownload}
                            disabled={downloading}
                            className="flex items-center gap-1 transition-all shadow-md disabled:bg-indigo-400 disabled:cursor-not-allowed" 
                        size="sm" >
                            {downloading ? <RefreshCw className="animate-spin" size={12} /> : <Download size={12} />} 
                            {downloading ? 'Preparing...' : 'Download PDF'}
                        </Button>
                    )}
                </div>
            </div>

            {/* Content grid */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">

                {/* Left side: Preview */}
                <div className="lg:col-span-2 bg-white border border-gray-100 rounded-2xl shadow-[0_1px_3px_rgba(0,0,0,0.02)] overflow-hidden h-[600px] flex flex-col">
                    <div className="bg-gray-50/50 border-b border-gray-100 px-6 py-4 flex items-center justify-between">
                        <span className="text-xs font-bold text-gray-800 flex items-center gap-2">
                            <FileText size={14} className="text-indigo-500" /> Inline Preview
                        </span>
                        <span className={cn(
                            "px-2 py-0.5 rounded-full text-[10px] font-bold capitalize",
                            report.status === 'ready' ? "bg-emerald-50 text-emerald-700" : "bg--50 text--800 animate-pulse"
                        )}>
                            {report.status}
                        </span>
                    </div>

                    <div className="flex-1 bg-gray-50 flex items-center justify-center p-4 relative">
                        {report.status === 'ready' && report.generated_file_path ? (
                            <iframe
                                src={`/api/v1/reports/${report.id}/download`}
                                className="w-full h-full rounded-xl border border-gray-200 bg-white"
                                title={report.title}
                            />
                        ) : (
                            <div className="text-center max-w-sm">
                                <RefreshCw className="animate-spin text-indigo-500 mx-auto mb-4" size={28} />
                                <h4 className="text-xs font-bold text-gray-900">Generating Report Preview</h4>
                                <p className="text-[11px] text-gray-400 mt-1">This can take up to 30 seconds as headless Chrome compiles data vectors.</p>
                            </div>
                        )}
                    </div>
                </div>

                {/* Right side: Config & Actions */}
                <div className="space-y-6">

                    {/* Metadata summary */}
                    <div className="bg-white border border-gray-100 rounded-2xl p-6 shadow-[0_1px_3px_rgba(0,0,0,0.02)] space-y-4">
                        <h3 className="text-xs font-bold text-gray-900 border-b border-gray-55 pb-2">Report Profile</h3>
                        
                        <div className="space-y-3 text-xs">
                            <div className="flex justify-between">
                                <span className="text-gray-400">Template</span>
                                <span className="font-semibold text-gray-900 capitalize">{report.template.replace('_', ' ')}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-gray-400">Time Range</span>
                                <span className="font-semibold text-gray-900">{report.date_from} to {report.date_to}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-gray-400">Project</span>
                                <span className="font-semibold text-gray-900">{report.project?.name ?? 'None'}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-gray-400">Client</span>
                                <span className="font-semibold text-gray-900">{report.client?.name ?? 'None'}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-gray-400">Created By</span>
                                <span className="font-semibold text-gray-900">{report.generated_by?.name ?? 'System'}</span>
                            </div>
                        </div>
                    </div>

                    {/* Quick Share Form */}
                    {report.status === 'ready' && (
                        <div className="bg-white border border-gray-100 rounded-2xl p-6 shadow-[0_1px_3px_rgba(0,0,0,0.02)] space-y-4">
                            <h3 className="text-xs font-bold text-gray-900 border-b border-gray-55 pb-2">Quick Share via Email</h3>
                            
                            <form onSubmit={handleSendEmail} className="space-y-3">
                                <div>
                                    <label className="block text-[10px] font-bold text-gray-500 mb-1">Email Address</label>
                                    <input
                                        type="email"
                                        required
                                        value={recipientInput}
                                        onChange={e => setRecipientInput(e.target.value)}
                                        placeholder="partner@client.com"
                                        className="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs bg-gray-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    disabled={sending}
                                    className="w-full flex items-center justify-center gap-1.5 disabled:bg-indigo-400 transition-all shadow-md" 
                                size="sm" >
                                    <Send size={12} /> {sending ? 'Sending...' : 'Mail PDF'}
                                </Button>
                            </form>

                            {report.recipients && report.recipients.length > 0 && (
                                <div className="pt-2">
                                    <label className="block text-[10px] font-bold text-gray-400 mb-1.5 uppercase">Delivered to</label>
                                    <div className="flex flex-wrap gap-1.5">
                                        {report.recipients.map(e => (
                                            <span key={e} className="inline-flex items-center gap-1 bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded-lg text-[10px] font-semibold border border-emerald-100">
                                                <Check size={9} /> {e}
                                            </span>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Internal Comments Section */}
                    <div className="bg-white border border-gray-100 rounded-2xl p-6 shadow-[0_1px_3px_rgba(0,0,0,0.02)] space-y-4">
                        <h3 className="text-xs font-bold text-gray-900 border-b border-gray-55 pb-2 flex items-center gap-2">
                            <MessageSquare size={14} className="text-gray-400" /> Internal Notes & Annotations
                        </h3>
                        <CommentsSection
                            submitUrl={`/internal-reports/${report.id}/comments`}
                            deleteUrlTemplate={(id) => `/internal-reports/${report.id}/comments/${id}`}
                            comments={report.comments || []}
                        />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

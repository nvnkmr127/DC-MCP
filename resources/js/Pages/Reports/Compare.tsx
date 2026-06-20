import React from 'react';
import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { ArrowLeft, FileText } from 'lucide-react';
import { cn } from '@/lib/utils';

interface Report {
    id: string;
    title: string;
    type: string;
    template: string;
    status: string;
    date_from: string;
    date_to: string;
    generated_file_path: string | null;
    project?: { name: string } | null;
    client?: { name: string } | null;
    generated_by?: { name: string } | null;
}

interface Props {
    report1: Report;
    report2: Report;
}

export default function CompareReports({ report1, report2 }: Props) {
    const renderReportPanel = (report: Report, label: string) => (
        <div className="bg-white border border-gray-100 rounded-2xl shadow-[0_1px_3px_rgba(0,0,0,0.02)] overflow-hidden flex flex-col h-[800px]">
            <div className="bg-gray-50 border-b border-gray-100 px-6 py-4 flex flex-col gap-2">
                <div className="flex items-center justify-between">
                    <span className="text-xs font-bold text-gray-800 flex items-center gap-2">
                        <FileText size={14} className="text-indigo-500" /> {label}
                    </span>
                    <span className={cn(
                        "px-2 py-0.5 rounded-full text-[10px] font-bold capitalize",
                        report.status === 'ready' ? "bg-emerald-50 text-emerald-700" : "bg-amber-50 text-amber-700"
                    )}>
                        {report.status}
                    </span>
                </div>
                <div>
                    <h3 className="text-sm font-bold text-gray-900 truncate">{report.title}</h3>
                    <p className="text-xs text-gray-500 mt-1">
                        {report.date_from} to {report.date_to}
                    </p>
                </div>
            </div>

            <div className="flex-1 bg-gray-100 flex items-center justify-center relative p-4">
                {report.status === 'ready' && report.generated_file_path ? (
                    <iframe
                        src={`/api/v1/reports/${report.id}/download`}
                        className="w-full h-full rounded-xl border border-gray-200 bg-white"
                        title={report.title}
                    />
                ) : (
                    <div className="text-center text-gray-500 text-xs">
                        Report not ready or missing PDF.
                    </div>
                )}
            </div>
        </div>
    );

    return (
        <AppLayout title="Compare Reports">
            <Head title="Compare Reports" />

            <div className="mb-6 flex items-center justify-between">
                <Link
                    href="/reports"
                    className="inline-flex items-center gap-1.5 text-xs font-bold text-gray-500 hover:text-gray-900"
                >
                    <ArrowLeft size={13} /> Back to reports
                </Link>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {renderReportPanel(report1, 'Report A')}
                {renderReportPanel(report2, 'Report B')}
            </div>
        </AppLayout>
    );
}

import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { formatHours, cn } from '@/lib/utils';
import {
    BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer,
    PieChart, Pie, Cell, LineChart, Line, AreaChart, Area,
} from 'recharts';
import { CalendarRange, Download, TrendingUp, BarChart3, FileText, Calendar, Plus, Mail } from 'lucide-react';
import axios from 'axios';
import { toast } from 'sonner';

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
}

interface Schedule {
    id: string;
    title: string;
    type: string;
    template: string;
    frequency: string;
    send_day: number;
    recipients: string[];
    is_active: boolean;
    next_run_at: string | null;
    project?: { name: string } | null;
    client?: { name: string } | null;
}

interface ReportData {
    task_completion: Array<{ date: string; completed: number }>;
    tasks_by_status: Record<string, number>;
    tasks_by_priority: Record<string, number>;
    time_by_user: Array<{ user: string; hours: number }>;
    time_by_project: Array<{ project: string; hours: number }>;
    projects: Array<{ name: string; total: number; completed: number; overdue: number; completion_pct: number }>;
}

interface Props {
    data: ReportData;
    filters: { from: string; to: string };
    reports: Report[];
    schedules: Schedule[];
}

const PIE_COLORS = ['#6366f1', '#a855f7', '#ec4899', '#3b82f6', '#10b981', '#f59e0b'];
const QUICK_RANGES = [
    { label: 'Last 7 days',  days: 7 },
    { label: 'Last 30 days', days: 30 },
    { label: 'Last 90 days', days: 90 },
];

export default function ReportsIndex({ data, filters, reports, schedules }: Props) {
    const [activeTab, setActiveTab] = useState<'analytics' | 'pdfs' | 'schedules'>('analytics');
    const [from, setFrom] = useState(filters.from);
    const [to, setTo]     = useState(filters.to);
    const [emailModalReport, setEmailModalReport] = useState<Report | null>(null);
    const [recipientEmail, setRecipientEmail] = useState('');

    function applyRange() {
        router.get('/reports', { from, to }, { preserveState: true });
    }

    function applyQuick(days: number) {
        const t = new Date();
        const f = new Date();
        f.setDate(f.getDate() - days);
        const fmt = (d: Date) => d.toISOString().split('T')[0];
        router.get('/reports', { from: fmt(f), to: fmt(t) }, { preserveState: true });
    }

    const statusPieData   = Object.entries(data.tasks_by_status).map(([k, v]) => ({ name: k.replace(/_/g, ' '), value: v }));
    const totalHours      = data.time_by_user.reduce((a, b) => a + b.hours, 0);
    const totalCompleted  = data.task_completion.reduce((a, b) => a + b.completed, 0);

    const handleSendEmail = (e: React.FormEvent) => {
        e.preventDefault();
        if (!emailModalReport || !recipientEmail) return;

        axios.post(`/api/v1/reports/${emailModalReport.id}/send`, {
            recipients: [recipientEmail]
        })
        .then(() => {
            toast.success('Report emailed successfully!');
            setEmailModalReport(null);
            setRecipientEmail('');
        })
        .catch(err => {
            toast.error(err.response?.data?.message ?? 'Failed to send report.');
        });
    };

    return (
        <AppLayout title="Analytics & Reports">
            <Head title="Reports" />

            {/* Title & Generate action */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                <div>
                    <h1 className="text-xl font-extrabold text-gray-900 tracking-tight">Intelligence & Reports</h1>
                    <p className="text-xs text-gray-400 mt-1 font-medium">Generate SEO audits, client performance reports, and sprint summaries.</p>
                </div>
                <Link
                    href="/reports/create"
                    className="flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl transition-all shadow-md self-start sm:self-auto"
                >
                    <Plus size={14} /> New Report
                </Link>
            </div>

            {/* Tabs */}
            <div className="flex gap-2 border-b border-gray-100/60 pb-px mb-6">
                {[
                    { id: 'analytics', label: 'Performance Analytics', icon: BarChart3 },
                    { id: 'pdfs', label: 'Generated PDF Reports', icon: FileText },
                    { id: 'schedules', label: 'Report Schedules', icon: Calendar },
                ].map(t => (
                    <button
                        key={t.id}
                        onClick={() => setActiveTab(t.id as any)}
                        className={cn(
                            "flex items-center gap-2 px-4 py-2.5 text-xs font-bold transition-all relative border-b-2 -mb-[2px]",
                            activeTab === t.id
                                ? "border-indigo-600 text-indigo-600"
                                : "border-transparent text-gray-400 hover:text-gray-900"
                        )}
                    >
                        <t.icon size={13} /> {t.label}
                    </button>
                ))}
            </div>

            {/* TAB CONTENT: ANALYTICS */}
            {activeTab === 'analytics' && (
                <>
                    {/* Date range bar */}
                    <div className="bg-white border border-gray-100 rounded-2xl p-4 mb-6 flex flex-col sm:flex-row items-start sm:items-center gap-3 shadow-[0_1px_3px_rgba(0,0,0,0.02)]">
                        <CalendarRange size={15} className="text-gray-400 shrink-0" />
                        <div className="flex items-center gap-2 flex-wrap flex-1">
                            {QUICK_RANGES.map(({ label, days }) => (
                                <button
                                    key={days}
                                    onClick={() => applyQuick(days)}
                                    className="px-2.5 py-1 text-[11px] font-bold text-gray-500 hover:text-indigo-600 hover:bg-indigo-50/50 rounded-lg transition-colors border border-gray-200"
                                >
                                    {label}
                                </button>
                            ))}
                            <div className="w-px h-4 bg-gray-200 mx-1" />
                            <div className="flex items-center gap-2">
                                <input
                                    type="date"
                                    value={from}
                                    onChange={e => setFrom(e.target.value)}
                                    className="px-3 py-1.5 text-xs border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-gray-50 focus:bg-white transition-colors"
                                />
                                <span className="text-gray-400 text-xs">→</span>
                                <input
                                    type="date"
                                    value={to}
                                    onChange={e => setTo(e.target.value)}
                                    className="px-3 py-1.5 text-xs border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-gray-50 focus:bg-white transition-colors"
                                />
                                <button
                                    onClick={applyRange}
                                    className="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl transition-all shadow-md"
                                >
                                    Apply
                                </button>
                            </div>
                        </div>
                    </div>

                    {/* Summary KPIs */}
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-5 mb-6">
                        {[
                            { label: 'Tasks Completed',   value: totalCompleted,           color: 'text-indigo-600', bg: 'bg-indigo-50/50' },
                            { label: 'Total Hours Logged', value: `${totalHours.toFixed(1)}h`, color: 'text-sky-600', bg: 'bg-sky-50/50' },
                            { label: 'Active Projects',    value: data.projects.length,      color: 'text-emerald-600', bg: 'bg-emerald-50/50' },
                            { label: 'Team Members',       value: data.time_by_user.length,  color: 'text-orange-600', bg: 'bg-orange-50/50' },
                        ].map(({ label, value, color, bg }) => (
                            <div key={label} className="bg-white rounded-2xl border border-gray-100 p-5">
                                <div className={cn('inline-flex w-8 h-8 rounded-xl items-center justify-center mb-3', bg)}>
                                    <TrendingUp size={15} className={color} />
                                </div>
                                <p className="text-2xl font-extrabold text-gray-900 tracking-tight tabular-nums">{value}</p>
                                <p className="text-[11px] text-gray-400 mt-1 font-semibold uppercase tracking-wider">{label}</p>
                            </div>
                        ))}
                    </div>

                    {/* Charts grid */}
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        {/* Task completion over time */}
                        <div className="bg-white rounded-2xl border border-gray-100 p-6 shadow-[0_1px_3px_rgba(0,0,0,0.02)]">
                            <h3 className="text-[13px] font-bold text-gray-900 mb-4">Task Completions</h3>
                            <ResponsiveContainer width="100%" height={210}>
                                <AreaChart data={data.task_completion}>
                                    <defs>
                                        <linearGradient id="grad1" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="5%"  stopColor="#6366f1" stopOpacity={0.12} />
                                            <stop offset="95%" stopColor="#6366f1" stopOpacity={0} />
                                        </linearGradient>
                                    </defs>
                                    <CartesianGrid strokeDasharray="3 3" stroke="#f3f4f6" vertical={false} />
                                    <XAxis dataKey="date" tick={{ fontSize: 10, fill: '#9ca3af' }} axisLine={false} tickLine={false} />
                                    <YAxis tick={{ fontSize: 10, fill: '#9ca3af' }} axisLine={false} tickLine={false} allowDecimals={false} />
                                    <Tooltip contentStyle={{ borderRadius: '12px', border: '1px solid #f1f5f9', fontSize: '11px' }} />
                                    <Area type="monotone" dataKey="completed" stroke="#6366f1" strokeWidth={2} fill="url(#grad1)" dot={false} />
                                </AreaChart>
                            </ResponsiveContainer>
                        </div>

                        {/* Time by user */}
                        <div className="bg-white rounded-2xl border border-gray-100 p-6 shadow-[0_1px_3px_rgba(0,0,0,0.02)]">
                            <h3 className="text-[13px] font-bold text-gray-900 mb-4">Time by Team Member</h3>
                            <ResponsiveContainer width="100%" height={210}>
                                <BarChart data={data.time_by_user} layout="vertical">
                                    <CartesianGrid strokeDasharray="3 3" stroke="#f3f4f6" horizontal={false} />
                                    <XAxis type="number" tick={{ fontSize: 10, fill: '#9ca3af' }} axisLine={false} tickLine={false} />
                                    <YAxis dataKey="user" type="category" tick={{ fontSize: 10, fill: '#6b7280' }} width={80} axisLine={false} tickLine={false} />
                                    <Tooltip formatter={(v: any) => [`${v}h`, 'Hours']} contentStyle={{ borderRadius: '12px', border: '1px solid #f1f5f9', fontSize: '11px' }} />
                                    <Bar dataKey="hours" fill="#6366f1" radius={[0, 4, 4, 0]} maxBarSize={16} />
                                </BarChart>
                            </ResponsiveContainer>
                        </div>
                    </div>
                </>
            )}

            {/* TAB CONTENT: PDF REPORTS */}
            {activeTab === 'pdfs' && (
                <div className="bg-white border border-gray-100 rounded-2xl shadow-[0_1px_3px_rgba(0,0,0,0.02)] overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-xs">
                            <thead>
                                <tr className="border-b border-gray-100 bg-gray-50/70">
                                    <th className="text-left px-5 py-3.5 font-bold text-gray-400 uppercase tracking-wider">Report Info</th>
                                    <th className="text-left px-5 py-3.5 font-bold text-gray-400 uppercase tracking-wider">Project / Client</th>
                                    <th className="text-left px-5 py-3.5 font-bold text-gray-400 uppercase tracking-wider">Date Period</th>
                                    <th className="text-left px-5 py-3.5 font-bold text-gray-400 uppercase tracking-wider">Status</th>
                                    <th className="text-right px-5 py-3.5 font-bold text-gray-400 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-50">
                                {reports.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="text-center py-12 text-gray-400">
                                            No reports generated yet. Click "New Report" to create your first report.
                                        </td>
                                    </tr>
                                ) : (
                                    reports.map(rep => (
                                        <tr key={rep.id} className="hover:bg-gray-50/50 transition-colors">
                                            <td className="px-5 py-4">
                                                <div className="font-bold text-gray-900 text-[13px]">{rep.title}</div>
                                                <div className="text-gray-400 mt-0.5 capitalize text-[10px]">{rep.template.replace('_', ' ')}</div>
                                            </td>
                                            <td className="px-5 py-4 text-gray-600">
                                                {rep.project?.name ?? rep.client?.name ?? 'General'}
                                            </td>
                                            <td className="px-5 py-4 text-gray-500">
                                                {rep.date_from} → {rep.date_to}
                                            </td>
                                            <td className="px-5 py-4">
                                                <span className={cn(
                                                    "px-2 py-0.5 rounded-full text-[10px] font-bold capitalize",
                                                    rep.status === 'ready' ? "bg-emerald-50 text-emerald-700" :
                                                    rep.status === 'generating' ? "bg-amber-50 text-amber-700 animate-pulse" : "bg-gray-50 text-gray-600"
                                                )}>
                                                    {rep.status}
                                                </span>
                                            </td>
                                            <td className="px-5 py-4 text-right space-x-2">
                                                <Link
                                                    href={`/reports/${rep.id}`}
                                                    className="inline-flex items-center gap-1 px-2.5 py-1.5 bg-gray-50 hover:bg-gray-100 text-gray-600 rounded-lg font-bold"
                                                >
                                                    View
                                                </Link>
                                                {rep.status === 'ready' && (
                                                    <>
                                                        <a
                                                            href={`/api/v1/reports/${rep.id}/download`}
                                                            className="inline-flex items-center gap-1 px-2.5 py-1.5 bg-indigo-55 text-indigo-600 hover:bg-indigo-50 rounded-lg font-bold"
                                                        >
                                                            <Download size={12} /> Download
                                                        </a>
                                                        <button
                                                            onClick={() => setEmailModalReport(rep)}
                                                            className="inline-flex items-center gap-1 px-2.5 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-bold shadow-sm"
                                                        >
                                                            <Mail size={12} /> Send Email
                                                        </button>
                                                    </>
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {/* TAB CONTENT: SCHEDULES */}
            {activeTab === 'schedules' && (
                <div className="bg-white border border-gray-100 rounded-2xl shadow-[0_1px_3px_rgba(0,0,0,0.02)] overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-xs">
                            <thead>
                                <tr className="border-b border-gray-100 bg-gray-50/70">
                                    <th className="text-left px-5 py-3.5 font-bold text-gray-400 uppercase tracking-wider">Schedule Config</th>
                                    <th className="text-left px-5 py-3.5 font-bold text-gray-400 uppercase tracking-wider">Target</th>
                                    <th className="text-left px-5 py-3.5 font-bold text-gray-400 uppercase tracking-wider">Frequency</th>
                                    <th className="text-left px-5 py-3.5 font-bold text-gray-400 uppercase tracking-wider">Next Run</th>
                                    <th className="text-left px-5 py-3.5 font-bold text-gray-400 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-50">
                                {schedules.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="text-center py-12 text-gray-400">
                                            No report schedules configured. Report schedules can be configured dynamically via reports creation.
                                        </td>
                                    </tr>
                                ) : (
                                    schedules.map(sched => (
                                        <tr key={sched.id} className="hover:bg-gray-50/50 transition-colors">
                                            <td className="px-5 py-4">
                                                <div className="font-bold text-gray-900 text-[13px]">{sched.title}</div>
                                                <div className="text-gray-400 mt-0.5 capitalize text-[10px]">{sched.template.replace('_', ' ')}</div>
                                            </td>
                                            <td className="px-5 py-4 text-gray-600">
                                                {sched.project?.name ?? sched.client?.name ?? 'General'}
                                            </td>
                                            <td className="px-5 py-4 capitalize text-gray-500">
                                                {sched.frequency} (Day {sched.send_day})
                                            </td>
                                            <td className="px-5 py-4 text-gray-500">
                                                {sched.next_run_at ? sched.next_run_at.slice(0, 10) : '—'}
                                            </td>
                                            <td className="px-5 py-4">
                                                <span className={cn(
                                                    "px-2 py-0.5 rounded-full text-[10px] font-bold",
                                                    sched.is_active ? "bg-emerald-50 text-emerald-700" : "bg-gray-50 text-gray-600"
                                                )}>
                                                    {sched.is_active ? 'Active' : 'Inactive'}
                                                </span>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {/* Email Send Modal */}
            {emailModalReport && (
                <div className="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="bg-white rounded-2xl p-6 w-full max-w-md shadow-2xl border border-gray-100">
                        <div className="flex justify-between items-center mb-4">
                            <h3 className="text-sm font-bold text-gray-900">Email Report</h3>
                            <button onClick={() => setEmailModalReport(null)} className="text-gray-400 hover:text-gray-700">
                                <Plus className="rotate-45" size={18} />
                            </button>
                        </div>
                        <p className="text-xs text-gray-400 mb-4">This will send the PDF report: <strong>{emailModalReport.title}</strong> directly to the recipient.</p>
                        
                        <form onSubmit={handleSendEmail} className="space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-gray-600 mb-1">Recipient Email</label>
                                <input
                                    type="email"
                                    required
                                    value={recipientEmail}
                                    onChange={e => setRecipientEmail(e.target.value)}
                                    placeholder="client@example.com"
                                    className="w-full px-3.5 py-2 border border-gray-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white bg-gray-50"
                                />
                            </div>
                            <div className="flex justify-end gap-2 pt-2">
                                <button
                                    type="button"
                                    onClick={() => setEmailModalReport(null)}
                                    className="px-3.5 py-2 border border-gray-200 rounded-xl text-xs font-semibold text-gray-600 hover:bg-gray-50"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    className="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl transition-all shadow-md"
                                >
                                    Send Report
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}

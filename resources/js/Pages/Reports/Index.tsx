import React, { useState } from 'react';
import { Button } from '@/Components/ui/Button';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { formatHours, cn } from '@/lib/utils';
import {
    BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer,
    PieChart, Pie, Cell, LineChart, Line, AreaChart, Area,
} from 'recharts';
import { CalendarRange, Download, TrendingUp, BarChart3, FileText, Calendar, Plus, Mail, RefreshCw } from 'lucide-react';
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
    is_public?: boolean;
    share_token?: string | null;
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
    project_id?: string | null;
    client_id?: string | null;
    config?: { selected_projects?: string[]; selected_tasks?: string[] } | null;
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
    const [shareModalReport, setShareModalReport] = useState<Report | null>(null);
    const [recipientEmail, setRecipientEmail] = useState('');
    
    // Compare state
    const [compareMode, setCompareMode] = useState(false);
    const [selectedForCompare, setSelectedForCompare] = useState<string[]>([]);
    
    const [editSchedule, setEditSchedule] = useState<Schedule | null>(null);
    const [scheduleConfig, setScheduleConfig] = useState<{ selected_projects: string[], selected_tasks: string[] }>({ selected_projects: [], selected_tasks: [] });
    const [availableItems, setAvailableItems] = useState<any[]>([]);
    const [fetchingItems, setFetchingItems] = useState(false);
    const [downloadingId, setDownloadingId] = useState<string | null>(null);

    const handleDownload = async (id: string, title: string) => {
        setDownloadingId(id);
        try {
            const response = await axios.get(`/api/v1/reports/${id}/download`, {
                responseType: 'blob',
            });
            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', `${title}.pdf`);
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);
            toast.success('Download ready');
        } catch (error) {
            toast.error('Failed to download PDF.');
        } finally {
            setDownloadingId(null);
        }
    };

    function applyRange() {
        router.get('/internal-reports', { from, to }, { preserveState: true });
    }

    function applyQuick(days: number) {
        const t = new Date();
        const f = new Date();
        f.setDate(f.getDate() - days);
        const fmt = (d: Date) => d.toISOString().split('T')[0];
        router.get('/internal-reports', { from: fmt(f), to: fmt(t) }, { preserveState: true });
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
        <AppLayout title="Internal Reports">
            <Head title="Internal Reports" />

            {/* Title & Generate action */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                <div>
                    <h1 className="text-xl font-extrabold text-gray-900 tracking-tight">Internal Reports</h1>
                    <p className="text-xs text-gray-400 mt-1 font-medium">Generate SEO audits, client performance reports, and sprint summaries.</p>
                </div>
                <div className="flex items-center gap-2">
                    <Button
                        onClick={() => {
                            setCompareMode(!compareMode);
                            setSelectedForCompare([]);
                        }}
                        className={cn(
                            "px-3 py-1.5 text-xs font-bold rounded-xl transition-colors border",
                            compareMode ? "bg-indigo-50 text-indigo-700 border-indigo-200" : "bg-white text-gray-700 border-gray-200 hover:bg-gray-50"
                        )}
                    >
                        {compareMode ? "Cancel Compare" : "Compare Reports"}
                    </Button>
                    <Link
                        href="/internal-reports/create"
                        className="flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl transition-all shadow-sm"
                    >
                        <Plus size={13} /> Build Report
                    </Link>
                </div>
            </div>

            {/* Tabs */}
            <div className="flex gap-2 border-b border-gray-100/60 pb-px mb-6">
                {[
                    { id: 'analytics', label: 'Performance Analytics', icon: BarChart3 },
                    { id: 'pdfs', label: 'Generated PDF Reports', icon: FileText },
                    { id: 'schedules', label: 'Report Schedules', icon: Calendar },
                ].map(t => (
                    <Button
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
                    </Button>
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
                                <Button
                                    key={days}
                                    onClick={() => applyQuick(days)}
                                    className="px-2.5 py-1 text-[11px] font-bold text-gray-500 hover:text-indigo-600 hover:bg-indigo-50/50 rounded-lg transition-colors border border-gray-200"
                                >
                                    {label}
                                </Button>
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
                                <Button
                                    onClick={applyRange}
                                    className="transition-all shadow-md" 
                                size="sm" >
                                    Apply
                                </Button>
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
                                    {compareMode && <th className="px-5 py-3 w-10 text-center"></th>}
                                    <th className="px-5 py-3 text-left font-bold text-gray-400 uppercase tracking-wider">Report Title</th>
                                    <th className="px-5 py-3 text-left font-bold text-gray-400 uppercase tracking-wider">Period</th>
                                    <th className="px-5 py-3 text-left font-bold text-gray-400 uppercase tracking-wider">Project / Client</th>
                                    <th className="px-5 py-3 text-left font-bold text-gray-400 uppercase tracking-wider">Status</th>
                                    {!compareMode && <th className="px-5 py-3 text-right font-bold text-gray-400 uppercase tracking-wider">Actions</th>}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-50">
                                {reports.length === 0 ? (
                                    <tr>
                                        <td colSpan={compareMode ? 6 : 5} className="py-16 text-center">
                                            <div className="flex flex-col items-center justify-center">
                                                <div className="w-12 h-12 rounded-xl bg-gray-50 border border-gray-100 shadow-sm flex items-center justify-center mx-auto mb-4">
                                                    <FileText size={20} className="text-gray-400" />
                                                </div>
                                                <p className="text-[14px] font-semibold text-gray-900 mb-1">No reports generated yet</p>
                                                <p className="text-[13px] text-gray-500 max-w-sm mb-6">Create your first client report. Start from scratch or use templates for monthly summaries, SEO audits, and campaign results.</p>
                                                <Button onClick={() => setModalOpen(true)} className="px-4 py-2 bg-indigo-600 border border-transparent rounded-lg text-[13px] font-semibold text-white hover:bg-indigo-700 transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1">
                                                    Create New Report
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ) : (
                                    reports.map(rep => (
                                        <tr key={rep.id} className="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                                            {compareMode && (
                                                <td className="px-5 py-4 text-center">
                                                    <input
                                                        type="checkbox"
                                                        className="rounded text-indigo-600 focus:ring-indigo-500 border-gray-300 w-4 h-4"
                                                        checked={selectedForCompare.includes(rep.id)}
                                                        disabled={!selectedForCompare.includes(rep.id) && selectedForCompare.length >= 2}
                                                        onChange={(e) => {
                                                            if (e.target.checked) {
                                                                setSelectedForCompare(prev => [...prev, rep.id]);
                                                            } else {
                                                                setSelectedForCompare(prev => prev.filter(id => id !== rep.id));
                                                            }
                                                        }}
                                                    />
                                                </td>
                                            )}
                                            <td className="px-5 py-4">
                                                <div className="text-sm font-bold text-gray-900">{rep.title}</div>
                                                <div className="text-[10px] uppercase font-bold tracking-wider text-gray-400 mt-0.5">{rep.template.replace('_', ' ')}</div>
                                            </td>
                                            <td className="px-5 py-4 text-xs font-semibold text-gray-500">
                                                {rep.date_from} — {rep.date_to}
                                            </td>
                                            <td className="px-5 py-4">
                                                {rep.project ? (
                                                    <div className="text-xs font-bold text-gray-800">{rep.project.name}</div>
                                                ) : rep.client ? (
                                                    <div className="text-xs font-bold text-gray-800">{rep.client.name}</div>
                                                ) : <span className="text-gray-400 text-xs">—</span>}
                                            </td>
                                            <td className="px-5 py-4">
                                                <span className={cn(
                                                    "px-2 py-0.5 rounded-full text-[10px] font-bold capitalize",
                                                    rep.status === 'ready' ? "bg-emerald-50 text-emerald-700" : "bg--50 text--800"
                                                )}>
                                                    {rep.status}
                                                </span>
                                            </td>
                                            {!compareMode && (
                                                <td className="px-5 py-4 text-right space-x-2">
                                                    <Link
                                                        href={`/internal-reports/${rep.id}`}
                                                        className="inline-flex items-center gap-1 px-2.5 py-1.5 bg-gray-50 hover:bg-gray-100 text-gray-700 rounded-lg font-bold"
                                                    >
                                                        View
                                                    </Link>
                                                    {rep.status === 'ready' && (
                                                        <>
                                                            <Button
                                                                onClick={() => handleDownload(rep.id, rep.title)}
                                                                disabled={downloadingId === rep.id}
                                                                className="inline-flex items-center gap-1 px-2.5 py-1.5 bg-indigo-50 text-indigo-600 hover:bg-indigo-100 rounded-lg font-bold disabled:opacity-50"
                                                            >
                                                                {downloadingId === rep.id ? <RefreshCw className="animate-spin" size={12} /> : <Download size={12} />}
                                                                {downloadingId === rep.id ? 'Preparing...' : 'Download'}
                                                            </Button>
                                                            <Button
                                                                onClick={() => setEmailModalReport(rep)}
                                                                className="inline-flex items-center gap-1 px-2.5 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-bold shadow-sm"
                                                            >
                                                                <Mail size={12} /> Send Email
                                                            </Button>
                                                            <Button
                                                                onClick={() => setShareModalReport(rep)}
                                                                className="inline-flex items-center gap-1 px-2.5 py-1.5 bg-gray-50 hover:bg-gray-100 text-gray-700 rounded-lg font-bold"
                                                            >
                                                                Share
                                                            </Button>
                                                        </>
                                                    )}
                                                </td>
                                            )}
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
                                    <th className="text-right px-5 py-3.5 font-bold text-gray-400 uppercase tracking-wider">Actions</th>
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
                                                <Button
                                                    onClick={() => {
                                                        axios.patch(`/api/v1/report-schedules/${sched.id}`, {
                                                            is_active: !sched.is_active
                                                        }).then(() => {
                                                            toast.success(`Schedule ${sched.is_active ? 'paused' : 'activated'}`);
                                                            router.reload();
                                                        });
                                                    }}
                                                    className={cn(
                                                        "px-2 py-0.5 rounded-full text-[10px] font-bold cursor-pointer transition-colors border",
                                                        sched.is_active 
                                                            ? "bg-emerald-50 text-emerald-700 border-emerald-100 hover:bg-emerald-100" 
                                                            : "bg-gray-50 text-gray-700 border-gray-200 hover:bg-gray-100"
                                                    )}
                                                >
                                                    {sched.is_active ? 'Active' : 'Paused'}
                                                </Button>
                                            </td>
                                            <td className="px-5 py-4 text-right">
                                                <div className="flex justify-end items-center gap-2">
                                                    <Button
                                                        onClick={() => {
                                                            setEditSchedule(sched);
                                                            setScheduleConfig({
                                                                selected_projects: sched.config?.selected_projects || [],
                                                                selected_tasks: sched.config?.selected_tasks || [],
                                                            });
                                                            if (sched.client_id && !sched.project_id) {
                                                                setFetchingItems(true);
                                                                axios.get(`/api/v1/projects?client_id=${sched.client_id}`)
                                                                    .then(res => setAvailableItems(res.data?.data || []))
                                                                    .finally(() => setFetchingItems(false));
                                                            } else if (sched.project_id) {
                                                                setFetchingItems(true);
                                                                axios.get(`/api/v1/tasks?project_id=${sched.project_id}`)
                                                                    .then(res => setAvailableItems(res.data?.data || []))
                                                                    .finally(() => setFetchingItems(false));
                                                            } else {
                                                                setAvailableItems([]);
                                                            }
                                                        }}
                                                        className="inline-flex items-center gap-1 px-2.5 py-1.5 bg-gray-50 hover:bg-gray-100 text-gray-700 rounded-lg font-bold"
                                                    >
                                                        Edit Scope
                                                    </Button>
                                                    <Button
                                                        onClick={() => {
                                                            if (confirm('Are you sure you want to delete this schedule?')) {
                                                                axios.delete(`/api/v1/report-schedules/${sched.id}`)
                                                                    .then(() => {
                                                                        toast.success('Schedule deleted');
                                                                        router.reload();
                                                                    });
                                                            }
                                                        }}
                                                        className="inline-flex items-center gap-1 px-2.5 py-1.5 bg-red-50 hover:bg--100 text--700 rounded-lg font-bold"
                                                    >
                                                        Delete
                                                    </Button>
                                                </div>
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
                            <Button onClick={() => setEmailModalReport(null)} className="text-gray-400 hover:text-gray-700">
                                <Plus className="rotate-45" size={18} />
                            </Button>
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
                                <Button
                                    type="button"
                                    onClick={() => setEmailModalReport(null)}
                                    className="px-3.5 py-2 border border-gray-200 rounded-xl text-xs font-semibold text-gray-600 hover:bg-gray-50"
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    className="transition-all shadow-md" 
                                size="sm" >
                                    Send Report
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
            {/* Share Link Modal */}
            {shareModalReport && (
                <div className="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="bg-white rounded-2xl p-6 w-full max-w-md shadow-2xl border border-gray-100">
                        <div className="flex justify-between items-center mb-4">
                            <h3 className="text-sm font-bold text-gray-900">Share Report</h3>
                            <Button onClick={() => setShareModalReport(null)} className="text-gray-400 hover:text-gray-700">
                                <Plus className="rotate-45" size={18} />
                            </Button>
                        </div>
                        
                        <p className="text-xs text-gray-500 mb-6">
                            Create a public link to share <strong>{shareModalReport.title}</strong> with external clients or stakeholders. 
                            Anyone with this link can view and download the PDF.
                        </p>

                        <div className="bg-gray-50 rounded-xl p-4 border border-gray-100 space-y-4 mb-6">
                            <div className="flex items-center justify-between">
                                <div className="font-semibold text-sm text-gray-800">Public Link</div>
                                <label className="relative inline-flex items-center cursor-pointer">
                                    <input 
                                        type="checkbox" 
                                        className="sr-only peer" 
                                        checked={shareModalReport.is_public || false}
                                        onChange={(e) => {
                                            const isPublic = e.target.checked;
                                            axios.patch(`/api/v1/reports/${shareModalReport.id}/share`, { is_public: isPublic })
                                                .then((res) => {
                                                    setShareModalReport({ ...shareModalReport, is_public: res.data.data.is_public, share_token: res.data.data.share_token });
                                                    toast.success(isPublic ? 'Public link generated' : 'Public access revoked');
                                                    router.reload();
                                                })
                                                .catch(() => toast.error('Failed to update sharing settings'));
                                        }}
                                    />
                                    <div className="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-500"></div>
                                </label>
                            </div>

                            {shareModalReport.is_public && shareModalReport.share_token && (
                                <div className="space-y-2 pt-2 border-t border-gray-200">
                                    <div className="text-[10px] uppercase font-bold tracking-wider text-gray-400">Shareable URL</div>
                                    <div className="flex gap-2">
                                        <input 
                                            readOnly 
                                            value={`${window.location.origin}/shared/reports/${shareModalReport.share_token}`}
                                            className="w-full text-xs font-mono bg-white border border-gray-200 rounded-lg px-3 py-2 text-gray-600 focus:outline-none"
                                            onClick={e => e.currentTarget.select()}
                                        />
                                        <Button 
                                            onClick={() => {
                                                navigator.clipboard.writeText(`${window.location.origin}/shared/reports/${shareModalReport.share_token}`);
                                                toast.success('Link copied to clipboard');
                                            }}
                                            className="bg-gray-800 text-white px-3 py-2 rounded-lg text-xs font-bold hover:bg-gray-700 transition-colors"
                                        >
                                            Copy
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </div>

                        <div className="flex justify-end pt-2">
                            <Button
                                type="button"
                                onClick={() => setShareModalReport(null)}
                                className="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-xl transition-all"
                            >
                                Done
                            </Button>
                        </div>
                    </div>
                </div>
            )}

            {/* Edit Schedule Modal */}
            {editSchedule && (
                <div className="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="bg-white rounded-2xl p-6 w-full max-w-md shadow-2xl border border-gray-100">
                        <div className="flex justify-between items-center mb-4">
                            <h3 className="text-sm font-bold text-gray-900">Edit Scope: {editSchedule.title}</h3>
                            <Button onClick={() => setEditSchedule(null)} className="text-gray-400 hover:text-gray-700">
                                <Plus className="rotate-45" size={18} />
                            </Button>
                        </div>
                        
                        {!editSchedule.client_id && !editSchedule.project_id && (
                            <p className="text-sm text-gray-600 mb-4">This schedule includes all data across the organization. No specific scope to edit.</p>
                        )}
                        
                        {(editSchedule.client_id || editSchedule.project_id) && (
                            <div className="mb-4">
                                <label className="block text-xs font-bold text-gray-600 mb-2">
                                    {editSchedule.client_id && !editSchedule.project_id ? "Select Campaigns/Projects" : "Select Tasks"}
                                </label>
                                <p className="text-xs text-gray-500 mb-3">Leave empty to include all.</p>
                                
                                {fetchingItems ? (
                                    <p className="text-sm text-gray-500">Loading...</p>
                                ) : availableItems.length > 0 ? (
                                    <div className="space-y-2 max-h-60 overflow-y-auto">
                                        {availableItems.map(item => {
                                            const isProject = editSchedule.client_id && !editSchedule.project_id;
                                            const checked = isProject 
                                                ? scheduleConfig.selected_projects.includes(item.id)
                                                : scheduleConfig.selected_tasks.includes(item.id);
                                            
                                            return (
                                                <label key={item.id} className="flex items-center gap-2 p-2 border border-gray-100 rounded-lg hover:bg-gray-50 cursor-pointer">
                                                    <input 
                                                        type="checkbox" 
                                                        checked={checked}
                                                        onChange={e => {
                                                            if (isProject) {
                                                                const updated = e.target.checked 
                                                                    ? [...scheduleConfig.selected_projects, item.id] 
                                                                    : scheduleConfig.selected_projects.filter(id => id !== item.id);
                                                                setScheduleConfig({ ...scheduleConfig, selected_projects: updated });
                                                            } else {
                                                                const updated = e.target.checked 
                                                                    ? [...scheduleConfig.selected_tasks, item.id] 
                                                                    : scheduleConfig.selected_tasks.filter(id => id !== item.id);
                                                                setScheduleConfig({ ...scheduleConfig, selected_tasks: updated });
                                                            }
                                                        }}
                                                        className="rounded text-indigo-600 focus:ring-indigo-500" 
                                                    />
                                                    <span className="text-sm font-medium text-gray-800">{item.name || item.title}</span>
                                                </label>
                                            );
                                        })}
                                    </div>
                                ) : (
                                    <p className="text-sm text-gray-500">No items found.</p>
                                )}
                            </div>
                        )}
                        
                        <div className="flex justify-end gap-2 pt-2 border-t border-gray-100 mt-4 pt-4">
                            <Button
                                type="button"
                                onClick={() => setEditSchedule(null)}
                                className="px-3.5 py-2 border border-gray-200 rounded-xl text-xs font-semibold text-gray-600 hover:bg-gray-50"
                            >
                                Cancel
                            </Button>
                            <Button
                                type="button"
                                onClick={() => {
                                    axios.patch(`/api/v1/report-schedules/${editSchedule.id}`, {
                                        config: {
                                            ...editSchedule.config,
                                            selected_projects: scheduleConfig.selected_projects,
                                            selected_tasks: scheduleConfig.selected_tasks
                                        }
                                    }).then(() => {
                                        toast.success('Scope updated successfully.');
                                        setEditSchedule(null);
                                        router.reload();
                                    }).catch(err => {
                                        toast.error(err.response?.data?.message || 'Failed to update scope.');
                                    });
                                }}
                                disabled={!editSchedule.client_id && !editSchedule.project_id}
                                className="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-xs font-semibold rounded-xl transition-all shadow-md"
                            >
                                Save Changes
                            </Button>
                        </div>
                    </div>
                </div>
            )}
            {/* Floating Compare Action Bar */}
            {compareMode && selectedForCompare.length > 0 && (
                <div className="fixed bottom-6 left-1/2 -translate-x-1/2 bg-gray-900 text-white px-6 py-4 rounded-2xl shadow-2xl flex items-center gap-6 z-40 border border-gray-700/50 backdrop-blur-lg">
                    <div className="text-sm font-bold">
                        {selectedForCompare.length} selected <span className="text-gray-400 font-normal">/ 2 required</span>
                    </div>
                    <div className="flex items-center gap-3">
                        <Button 
                            onClick={() => {
                                setCompareMode(false);
                                setSelectedForCompare([]);
                            }}
                            className="text-gray-300 hover:text-white text-xs font-semibold px-2 py-1"
                        >
                            Cancel
                        </Button>
                        <Button
                            disabled={selectedForCompare.length !== 2}
                            onClick={() => router.get(`/internal-reports/compare?id1=${selectedForCompare[0]}&id2=${selectedForCompare[1]}`)}
                            className="px-4 py-2 bg-indigo-500 hover:bg-indigo-400 disabled:bg-gray-700 disabled:text-gray-500 text-white rounded-xl text-xs font-bold transition-colors"
                        >
                            Compare Side-by-Side
                        </Button>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}

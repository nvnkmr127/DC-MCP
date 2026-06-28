import React, { useState, useEffect } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { formatHours, timeAgo, getInitials, cn } from '@/lib/utils';
import {
    CheckSquare, Clock, AlertTriangle, TrendingUp, TrendingDown,
    FolderKanban, Users, ArrowRight, Zap, Minus, LayoutGrid, Edit3, Save, Plus, X, RefreshCw, Share2, Printer, Calendar, FileCheck, CheckCircle2
} from 'lucide-react';
import {
    AreaChart, Area, BarChart, Bar, LineChart, Line, PieChart, Pie, Cell,
    XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer
} from 'recharts';
import {
    useDashboardsQuery,
    useDashboardDataQuery,
    useSaveDashboardMutation,
    useQueryWidgetDataMutation,
    useShareDashboardMutation,
    DashboardConfig,
    Widget
} from '@/hooks/queries/useDashboards';
import { Button } from '@/Components/ui/Button';
import { Skeleton } from '@/Components/ui/Skeleton';

interface DashboardStats {
    my_active_tasks: number;
    my_overdue_tasks: number;
    my_hours_this_week: number;
    active_projects: number;
    team_overdue_tasks: number;
    tasks_by_status: Record<string, number>;
    recent_activity: Array<{
        id: string;
        action: string;
        comment: string;
        logged_at: string;
        actor_name: string;
        task_title: string;
    }>;
}

interface Props {
    stats: DashboardStats;
    briefing?: { id: string; date: string; digest_text: string | null; status: string } | null;
    setup_checklist: Array<{ id: string; title: string; done: boolean; href: string | null }>;
    overdue_tasks_list?: Array<{ id: string; title: string; due_date: string; status: string; project?: { name: string }; assignee?: { name: string } }>;
    today_calendar?: Array<{ id: string; title: string; date: string; status: string; priority: string; type: string; project?: { name: string }; url: string }>;
    pending_approvals?: Array<{ id: string; title: string; status: string; project?: { id: string; name: string }; client?: { name: string }; submitter?: { name: string }; created_at: string }>;
}

const PIE_COLORS = ['#6366f1', '#a855f7', '#ec4899', '#3b82f6', '#10b981', '#f59e0b'];

export default function DashboardIndex({ stats, briefing, setup_checklist, overdue_tasks_list = [], today_calendar = [], pending_approvals = [] }: Props) {
    const { data: dashboards, isLoading: isLoadingConfigs } = useDashboardsQuery();
    const [editMode, setEditMode] = useState(false);
    const [localLayout, setLocalLayout] = useState<Widget[]>([]);
    const [activeDb, setActiveDb] = useState<DashboardConfig | null>(null);
    const [rangePreset, setRangePreset] = useState('last_30_days');
    const [shareModalOpen, setShareModalOpen] = useState(false);

    const getDateRange = (range: string) => {
        const today = new Date();
        const to = today.toISOString().split('T')[0];
        let from = to;
        if (range === 'last_7_days') {
            const d = new Date(); d.setDate(d.getDate() - 7);
            from = d.toISOString().split('T')[0];
        } else if (range === 'last_30_days') {
            const d = new Date(); d.setDate(d.getDate() - 30);
            from = d.toISOString().split('T')[0];
        } else if (range === 'last_quarter') {
            const d = new Date(); d.setDate(d.getDate() - 90);
            from = d.toISOString().split('T')[0];
        } else if (range === 'year_to_date') {
            const d = new Date(today.getFullYear(), 0, 1);
            from = d.toISOString().split('T')[0];
        }
        return { from, to };
    };

    const dateRange = getDateRange(rangePreset);

    // Sync activeDb and localLayout when dashboards query completes
    useEffect(() => {
        if (dashboards && dashboards.length > 0 && !activeDb) {
            const firstDb = dashboards[0];
            setActiveDb(firstDb);
            setLocalLayout(firstDb.layout);
        }
    }, [dashboards]);

    const { data: widgetsData, isLoading: isLoadingData, refetch: refetchData } = useDashboardDataQuery(
        activeDb?.id,
        !editMode,
        dateRange
    );

    const saveMutation = useSaveDashboardMutation();
    const queryWidgetMutation = useQueryWidgetDataMutation();
    const shareMutation = useShareDashboardMutation();

    const [localWidgetsData, setLocalWidgetsData] = useState<Record<string, any>>({});

    // Sync local widgets data when query succeeds
    useEffect(() => {
        if (widgetsData) {
            setLocalWidgetsData(widgetsData);
        }
    }, [widgetsData]);

    const greeting = () => {
        const hr = new Date().getHours();
        return hr < 12 ? 'Good morning' : hr < 17 ? 'Good afternoon' : 'Good evening';
    };

    const handleSaveLayout = () => {
        if (!activeDb) return;
        saveMutation.mutate({ id: activeDb.id, layout: localLayout }, {
            onSuccess: () => {
                setEditMode(false);
            }
        });
    };

    const handleRemoveWidget = (widgetId: string) => {
        setLocalLayout(prev => prev.filter(w => w.id !== widgetId));
    };

    const handleAddWidget = () => {
        const newId = `custom-w-${Date.now()}`;
        const newWidget: Widget = {
            id: newId,
            title: 'Ad Spend Performance',
            type: 'line_chart',
            spec: {
                metric_key: 'meta_ads_spend',
                aggregation: 'sum',
                group_by: 'day',
                filters: {}
            },
            position: { x: 0, y: 0, w: 6, h: 4 }
        };
        setLocalLayout(prev => [...prev, newWidget]);
        
        queryWidgetMutation.mutate(newWidget.spec, {
            onSuccess: (data) => {
                setLocalWidgetsData(prev => ({ ...prev, [newId]: data }));
            }
        });
    };

    const handleShareToggle = (enabled: boolean) => {
        if (!activeDb) return;
        shareMutation.mutate({ id: activeDb.id, enabled });
    };

    const handleCopyShareLink = () => {
        if (activeDb?.share_token) {
            const url = `${window.location.origin}/public/dashboards/${activeDb.share_token}`;
            navigator.clipboard.writeText(url);
            alert('Share link copied to clipboard!');
        }
    };

    const handlePrint = () => {
        window.print();
    };

    const isConfigsLoading = isLoadingConfigs || saveMutation.isPending;

    // Build visual representation of current config/layout
    const dashboardConfig = activeDb ? { ...activeDb, layout: localLayout } : null;
    const resolvedWidgetsData = localWidgetsData;

    const totalTasks = Object.values(stats.tasks_by_status).reduce((a, b) => a + b, 0);
    const isCompletelyEmpty = stats.active_projects === 0 && totalTasks === 0;

    return (
        <AppLayout title={`${greeting()}, Team 👋`}>
            <Head title="Dashboard" />

            {/* Premium Header Actions */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6 border-b border-gray-100/60 pb-4">
                <div>
                    <h1 className="text-xl font-extrabold text-gray-900 tracking-tight">Performance Desk</h1>
                    <p className="text-xs text-gray-400 mt-1 font-medium">Auto-synced multi-tenant KPI controls.</p>
                </div>
                <div className="flex items-center gap-2">
                    {dashboardConfig && (
                        <>
                            {editMode ? (
                                <>
                                    <Button
                                        onClick={handleAddWidget}
                                        variant="outline"
                                        size="sm"
                                        
                                    size="icon" >
                                        <Plus size={13} className="mr-1.5" /> Add Widget
                                    </Button>
                                    <Button
                                        onClick={handleSaveLayout}
                                        size="sm"
                                        loading={saveMutation.isPending}
                                    >
                                        <Save size={13} className="mr-1.5" /> Save Dashboard
                                    </Button>
                                </>
                            ) : (
                                <Button
                                    onClick={() => setEditMode(true)}
                                    variant="outline"
                                    size="sm"
                                >
                                    <Edit3 size={13} className="mr-1.5" /> Edit Layout
                                </Button>
                            )}
                            <select
                                className="text-sm border-gray-200 rounded-lg py-1.5 pl-3 pr-8 focus:ring-indigo-500 focus:border-indigo-500"
                                value={rangePreset}
                                onChange={(e) => setRangePreset(e.target.value)}
                            >
                                <option value="last_7_days">Last 7 Days</option>
                                <option value="last_30_days">Last 30 Days</option>
                                <option value="last_quarter">Last Quarter</option>
                                <option value="year_to_date">Year to Date</option>
                            </select>
                            
                            <div className="h-4 border-r border-gray-200 mx-1"></div>
                            
                            <Button
                                onClick={() => setShareModalOpen(true)}
                                variant="outline"
                                size="sm"
                            >
                                <Share2 size={13} className="mr-1.5" /> Share
                            </Button>
                            
                            <Button
                                onClick={handlePrint}
                                variant="outline"
                                size="sm"
                            >
                                <Printer size={13} className="mr-1.5" /> Print PDF
                            </Button>

                            <Button
                                onClick={() => refetchData()}
                                variant="outline"
                                size="sm"
                                className="p-2 aspect-square rounded-xl flex items-center justify-center text-gray-400 hover:text-gray-700"
                                title="Refresh data"
                            >
                                <RefreshCw size={13} className={cn(isLoadingData && "animate-spin")} />
                            </Button>
                        </>
                    )}
                </div>
            </div>

            {/* Setup Checklist / Empty State CTA */}
            {setup_checklist && setup_checklist.some(i => !i.done) && (
                <div className="mb-8 bg-gradient-to-r from-indigo-50 to-blue-50 border border-indigo-100 rounded-2xl p-6 shadow-sm print:hidden">
                    <div className="flex items-center justify-between mb-4">
                        <h2 className="text-lg font-bold text-gray-900 tracking-tight flex items-center">
                            <CheckSquare className="mr-2 text-indigo-600" size={20} />
                            Setup Checklist
                        </h2>
                        <span className="text-sm font-semibold text-indigo-700 bg-indigo-100 px-3 py-1 rounded-full">
                            {setup_checklist.filter(i => i.done).length} of {setup_checklist.length} steps complete
                        </span>
                    </div>
                    <div className="bg-white rounded-xl shadow-sm border border-indigo-50 overflow-hidden">
                        <div className="divide-y divide-gray-50">
                            {setup_checklist.map((item, idx) => (
                                <div key={item.id} className="p-4 flex items-center justify-between hover:bg-gray-50 transition-colors">
                                    <div className="flex items-center gap-3">
                                        <div className={cn("w-6 h-6 rounded-full flex items-center justify-center shrink-0 border", item.done ? "bg-emerald-500 border-emerald-500 text-white" : "bg-gray-100 border-gray-300 text-transparent")}>
                                            {item.done && <CheckSquare size={12} className="text-white" />}
                                        </div>
                                        <span className={cn("text-sm font-medium", item.done ? "text-gray-400 line-through" : "text-gray-900")}>
                                            {item.title}
                                        </span>
                                    </div>
                                    {!item.done && item.href && (
                                        <Link href={item.href} className="text-xs font-semibold text-indigo-600 hover:text-indigo-700 bg-indigo-50 px-3 py-1.5 rounded-lg transition-colors">
                                            Complete task
                                        </Link>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            )}

            {/* Loading / Custom layout view */}
            {isConfigsLoading ? (
                <>
                    {/* KPI Cards Skeleton */}
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-5 mb-6">
                        {[1, 2, 3, 4].map(i => (
                            <div key={i} className="bg-white rounded-2xl p-5 border border-gray-100 shadow-[0_1px_3px_rgba(0,0,0,0.02)]">
                                <Skeleton className="w-9 h-9 rounded-xl mb-4" />
                                <Skeleton className="h-8 w-16 mb-2" />
                                <Skeleton className="h-3 w-24" />
                            </div>
                        ))}
                    </div>
                    {/* Command Center Skeleton */}
                    <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-6">
                        <div className="lg:col-span-8 flex flex-col gap-6">
                            <Skeleton className="h-[220px] rounded-2xl w-full border border-gray-100" />
                            <Skeleton className="h-[300px] rounded-2xl w-full border border-gray-100" />
                        </div>
                        <div className="lg:col-span-4 flex flex-col gap-6">
                            <Skeleton className="h-[320px] rounded-2xl w-full border border-gray-100" />
                            <Skeleton className="h-[320px] rounded-2xl w-full border border-gray-100" />
                        </div>
                    </div>
                </>
            ) : isCompletelyEmpty ? (
                <div className="flex flex-col items-center justify-center bg-white rounded-[2rem] border border-gray-100/80 shadow-sm p-16 mt-6 relative overflow-hidden min-h-[500px]">
                    <div className="absolute top-0 left-0 w-full h-full pointer-events-none">
                        <div className="absolute -top-[30%] -left-[10%] w-[60%] h-[60%] bg-indigo-500/5 blur-[100px] rounded-full"></div>
                        <div className="absolute top-[50%] -right-[20%] w-[50%] h-[70%] bg-violet-500/5 blur-[100px] rounded-full"></div>
                    </div>
                    
                    <div className="w-28 h-28 mb-8 rounded-[2rem] bg-gradient-to-tr from-indigo-500 via-purple-500 to-violet-500 flex items-center justify-center shadow-[0_8px_30px_rgba(99,102,241,0.3)] text-white transform -rotate-6 transition-transform hover:rotate-0 duration-500">
                        <FolderKanban size={48} className="transform rotate-6 hover:rotate-0 transition-transform duration-500" />
                    </div>
                    
                    <h2 className="text-3xl font-extrabold text-gray-900 mb-4 tracking-tight z-10 text-center">
                        Welcome to your workspace
                    </h2>
                    <p className="text-gray-500 max-w-lg text-center mb-10 z-10 leading-relaxed text-sm md:text-base">
                        You don't have any projects or tasks set up yet. Start by creating a fresh project, or save time by importing your data from tools like Jira, Trello, or FreshBooks.
                    </p>
                    
                    <div className="flex flex-col sm:flex-row items-center gap-4 z-10">
                        <Link 
                            href="/projects/create" 
                            className="flex items-center justify-center gap-2 bg-indigo-600 text-white px-8 py-3.5 rounded-xl font-semibold hover:bg-indigo-700 transition-all shadow-[0_4px_14px_0_rgba(79,70,229,0.39)] hover:shadow-[0_6px_20px_rgba(79,70,229,0.23)] hover:-translate-y-0.5 w-full sm:w-auto"
                        >
                            <Plus size={18} />
                            Create Project
                        </Link>
                        <Link 
                            href="/settings/import" 
                            className="flex items-center justify-center gap-2 bg-white text-gray-700 border border-gray-200 px-8 py-3.5 rounded-xl font-semibold hover:bg-gray-50 hover:border-gray-300 transition-all shadow-sm hover:shadow-md w-full sm:w-auto hover:-translate-y-0.5"
                        >
                            <RefreshCw size={18} className="text-gray-400" />
                            Import Data
                        </Link>
                    </div>
                </div>
            ) : dashboardConfig && dashboardConfig.layout && dashboardConfig.layout.length > 0 ? (
                <div className="grid grid-cols-1 md:grid-cols-6 gap-6 mb-6">
                    {dashboardConfig.layout.map((widget: any) => {
                        const data = localWidgetsData[widget.id];
                        const spanCls = widget.position.w >= 6 ? 'md:col-span-6' : 'md:col-span-3';

                        return (
                            <div
                                key={widget.id}
                                className={cn(
                                    "bg-white border border-gray-100 rounded-2xl p-6 shadow-[0_1px_3px_rgba(0,0,0,0.02)] hover:shadow-[0_8px_30px_rgba(0,0,0,0.04)] transition-all duration-300 relative group",
                                    spanCls,
                                    editMode && "border-dashed border-indigo-400 bg-indigo-50/5"
                                )}
                            >
                                {editMode && (
                                    <Button
                                        onClick={() => handleRemoveWidget(widget.id)}
                                        className="absolute top-3 right-3 p-1 rounded-lg bg--50 text--700 hover:bg-red-100 opacity-0 group-hover:opacity-100 transition-opacity z-10"
                                    >
                                        <X size={12} />
                                    </Button>
                                )}

                                <div className="flex items-center justify-between mb-4">
                                    <h3 className="text-[13px] font-bold text-gray-900 tracking-wide">{widget.title}</h3>
                                    {data?.meta?.unit && (
                                        <span className="text-[10px] uppercase font-bold text-indigo-500/80 bg-indigo-50 px-2 py-0.5 rounded-md">
                                            {data.meta.unit}
                                        </span>
                                    )}
                                </div>

                                {isLoadingData && !data ? (
                                    <div className="w-full flex flex-col justify-center gap-4 py-2">
                                        {widget.type === 'metric_card' ? (
                                            <div className="flex items-end gap-2">
                                                <Skeleton className="h-9 w-20" />
                                                <Skeleton className="h-4 w-10 mb-1" />
                                            </div>
                                        ) : (
                                            <Skeleton className="h-[200px] w-full rounded-xl" />
                                        )}
                                    </div>
                                ) : widget.type === 'metric_card' ? (
                                    <div className="flex items-baseline gap-2">
                                        <span className="text-3xl font-extrabold text-gray-900 tracking-tight tabular-nums">
                                            {data?.summary?.total ?? 0}
                                        </span>
                                        <span className="text-[11px] font-semibold text-gray-400">Total</span>
                                    </div>
                                ) : widget.type === 'line_chart' && data?.data ? (
                                    <div className="h-[200px]">
                                        <ResponsiveContainer width="100%" height="100%">
                                            <AreaChart data={data.data}>
                                                <defs>
                                                    <linearGradient id={`grad-${widget.id}`} x1="0" y1="0" x2="0" y2="1">
                                                        <stop offset="5%" stopColor="#6366f1" stopOpacity={0.2} />
                                                        <stop offset="95%" stopColor="#6366f1" stopOpacity={0} />
                                                    </linearGradient>
                                                </defs>
                                                <CartesianGrid strokeDasharray="3 3" stroke="#f8fafc" vertical={false} />
                                                <XAxis dataKey="label" tick={{ fontSize: 10, fill: '#94a3b8' }} axisLine={false} tickLine={false} />
                                                <YAxis tick={{ fontSize: 10, fill: '#94a3b8' }} axisLine={false} tickLine={false} />
                                                <Tooltip contentStyle={{ borderRadius: '12px', border: '1px solid #f1f5f9', boxShadow: '0 8px 30px rgba(0,0,0,0.06)' }} />
                                                <Area type="monotone" dataKey="value" stroke="#6366f1" strokeWidth={2.5} fill={`url(#grad-${widget.id})`} />
                                            </AreaChart>
                                        </ResponsiveContainer>
                                    </div>
                                ) : (
                                    <div className="text-xs text-gray-400 py-4 text-center">No visualization available or loading failed.</div>
                                )}
                            </div>
                        );
                    })}
                </div>
            ) : (
                /* Fallback clean static dashboard layout if config not present */
                <>
                    {/* KPI Cards */}
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-5 mb-6">
                        {[
                            { label: 'Active Tasks', value: stats.my_active_tasks, icon: CheckSquare, color: 'text-indigo-600', bg: 'bg-indigo-50/50', border: 'border-indigo-100/60' },
                            { label: 'Overdue Tasks', value: stats.my_overdue_tasks, icon: AlertTriangle, color: 'text-rose-600', bg: 'bg-rose-50/50', border: 'border-rose-100/60', urgent: stats.my_overdue_tasks > 0 },
                            { label: 'Hours Tracked', value: `${stats.my_hours_this_week}h`, icon: Clock, color: 'text-sky-600', bg: 'bg-sky-50/50', border: 'border-sky-100/60' },
                            { label: 'Active Campaigns', value: stats.active_projects, icon: FolderKanban, color: 'text-emerald-600', bg: 'bg-emerald-50/50', border: 'border-emerald-100/60' }
                        ].map((kpi) => {
                            const Icon = kpi.icon;
                            return (
                                <div key={kpi.label} className={cn(
                                    "bg-white rounded-2xl p-5 border shadow-[0_1px_3px_rgba(0,0,0,0.02)] transition-all duration-300 hover:-translate-y-0.5",
                                    kpi.urgent ? "border-rose-100 hover:border-rose-200" : "border-gray-100/80 hover:border-gray-200/80"
                                )}>
                                    <div className="flex items-center justify-between mb-4">
                                        <div className={cn("w-9 h-9 rounded-xl flex items-center justify-center", kpi.bg)}>
                                            <Icon size={16} className={kpi.color} />
                                        </div>
                                    </div>
                                    <h3 className="text-2xl font-extrabold text-gray-900 tracking-tight tabular-nums">{kpi.value}</h3>
                                    <p className="text-[11px] text-gray-400 mt-1 font-semibold uppercase tracking-wider">{kpi.label}</p>
                                </div>
                            );
                        })}
                    </div>

                    {/* Command Center: Start-of-Day Flow */}
                    <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-6">
                        
                        {/* Daily Briefing & Agenda (Left Column, takes 8 cols) */}
                        <div className="lg:col-span-8 flex flex-col gap-6">
                            
                            {/* Morning Briefing */}
                            <div className="bg-gradient-to-br from-indigo-50 to-white rounded-2xl border border-indigo-100 p-6 shadow-sm relative overflow-hidden">
                                <div className="absolute top-0 right-0 p-8 opacity-5">
                                    <Zap size={100} />
                                </div>
                                <div className="flex items-center justify-between mb-4 relative z-10">
                                    <div className="flex items-center gap-3">
                                        <div className="w-10 h-10 rounded-xl bg-indigo-600 flex items-center justify-center text-white shadow-md">
                                            <Zap size={20} className="fill-indigo-400" />
                                        </div>
                                        <div>
                                            <h3 className="text-base font-bold text-gray-900 tracking-tight">Morning Briefing</h3>
                                            <p className="text-xs font-medium text-indigo-600/80">AI-powered daily digest</p>
                                        </div>
                                    </div>
                                    <Link href="/briefings" className="text-xs font-semibold text-indigo-600 hover:text-indigo-700 bg-white px-3 py-1.5 rounded-lg border border-indigo-100 shadow-sm transition-all hover:shadow">
                                        View Full Digest
                                    </Link>
                                </div>
                                
                                <div className="relative z-10">
                                    {briefing?.digest_text ? (
                                        <div className="prose prose-sm prose-indigo max-w-none text-gray-700 leading-relaxed font-medium">
                                            {briefing.digest_text}
                                        </div>
                                    ) : (
                                        <div className="py-6 text-center bg-white/60 backdrop-blur rounded-xl border border-white/40 border-dashed">
                                            <p className="text-sm text-gray-500 mb-4">You have no briefing compiled for today.</p>
                                            <Link
                                                href="/briefings/generate"
                                                method="post"
                                                as="button"
                                                className="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors shadow-md shadow-indigo-200 inline-flex items-center gap-2"
                                            >
                                                <Zap size={16} /> Generate Briefing
                                            </Link>
                                        </div>
                                    )}
                                </div>
                            </div>

                            {/* Today's Calendar */}
                            <div className="bg-white rounded-2xl border border-gray-100/80 shadow-[0_1px_3px_rgba(0,0,0,0.02)] flex flex-col flex-1">
                                <div className="p-5 border-b border-gray-50 flex items-center gap-2">
                                    <div className="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                                        <Calendar size={16} />
                                    </div>
                                    <h3 className="text-sm font-bold text-gray-900 tracking-wide">Today's Agenda</h3>
                                </div>
                                <div className="p-5">
                                    {today_calendar.length > 0 ? (
                                        <div className="space-y-3">
                                            {today_calendar.map(item => (
                                                <div key={item.id} className="flex items-center gap-4 p-3 rounded-xl hover:bg-gray-50 transition-colors border border-transparent hover:border-gray-100">
                                                    <div className="w-1.5 h-10 rounded-full bg-emerald-500 shrink-0"></div>
                                                    <div className="flex-1 min-w-0">
                                                        <Link href={item.url} className="text-sm font-bold text-gray-900 hover:text-emerald-600 truncate block transition-colors">
                                                            {item.title}
                                                        </Link>
                                                        <div className="flex items-center gap-3 mt-1 text-xs font-medium">
                                                            <span className="text-gray-500 uppercase tracking-wider">{item.type}</span>
                                                            {item.project && (
                                                                <>
                                                                    <span className="text-gray-300">•</span>
                                                                    <span className="text-gray-500 truncate">{item.project.name}</span>
                                                                </>
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    ) : (
                                        <div className="py-12 flex flex-col items-center justify-center text-center">
                                            <Calendar size={32} className="text-gray-200 mb-3" />
                                            <p className="text-sm font-medium text-gray-900 mb-1">Your calendar is clear.</p>
                                            <p className="text-xs text-gray-500 max-w-[200px]">New events and meetings will appear here.</p>
                                        </div>
                                    )}
                                </div>
                            </div>

                        </div>

                        {/* Action Items (Right Column, takes 4 cols) */}
                        <div className="lg:col-span-4 flex flex-col gap-6">
                            
                            {/* Overdue Tasks */}
                            <div className="bg-white rounded-2xl border border-rose-100/60 shadow-[0_4px_20px_rgba(225,29,72,0.03)] flex flex-col h-[320px]">
                                <div className="p-5 border-b border-rose-50 flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <div className="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center">
                                            <AlertTriangle size={16} />
                                        </div>
                                        <h3 className="text-sm font-bold text-gray-900 tracking-wide">Overdue Tasks</h3>
                                    </div>
                                    {overdue_tasks_list.length > 0 && (
                                        <span className="text-[10px] font-bold text-rose-600 bg-rose-100 px-2.5 py-1 rounded-md">
                                            {overdue_tasks_list.length}
                                        </span>
                                    )}
                                </div>
                                <div className="p-4 overflow-y-auto flex-1">
                                    {overdue_tasks_list.length > 0 ? (
                                        <div className="space-y-3">
                                            {overdue_tasks_list.map(task => (
                                                <div key={task.id} className="p-3 bg-rose-50/30 rounded-xl border border-rose-100/50">
                                                    <Link href={`/tasks/${task.id}`} className="text-sm font-semibold text-gray-900 hover:text-rose-600 block mb-1">
                                                        {task.title}
                                                    </Link>
                                                    <div className="flex items-center justify-between text-[11px] font-medium text-gray-500">
                                                        <span className="text-rose-600 bg-rose-50 px-1.5 py-0.5 rounded">{new Date(task.due_date).toLocaleDateString()}</span>
                                                        <span className="truncate max-w-[120px]">{task.project?.name}</span>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    ) : (
                                        <div className="h-full flex flex-col items-center justify-center text-center">
                                            <CheckCircle2 size={32} className="text-emerald-300 mb-3" />
                                            <p className="text-sm font-medium text-gray-900 mb-1">No overdue tasks!</p>
                                            <p className="text-xs text-gray-500 max-w-[200px]">Check with your manager or browse available projects.</p>
                                        </div>
                                    )}
                                </div>
                            </div>

                            {/* Pending Approvals */}
                            <div className="bg-white rounded-2xl border border-amber-100/60 shadow-[0_4px_20px_rgba(217,119,6,0.03)] flex flex-col flex-1 min-h-[320px]">
                                <div className="p-5 border-b border-amber-50 flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <div className="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center">
                                            <FileCheck size={16} />
                                        </div>
                                        <h3 className="text-sm font-bold text-gray-900 tracking-wide">Pending Approvals</h3>
                                    </div>
                                    {pending_approvals.length > 0 && (
                                        <span className="text-[10px] font-bold text-amber-700 bg-amber-100 px-2.5 py-1 rounded-md">
                                            {pending_approvals.length}
                                        </span>
                                    )}
                                </div>
                                <div className="p-4 overflow-y-auto flex-1">
                                    {pending_approvals.length > 0 ? (
                                        <div className="space-y-3">
                                            {pending_approvals.map(approval => (
                                                <div key={approval.id} className="p-3 bg-amber-50/30 rounded-xl border border-amber-100/50">
                                                    <div className="text-[10px] font-bold text-amber-600 uppercase tracking-wider mb-1">
                                                        {approval.project?.name || approval.client?.name || 'General Asset'}
                                                    </div>
                                                    <div className="text-sm font-semibold text-gray-900 mb-1.5">
                                                        {approval.title}
                                                    </div>
                                                    <div className="flex items-center justify-between">
                                                        <span className="text-[11px] font-medium text-gray-500">By {approval.submitter?.name}</span>
                                                        <Link href={`/projects/${approval.project?.id || ''}`} className="text-[11px] font-bold text-indigo-600 hover:text-indigo-700">
                                                            Review &rarr;
                                                        </Link>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    ) : (
                                        <div className="h-full flex flex-col items-center justify-center text-center">
                                            <FileCheck size={32} className="text-gray-200 mb-3" />
                                            <p className="text-sm font-medium text-gray-500">No pending approvals.</p>
                                        </div>
                                    )}
                                </div>
                            </div>

                        </div>
                    </div>
                </>
            )}

            {/* Share Modal */}
            {shareModalOpen && activeDb && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm print:hidden">
                    <div className="bg-white rounded-2xl shadow-xl border border-slate-100 w-full max-w-md overflow-hidden">
                        <div className="p-5 border-b border-slate-100 flex items-center justify-between">
                            <h2 className="text-lg font-bold text-slate-800 flex items-center">
                                <Share2 size={18} className="mr-2 text-indigo-500" /> Share Dashboard
                            </h2>
                            <Button onClick={() => setShareModalOpen(false)} className="text-slate-400 hover:text-slate-600">
                                <X size={18} />
                            </Button>
                        </div>
                        <div className="p-6">
                            <p className="text-sm text-slate-500 mb-6">
                                Create a read-only public link to share this dashboard with stakeholders. They will not need to log in to view the data.
                            </p>
                            
                            <div className="flex items-center justify-between mb-4 p-4 bg-slate-50 rounded-xl border border-slate-100">
                                <div>
                                    <h4 className="text-sm font-semibold text-slate-800">Public Link Access</h4>
                                    <p className="text-xs text-slate-500">Anyone with the link can view</p>
                                </div>
                                <label className="relative inline-flex items-center cursor-pointer">
                                    <input 
                                        type="checkbox" 
                                        className="sr-only peer" 
                                        checked={!!activeDb.share_token}
                                        onChange={(e) => handleShareToggle(e.target.checked)}
                                        disabled={shareMutation.isPending}
                                    />
                                    <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                                </label>
                            </div>

                            {activeDb.share_token && (
                                <div className="mt-4">
                                    <label className="block text-xs font-semibold text-slate-700 mb-1">Share URL</label>
                                    <div className="flex gap-2">
                                        <input 
                                            type="text" 
                                            readOnly 
                                            value={`${window.location.origin}/public/dashboards/${activeDb.share_token}`}
                                            className="flex-1 text-xs px-3 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-600 focus:outline-none"
                                        />
                                        <Button onClick={handleCopyShareLink} size="sm">Copy</Button>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}

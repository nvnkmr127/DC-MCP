import React, { useState, useEffect } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { formatHours, timeAgo, getInitials, cn } from '@/lib/utils';
import {
    CheckSquare, Clock, AlertTriangle, TrendingUp, TrendingDown,
    FolderKanban, Users, ArrowRight, Zap, Minus, LayoutGrid, Edit3, Save, Plus, X, RefreshCw
} from 'lucide-react';
import {
    AreaChart, Area, BarChart, Bar, LineChart, Line, PieChart, Pie, Cell,
    XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer
} from 'recharts';
import axios from 'axios';

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

interface Widget {
    id: string;
    title: string;
    type: 'metric_card' | 'line_chart' | 'bar_chart' | 'pie_chart';
    spec: {
        metric_key: string;
        aggregation: string;
        group_by?: string;
        filters?: Record<string, any>;
    };
    position: { x: number; y: number; w: number; h: number };
}

interface Props {
    stats: DashboardStats;
    briefing?: { id: string; date: string; digest_text: string | null; status: string } | null;
}

const PIE_COLORS = ['#6366f1', '#a855f7', '#ec4899', '#3b82f6', '#10b981', '#f59e0b'];

export default function DashboardIndex({ stats, briefing }: Props) {
    const [editMode, setEditMode] = useState(false);
    const [dashboardConfig, setDashboardConfig] = useState<any>(null);
    const [widgetsData, setWidgetsData] = useState<Record<string, any>>({});
    const [loading, setLoading] = useState(true);

    const greeting = () => {
        const hr = new Date().getHours();
        return hr < 12 ? 'Good morning' : hr < 17 ? 'Good afternoon' : 'Good evening';
    };

    // Fetch customizable dashboard configurations
    useEffect(() => {
        axios.get('/api/v1/dashboards')
            .then(res => {
                const activeDb = res.data.data?.[0];
                if (activeDb) {
                    setDashboardConfig(activeDb);
                    fetchDashboardData(activeDb.id);
                } else {
                    setLoading(false);
                }
            })
            .catch(() => {
                setLoading(false);
            });
    }, []);

    const fetchDashboardData = (id: string) => {
        setLoading(true);
        axios.get(`/api/v1/dashboards/${id}/data`)
            .then(res => {
                setWidgetsData(res.data.data?.widgets ?? {});
                setLoading(false);
            })
            .catch(() => {
                setLoading(false);
            });
    };

    const handleSaveLayout = () => {
        if (!dashboardConfig) return;
        axios.put(`/api/v1/dashboards/${dashboardConfig.id}`, {
            layout: dashboardConfig.layout
        })
        .then(() => {
            setEditMode(false);
        });
    };

    const handleRemoveWidget = (widgetId: string) => {
        if (!dashboardConfig) return;
        const newLayout = dashboardConfig.layout.filter((w: any) => w.id !== widgetId);
        setDashboardConfig({ ...dashboardConfig, layout: newLayout });
    };

    const handleAddWidget = () => {
        if (!dashboardConfig) return;
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
        const newLayout = [...dashboardConfig.layout, newWidget];
        setDashboardConfig({ ...dashboardConfig, layout: newLayout });
        
        // Fetch data for the new widget
        axios.post('/api/v1/viz/query', newWidget.spec)
            .then(res => {
                setWidgetsData(prev => ({ ...prev, [newId]: res.data.data }));
            });
    };

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
                                    <button
                                        onClick={handleAddWidget}
                                        className="flex items-center gap-1.5 px-3 py-1.5 bg-indigo-50 text-indigo-600 text-xs font-semibold rounded-xl hover:bg-indigo-100 transition-colors border border-indigo-100"
                                    >
                                        <Plus size={13} /> Add Widget
                                    </button>
                                    <button
                                        onClick={handleSaveLayout}
                                        className="flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 text-white text-xs font-semibold rounded-xl hover:bg-indigo-700 transition-colors shadow-md"
                                    >
                                        <Save size={13} /> Save Dashboard
                                    </button>
                                </>
                            ) : (
                                <button
                                    onClick={() => setEditMode(true)}
                                    className="flex items-center gap-1.5 px-3 py-1.5 bg-white text-gray-600 border border-gray-200 text-xs font-semibold rounded-xl hover:bg-gray-50 hover:text-gray-900 transition-colors"
                                >
                                    <Edit3 size={13} /> Edit Layout
                                </button>
                            )}
                            <button
                                onClick={() => fetchDashboardData(dashboardConfig.id)}
                                className="p-1.5 rounded-xl border border-gray-200 hover:bg-gray-50 text-gray-400 hover:text-gray-700 transition-colors"
                                title="Refresh data"
                            >
                                <RefreshCw size={13} />
                            </button>
                        </>
                    )}
                </div>
            </div>

            {/* Loading / Custom layout view */}
            {loading ? (
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    {[1, 2, 3].map(i => (
                        <div key={i} className="bg-white/80 border border-gray-100 rounded-2xl p-6 h-[200px] animate-pulse">
                            <div className="h-4 bg-gray-100 rounded-full w-24 mb-4"></div>
                            <div className="h-10 bg-gray-50 rounded-full w-12 mb-4"></div>
                            <div className="h-4 bg-gray-50 rounded-full w-full"></div>
                        </div>
                    ))}
                </div>
            ) : dashboardConfig && dashboardConfig.layout && dashboardConfig.layout.length > 0 ? (
                <div className="grid grid-cols-1 md:grid-cols-6 gap-6 mb-6">
                    {dashboardConfig.layout.map((widget: any) => {
                        const data = widgetsData[widget.id];
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
                                    <button
                                        onClick={() => handleRemoveWidget(widget.id)}
                                        className="absolute top-3 right-3 p-1 rounded-lg bg-red-50 text-red-500 hover:bg-red-100 opacity-0 group-hover:opacity-100 transition-opacity z-10"
                                    >
                                        <X size={12} />
                                    </button>
                                )}

                                <div className="flex items-center justify-between mb-4">
                                    <h3 className="text-[13px] font-bold text-gray-900 tracking-wide">{widget.title}</h3>
                                    {data?.meta?.unit && (
                                        <span className="text-[10px] uppercase font-bold text-indigo-500/80 bg-indigo-50 px-2 py-0.5 rounded-md">
                                            {data.meta.unit}
                                        </span>
                                    )}
                                </div>

                                {widget.type === 'metric_card' ? (
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

                    {/* Dashboard grid columns */}
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        {/* Daily Briefing summary */}
                        <div className="bg-white rounded-2xl border border-gray-100/80 p-6 shadow-[0_1px_3px_rgba(0,0,0,0.02)]">
                            <div className="flex items-center justify-between mb-5 border-b border-gray-50 pb-3">
                                <div className="flex items-center gap-2">
                                    <div className="w-7 h-7 rounded-lg bg-yellow-50 flex items-center justify-center text-yellow-500 shadow-[0_0_12px_rgba(234,179,8,0.15)]">
                                        <Zap size={14} className="fill-yellow-500/20" />
                                    </div>
                                    <h3 className="text-[13px] font-bold text-gray-900">Morning Assistant</h3>
                                </div>
                                <Link href="/briefings" className="text-[11px] font-semibold text-indigo-600 hover:text-indigo-700">View digest</Link>
                            </div>
                            
                            {briefing?.digest_text ? (
                                <p className="text-[12px] text-gray-600 leading-relaxed font-normal">{briefing.digest_text}</p>
                            ) : (
                                <div className="py-8 text-center">
                                    <Zap size={24} className="text-gray-300 mx-auto mb-2" />
                                    <p className="text-[12px] text-gray-400 mb-4">You have no briefing compiled for today.</p>
                                    <Link
                                        href="/briefings/generate"
                                        method="post"
                                        as="button"
                                        className="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl transition-colors shadow-md"
                                    >
                                        Generate Briefing
                                    </Link>
                                </div>
                            )}
                        </div>

                        {/* Task status stacked bar chart */}
                        <div className="bg-white rounded-2xl border border-gray-100/80 p-6 shadow-[0_1px_3px_rgba(0,0,0,0.02)]">
                            <h3 className="text-[13px] font-bold text-gray-900 mb-5">Workflow Allocation</h3>
                            
                            <div className="space-y-4">
                                {Object.entries(stats.tasks_by_status).map(([status, count], i) => {
                                    const formatted = status.replace('_', ' ').toUpperCase();
                                    return (
                                        <div key={status} className="flex items-center justify-between text-xs">
                                            <span className="text-gray-500 font-medium">{formatted}</span>
                                            <span className="font-extrabold text-gray-900">{count}</span>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>

                        {/* Recent updates log */}
                        <div className="bg-white rounded-2xl border border-gray-100/80 p-6 shadow-[0_1px_3px_rgba(0,0,0,0.02)]">
                            <h3 className="text-[13px] font-bold text-gray-900 mb-5">Recent Activity</h3>
                            <div className="space-y-4">
                                {stats.recent_activity.slice(0, 5).map(act => (
                                    <div key={act.id} className="flex items-start gap-3">
                                        <div className="w-6 h-6 rounded-full bg-indigo-50 border border-indigo-100 flex items-center justify-center text-[10px] font-extrabold text-indigo-600 shrink-0 mt-0.5">
                                            {getInitials(act.actor_name)}
                                        </div>
                                        <div className="min-w-0">
                                            <p className="text-[12px] text-gray-800 leading-snug">
                                                <strong>{act.actor_name}</strong> {act.action.replace('_', ' ')}: <em>{act.task_title}</em>
                                            </p>
                                            <span className="text-[10px] text-gray-400 block mt-0.5">{timeAgo(act.logged_at)}</span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </>
            )}
        </AppLayout>
    );
}

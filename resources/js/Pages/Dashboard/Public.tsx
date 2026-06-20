import React, { useState, useEffect } from 'react';
import { Head } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import {
    AreaChart, Area, BarChart, Bar, LineChart, Line, PieChart, Pie, Cell,
    XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer
} from 'recharts';
import { usePublicDashboardDataQuery, DashboardConfig, Widget } from '@/hooks/queries/useDashboards';
import { Button } from '@/Components/ui/Button';
import { Printer } from 'lucide-react';

interface Props {
    dashboard: DashboardConfig;
    token: string;
}

export default function PublicDashboard({ dashboard, token }: Props) {
    const [rangePreset, setRangePreset] = useState('last_30_days');

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

    const { data: widgetsData, isLoading } = usePublicDashboardDataQuery(
        token,
        dateRange
    );

    const layout = dashboard.layout || [];
    const localWidgetsData = widgetsData || {};

    const handlePrint = () => {
        window.print();
    };

    return (
        <div className="min-h-screen bg-slate-50 font-sans text-slate-900">
            <Head title={`${dashboard.name} - Performance Desk`} />

            <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                {/* Header Section */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8 pb-4 border-b border-gray-200 print:border-none print:mb-4 print:pb-0">
                    <div>
                        <h1 className="text-2xl font-extrabold tracking-tight">{dashboard.name}</h1>
                        <p className="text-sm text-slate-500 mt-1">Read-only performance view</p>
                    </div>
                    
                    <div className="flex items-center gap-3 print:hidden">
                        <select
                            className="text-sm border-gray-200 rounded-lg py-1.5 pl-3 pr-8 focus:ring-indigo-500 focus:border-indigo-500 bg-white"
                            value={rangePreset}
                            onChange={(e) => setRangePreset(e.target.value)}
                        >
                            <option value="last_7_days">Last 7 Days</option>
                            <option value="last_30_days">Last 30 Days</option>
                            <option value="last_quarter">Last Quarter</option>
                            <option value="year_to_date">Year to Date</option>
                        </select>

                        <Button onClick={handlePrint} variant="outline" size="sm" className="bg-white">
                            <Printer size={14} className="mr-2" /> Export PDF
                        </Button>
                    </div>
                </div>

                {isLoading ? (
                    <div className="flex items-center justify-center py-20 text-slate-400 text-sm">
                        Loading performance data...
                    </div>
                ) : layout.length > 0 ? (
                    <div className="grid grid-cols-1 md:grid-cols-6 gap-6 print:block">
                        {layout.map((widget: Widget) => {
                            const data = localWidgetsData[widget.id];
                            const spanCls = widget.position.w >= 6 ? 'md:col-span-6' : 'md:col-span-3';

                            return (
                                <div
                                    key={widget.id}
                                    className={cn(
                                        "bg-white border border-gray-200 rounded-2xl p-6 shadow-sm",
                                        "print:mb-6 print:border-none print:shadow-none print:p-0 print:break-inside-avoid",
                                        spanCls
                                    )}
                                    style={{ WebkitPrintColorAdjust: 'exact', printColorAdjust: 'exact' }}
                                >
                                    <div className="flex items-center justify-between mb-4">
                                        <h3 className="text-sm font-bold text-slate-800 tracking-wide">{widget.title}</h3>
                                        {data?.meta?.unit && (
                                            <span className="text-[10px] uppercase font-bold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-md">
                                                {data.meta.unit}
                                            </span>
                                        )}
                                    </div>

                                    {widget.type === 'metric_card' ? (
                                        <div className="flex items-baseline gap-2">
                                            <span className="text-4xl font-extrabold text-slate-900 tracking-tight tabular-nums">
                                                {data?.summary?.total ?? 0}
                                            </span>
                                            <span className="text-xs font-semibold text-slate-400">Total</span>
                                        </div>
                                    ) : widget.type === 'line_chart' && data?.data ? (
                                        <div className="h-[250px] print:h-[200px]">
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
                                        <div className="text-xs text-slate-400 py-6 text-center bg-slate-50/50 rounded-xl">
                                            No visualization available
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                ) : (
                    <div className="text-center py-20 bg-white rounded-2xl border border-gray-200">
                        <p className="text-slate-500 text-sm">No widgets are configured for this dashboard.</p>
                    </div>
                )}
            </main>
        </div>
    );
}

import React, { useState, useEffect } from 'react';
import { Button } from '@/Components/ui/Button';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { LayoutGrid, Plus, Save, Trash2, Settings, BarChart2, Calendar, Info } from 'lucide-react';
import { cn } from '@/lib/utils';
import axios from 'axios';
import { toast } from 'sonner';

interface KpiDefinition {
    id: string;
    name: string;
    slug: string;
    category: string;
    source: string;
    unit: string;
}

export default function DataVizIndex() {
    const [kpis, setKpis] = useState<KpiDefinition[]>([]);
    const [selectedKpi, setSelectedKpi] = useState<KpiDefinition | null>(null);
    const [dashboardName, setDashboardName] = useState('My Custom Dashboard');
    const [layout, setLayout] = useState<any[]>([]);
    const [chartType, setChartType] = useState<'line_chart' | 'bar_chart' | 'metric_card'>('line_chart');

    useEffect(() => {
        // Load KPIs definitions list
        axios.get('/api/v1/viz/kpis')
            .then(res => {
                setKpis(res.data.data ?? []);
                if (res.data.data?.length > 0) {
                    setSelectedKpi(res.data.data[0]);
                }
            });
    }, []);

    const handleAddWidget = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedKpi) return;

        const newId = `widget-${Date.now()}`;
        const newWidget = {
            id: newId,
            title: `${selectedKpi.name} Analytics`,
            type: chartType,
            spec: {
                metric_key: selectedKpi.slug,
                aggregation: 'sum',
                group_by: 'day',
                filters: {}
            },
            position: { x: 0, y: 0, w: 3, h: 2 }
        };

        setLayout([...layout, newWidget]);
        toast.success(`Added widget for ${selectedKpi.name}!`);
    };

    const handleRemoveWidget = (id: string) => {
        setLayout(layout.filter(w => w.id !== id));
    };

    const handleSaveDashboard = () => {
        axios.post('/api/v1/dashboards', {
            name: dashboardName,
            layout: layout,
            is_default: false,
        })
        .then(() => {
            toast.success('Dashboard saved successfully!');
            router.visit('/dashboard');
        })
        .catch(err => {
            toast.error(err.response?.data?.message ?? 'Failed to save dashboard.');
        });
    };

    return (
        <AppLayout title="Dashboard Builder">
            <Head title="Dashboard Builder" />

            {/* Header controls */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6 border-b border-gray-100/60 pb-4">
                <div>
                    <input
                        type="text"
                        value={dashboardName}
                        onChange={e => setDashboardName(e.target.value)}
                        className="text-lg font-extrabold text-gray-900 bg-transparent border-b border-transparent hover:border-gray-200 focus:border-indigo-500 focus:outline-none tracking-tight pb-0.5"
                    />
                    <p className="text-xs text-gray-400 mt-1 font-medium">Drag KPIs and design custom metric boards.</p>
                </div>
                <Button
                    onClick={handleSaveDashboard}
                    className="flex items-center gap-1.5 transition-all shadow-md self-start" 
                size="sm" >
                    <Save size={13} /> Save Dashboard
                </Button>
            </div>

            {/* Layout panel split */}
            <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
                
                {/* Left controls */}
                <div className="bg-white border border-gray-100 rounded-2xl p-5 shadow-[0_1px_3px_rgba(0,0,0,0.02)] space-y-5 h-fit">
                    <h3 className="text-xs font-bold text-gray-900 border-b border-gray-55 pb-2 flex items-center gap-1.5">
                        <Plus size={14} className="text-indigo-500" /> Add New Widget
                    </h3>

                    {kpis.length === 0 ? (
                        <div className="text-center py-6 text-xs text-gray-400">
                            <Info size={16} className="mx-auto mb-2" />
                            No KPI metrics defined in organization yet. Connect integrations via MCP settings.
                        </div>
                    ) : (
                        <form onSubmit={handleAddWidget} className="space-y-4">
                            <div>
                                <label className="block text-[10px] font-bold text-gray-500 mb-1">Select Metric KPI</label>
                                <select
                                    value={selectedKpi?.id || ''}
                                    onChange={e => {
                                        const found = kpis.find(k => k.id === e.target.value);
                                        if (found) setSelectedKpi(found);
                                    }}
                                    className="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs bg-gray-50 focus:bg-white"
                                >
                                    {kpis.map(k => (
                                        <option key={k.id} value={k.id}>{k.name}</option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="block text-[10px] font-bold text-gray-500 mb-1">Visualization Type</label>
                                <div className="grid grid-cols-3 gap-2">
                                    {[
                                        { id: 'metric_card', label: 'Card' },
                                        { id: 'line_chart', label: 'Line' },
                                        { id: 'bar_chart', label: 'Bar' },
                                    ].map(t => (
                                        <Button
                                            key={t.id}
                                            type="button"
                                            onClick={() => setChartType(t.id as any)}
                                            className={cn(
                                                "py-1.5 text-[10px] font-bold border rounded-lg transition-all",
                                                chartType === t.id
                                                    ? "bg-indigo-55 text-indigo-600 border-indigo-200"
                                                    : "border-gray-200 text-gray-500 hover:bg-gray-50"
                                            )}
                                        >
                                            {t.label}
                                        </Button>
                                    ))}
                                </div>
                            </div>

                            <Button
                                type="submit"
                                className="w-full transition-all shadow-md" 
                            size="sm" >
                                Insert to Canvas
                            </Button>
                        </form>
                    )}
                </div>

                {/* Right canvas */}
                <div className="lg:col-span-3 min-h-[450px] border-2 border-dashed border-gray-200 rounded-2xl p-6 flex flex-col relative bg-gray-50/50">
                    {layout.length === 0 ? (
                        <div className="flex-1 flex flex-col items-center justify-center text-center p-8">
                            <LayoutGrid size={32} className="text-gray-300 mb-3" />
                            <h4 className="text-xs font-bold text-gray-900">Canvas is Empty</h4>
                            <p className="text-[11px] text-gray-400 mt-1 max-w-xs">Configure options on the left and insert them into the canvas to start design.</p>
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6 w-full">
                            {layout.map(w => (
                                <div key={w.id} className="bg-white border border-gray-100 rounded-2xl p-5 shadow-[0_1px_3px_rgba(0,0,0,0.02)] flex flex-col relative group">
                                    <Button
                                        onClick={() => handleRemoveWidget(w.id)}
                                        className="absolute top-3 right-3 p-1 rounded-lg bg--50 text--700 hover:bg-red-100 opacity-0 group-hover:opacity-100 transition-opacity"
                                    >
                                        <Trash2 size={12} />
                                    </Button>

                                    <h4 className="text-xs font-bold text-gray-900 mb-2">{w.title}</h4>
                                    <div className="flex-1 bg-gray-50 border border-gray-100 rounded-xl p-6 flex items-center justify-center text-center text-xs text-gray-400 capitalize">
                                        <div className="space-y-1">
                                            <BarChart2 size={18} className="mx-auto text-indigo-400" />
                                            <p className="font-semibold text-gray-600">{w.type.replace('_', ' ')}</p>
                                            <p className="text-[10px] text-gray-400 font-mono">{w.spec.metric_key}</p>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

            </div>
        </AppLayout>
    );
}

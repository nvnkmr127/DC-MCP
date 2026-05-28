import React, { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { cn } from '@/lib/utils';
import { Plus, X, ChevronLeft, ChevronRight, List, Grid, CheckCircle2, ArrowRight } from 'lucide-react';

const STATUS_FLOW = ['idea', 'in_progress', 'in_review', 'approved', 'scheduled', 'published'] as const;

const STATUS_STYLES: Record<string, string> = {
    idea:        'bg-gray-100 text-gray-600',
    in_progress: 'bg-blue-100 text-blue-700',
    in_review:   'bg-amber-100 text-amber-700',
    approved:    'bg-violet-100 text-violet-700',
    scheduled:   'bg-indigo-100 text-indigo-700',
    published:   'bg-emerald-100 text-emerald-700',
    cancelled:   'bg-rose-100 text-rose-600',
};

const CHANNEL_STYLES: Record<string, string> = {
    instagram: 'bg-pink-100 text-pink-700',
    linkedin:  'bg-blue-100 text-blue-700',
    website:   'bg-green-100 text-green-700',
    email:     'bg-amber-100 text-amber-700',
    youtube:   'bg-red-100 text-red-700',
    twitter:   'bg-sky-100 text-sky-700',
    facebook:  'bg-purple-100 text-purple-700',
    google_ads:'bg-orange-100 text-orange-700',
    meta_ads:  'bg-indigo-100 text-indigo-700',
};

type ContentItem = {
    id: string; title: string; body: string | null; type: string;
    platform: string | null; status: string; due_date: string | null;
    scheduled_at: string | null; tags: string[];
    client: { id: string; name: string } | null;
    assignee: { id: string; name: string } | null;
    task_id: string | null;
};
type Client = { id: string; name: string; company: string };
type Member = { id: string; name: string };

interface Props {
    calendarItems: ContentItem[];
    listItems: { data: ContentItem[]; total: number };
    stats: { total: number; in_review: number; approved: number; scheduled: number; published: number };
    clients: Client[];
    projects: { id: string; name: string; client_id: string }[];
    filters: { clientId?: string; type?: string; status?: string; month: string };
}

function prevMonth(my: string) {
    const [y, m] = my.split('-').map(Number);
    const d = new Date(y, m - 2, 1);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}
function nextMonth(my: string) {
    const [y, m] = my.split('-').map(Number);
    const d = new Date(y, m, 1);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}
function monthLabel(my: string) {
    return new Date(my + '-01').toLocaleDateString('en-IN', { month: 'long', year: 'numeric' });
}
function daysInMonth(my: string) {
    const [y, m] = my.split('-').map(Number);
    return new Date(y, m, 0).getDate();
}
function firstDayOfWeek(my: string) {
    const [y, m] = my.split('-').map(Number);
    return new Date(y, m - 1, 1).getDay();
}

function CalendarGrid({ items, month }: { items: ContentItem[]; month: string }) {
    const [selectedItem, setSelectedItem] = useState<ContentItem | null>(null);
    const days = daysInMonth(month);
    const offset = firstDayOfWeek(month);
    const byDay: Record<number, ContentItem[]> = {};
    items.forEach(item => {
        const d = item.due_date || item.scheduled_at?.slice(0, 10);
        if (d) {
            const day = parseInt(d.slice(8, 10));
            if (!byDay[day]) byDay[day] = [];
            byDay[day].push(item);
        }
    });
    const cells = Array(offset).fill(null).concat(Array.from({ length: days }, (_, i) => i + 1));
    const today = new Date();
    const isCurrentMonth = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') === month;

    return (
        <div>
            <div className="grid grid-cols-7 text-xs text-center text-gray-400 font-medium mb-1">
                {['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map(d => <div key={d} className="py-1">{d}</div>)}
            </div>
            <div className="grid grid-cols-7 gap-px bg-gray-100 border border-gray-100 rounded-xl overflow-hidden">
                {cells.map((day, i) => (
                    <div key={i} className={cn('bg-white min-h-[90px] p-1.5', day === null && 'bg-gray-50')}>
                        {day !== null && (
                            <>
                                <span className={cn('text-xs font-medium block mb-1',
                                    isCurrentMonth && day === today.getDate() ? 'text-indigo-600 font-bold' : 'text-gray-500'
                                )}>{day}</span>
                                <div className="space-y-0.5">
                                    {(byDay[day] ?? []).slice(0, 3).map(item => (
                                        <button key={item.id} onClick={() => setSelectedItem(item)}
                                            className={cn('w-full text-left text-[10px] font-medium px-1.5 py-0.5 rounded truncate',
                                                CHANNEL_STYLES[item.platform ?? ''] ?? 'bg-gray-100 text-gray-600'
                                            )}>
                                            {item.title}
                                        </button>
                                    ))}
                                    {(byDay[day]?.length ?? 0) > 3 && (
                                        <span className="text-[10px] text-gray-400 px-1">+{(byDay[day].length - 3)} more</span>
                                    )}
                                </div>
                            </>
                        )}
                    </div>
                ))}
            </div>

            {selectedItem && (
                <div className="fixed inset-0 bg-black/30 flex items-center justify-center z-50 p-4" onClick={() => setSelectedItem(null)}>
                    <div className="bg-white rounded-2xl shadow-xl w-full max-w-md p-5 space-y-3" onClick={e => e.stopPropagation()}>
                        <div className="flex items-start justify-between">
                            <div>
                                <h3 className="font-bold text-gray-900">{selectedItem.title}</h3>
                                <div className="flex items-center gap-2 mt-1">
                                    {selectedItem.platform && (
                                        <span className={cn('text-xs px-2 py-0.5 rounded-full font-medium', CHANNEL_STYLES[selectedItem.platform] ?? 'bg-gray-100 text-gray-600')}>
                                            {selectedItem.platform}
                                        </span>
                                    )}
                                    <span className={cn('text-xs px-2 py-0.5 rounded-full font-medium', STATUS_STYLES[selectedItem.status])}>
                                        {selectedItem.status.replace('_', ' ')}
                                    </span>
                                </div>
                            </div>
                            <button onClick={() => setSelectedItem(null)}><X className="w-4 h-4 text-gray-400" /></button>
                        </div>
                        {selectedItem.body && <p className="text-sm text-gray-600">{selectedItem.body}</p>}
                        <div className="flex items-center gap-2 flex-wrap">
                            {STATUS_FLOW.map((s, idx) => {
                                const curIdx = STATUS_FLOW.indexOf(selectedItem.status as typeof STATUS_FLOW[number]);
                                if (idx <= curIdx || idx > curIdx + 1) return null;
                                return (
                                    <button key={s} onClick={() => {
                                        router.patch(`/content/${selectedItem.id}`, { status: s });
                                        setSelectedItem(null);
                                    }} className="flex items-center gap-1 px-3 py-1.5 bg-indigo-600 text-white text-xs rounded-lg hover:bg-indigo-700 font-medium">
                                        <ArrowRight className="w-3 h-3" /> Move to {s.replace('_', ' ')}
                                    </button>
                                );
                            })}
                            {!selectedItem.task_id && (
                                <button onClick={() => {
                                    router.post(`/content/${selectedItem.id}/convert-to-task`);
                                    setSelectedItem(null);
                                }} className="px-3 py-1.5 border border-gray-200 text-gray-600 text-xs rounded-lg hover:bg-gray-50 font-medium">
                                    → Convert to Task
                                </button>
                            )}
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

function AddContentModal({ clients, onClose, month }: { clients: Client[]; onClose: () => void; month: string }) {
    const form = useForm({
        client_id: '', title: '', type: 'social_post', platform: '',
        status: 'idea', due_date: '', body: '',
    });
    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 space-y-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-bold text-gray-900">Add Content</h2>
                    <button onClick={onClose}><X className="w-5 h-5 text-gray-400" /></button>
                </div>
                <form onSubmit={e => { e.preventDefault(); form.post('/content', { onSuccess: onClose }); }} className="space-y-3">
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Client *</label>
                        <select value={form.data.client_id} onChange={e => form.setData('client_id', e.target.value)}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                            <option value="">Select client…</option>
                            {clients.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Title *</label>
                        <input type="text" value={form.data.title} onChange={e => form.setData('title', e.target.value)}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Type</label>
                            <select value={form.data.type} onChange={e => form.setData('type', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                <option value="social_post">Social Post</option>
                                <option value="blog">Blog</option>
                                <option value="ad_campaign">Ad Campaign</option>
                            </select>
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Channel</label>
                            <select value={form.data.platform} onChange={e => form.setData('platform', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                <option value="">None</option>
                                {['instagram', 'facebook', 'twitter', 'linkedin', 'youtube', 'website', 'google_ads', 'meta_ads', 'email'].map(p =>
                                    <option key={p} value={p}>{p}</option>
                                )}
                            </select>
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Status</label>
                            <select value={form.data.status} onChange={e => form.setData('status', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                {STATUS_FLOW.map(s => <option key={s} value={s}>{s.replace('_', ' ')}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Due Date</label>
                            <input type="date" value={form.data.due_date} onChange={e => form.setData('due_date', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                        </div>
                    </div>
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Notes</label>
                        <textarea value={form.data.body} onChange={e => form.setData('body', e.target.value)} rows={3}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 resize-none" />
                    </div>
                    <div className="flex justify-end gap-2 pt-2">
                        <button type="button" onClick={onClose} className="px-4 py-2 text-sm text-gray-600">Cancel</button>
                        <button type="submit" disabled={form.processing || !form.data.client_id || !form.data.title}
                            className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                            {form.processing ? 'Adding…' : 'Add Content'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export default function ContentCalendarIndex({ calendarItems, listItems, stats, clients, filters }: Props) {
    const [view, setView] = useState<'calendar' | 'list'>('calendar');
    const [addOpen, setAddOpen] = useState(false);
    const [statusFilter, setStatusFilter] = useState(filters.status ?? '');

    const month = filters.month ?? new Date().toISOString().slice(0, 7);

    function navigate(m: string) {
        router.get('/content', { ...filters, month: m }, { preserveState: true });
    }

    function applyStatus(s: string) {
        const next = statusFilter === s ? '' : s;
        setStatusFilter(next);
        router.get('/content', { ...filters, status: next || undefined }, { preserveState: true });
    }

    return (
        <AppLayout>
            <Head title="Content Calendar" />
            <div className="space-y-5 max-w-6xl mx-auto">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Content Calendar</h1>
                        <p className="text-sm text-gray-500 mt-0.5">Plan, track and publish content across all channels</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <div className="flex items-center border border-gray-200 rounded-lg overflow-hidden">
                            <button onClick={() => setView('calendar')} className={cn('px-3 py-1.5 text-xs font-medium', view === 'calendar' ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-50')}>
                                <Grid className="w-3.5 h-3.5" />
                            </button>
                            <button onClick={() => setView('list')} className={cn('px-3 py-1.5 text-xs font-medium', view === 'list' ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-50')}>
                                <List className="w-3.5 h-3.5" />
                            </button>
                        </div>
                        <button onClick={() => setAddOpen(true)}
                            className="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                            <Plus className="w-4 h-4" /> Add Content
                        </button>
                    </div>
                </div>

                {/* Stats */}
                <div className="grid grid-cols-5 gap-3">
                    {[
                        { label: 'Total', value: stats.total, color: 'text-gray-900' },
                        { label: 'In Review', value: stats.in_review, color: 'text-amber-600' },
                        { label: 'Approved', value: stats.approved, color: 'text-violet-600' },
                        { label: 'Scheduled', value: stats.scheduled, color: 'text-indigo-600' },
                        { label: 'Published', value: stats.published, color: 'text-emerald-600' },
                    ].map(({ label, value, color }) => (
                        <div key={label} className="bg-white rounded-xl border border-gray-200 p-3 text-center">
                            <p className={cn('text-xl font-bold', color)}>{value}</p>
                            <p className="text-xs text-gray-400 mt-0.5">{label}</p>
                        </div>
                    ))}
                </div>

                {/* Status filters */}
                <div className="flex items-center gap-2 flex-wrap">
                    {(Object.keys(STATUS_STYLES) as string[]).map(s => (
                        <button key={s} onClick={() => applyStatus(s)}
                            className={cn('px-3 py-1 rounded-full text-xs font-medium border transition-all',
                                statusFilter === s
                                    ? STATUS_STYLES[s] + ' border-transparent'
                                    : 'bg-white text-gray-500 border-gray-200 hover:border-gray-300'
                            )}>
                            {s.replace('_', ' ')}
                        </button>
                    ))}
                    {statusFilter && (
                        <button onClick={() => applyStatus('')} className="px-2 py-1 text-xs text-gray-400 hover:text-gray-600">
                            <X className="w-3 h-3" />
                        </button>
                    )}
                </div>

                {view === 'calendar' ? (
                    <div className="bg-white rounded-xl border border-gray-200 p-4">
                        <div className="flex items-center justify-between mb-4">
                            <button onClick={() => navigate(prevMonth(month))} className="p-1.5 rounded-lg border border-gray-200 hover:bg-gray-50">
                                <ChevronLeft className="w-4 h-4" />
                            </button>
                            <span className="font-semibold text-gray-800">{monthLabel(month)}</span>
                            <button onClick={() => navigate(nextMonth(month))} className="p-1.5 rounded-lg border border-gray-200 hover:bg-gray-50">
                                <ChevronRight className="w-4 h-4" />
                            </button>
                        </div>
                        <CalendarGrid items={calendarItems} month={month} />
                    </div>
                ) : (
                    <div className="space-y-2">
                        {listItems.data.length === 0 && (
                            <div className="bg-white rounded-xl border border-gray-200 py-12 text-center">
                                <p className="text-sm text-gray-400">No content items yet.</p>
                            </div>
                        )}
                        {listItems.data.map(item => (
                            <div key={item.id} className="bg-white rounded-xl border border-gray-200 px-4 py-3 flex items-center justify-between gap-3">
                                <div className="flex items-center gap-3 min-w-0">
                                    {item.platform && (
                                        <span className={cn('text-xs px-2 py-0.5 rounded-full font-medium shrink-0', CHANNEL_STYLES[item.platform] ?? 'bg-gray-100 text-gray-600')}>
                                            {item.platform}
                                        </span>
                                    )}
                                    <div className="min-w-0">
                                        <p className="font-semibold text-sm text-gray-900 truncate">{item.title}</p>
                                        <p className="text-xs text-gray-400">{item.client?.name} {item.due_date ? `· Due ${item.due_date}` : ''}</p>
                                    </div>
                                </div>
                                <div className="flex items-center gap-2 shrink-0">
                                    <span className={cn('text-xs px-2 py-0.5 rounded-full font-medium', STATUS_STYLES[item.status])}>
                                        {item.status.replace('_', ' ')}
                                    </span>
                                    {!item.task_id && (
                                        <button onClick={() => router.post(`/content/${item.id}/convert-to-task`)}
                                            className="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                                            → Task
                                        </button>
                                    )}
                                    <button onClick={() => router.delete(`/content/${item.id}`, { onBefore: () => confirm('Delete this item?') })}
                                        className="text-xs text-gray-400 hover:text-rose-500">✕</button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                {addOpen && <AddContentModal clients={clients} onClose={() => setAddOpen(false)} month={month} />}
            </div>
        </AppLayout>
    );
}

import React, { useMemo } from 'react';
import { Button } from '@/Components/ui/Button';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { cn } from '@/lib/utils';
import { ChevronLeft, ChevronRight, Flag, CheckCircle2, Calendar, User, Folder, Clock } from 'lucide-react';

interface CalendarEvent {
    id: string;
    title: string;
    date: string;
    status: string;
    priority: string;
    type: 'task' | 'milestone';
    project?: { name: string } | null;
    assignee?: { name: string } | null;
    url: string;
}

interface Props {
    events: CalendarEvent[];
    year: number;
    month: number;
}

const PRIORITY_CONFIG: Record<string, { dot: string; text: string; bg: string; border: string }> = {
    urgent: {
        dot: 'bg-rose-500 shadow-[0_0_8px_rgba(244,63,94,0.5)]',
        text: 'text-rose-700',
        bg: 'bg-rose-50',
        border: 'border-rose-100',
    },
    high: {
        dot: 'bg-amber-500 shadow-[0_0_8px_rgba(245,158,11,0.5)]',
        text: 'text-amber-700',
        bg: 'bg-amber-50',
        border: 'border-amber-100',
    },
    medium: {
        dot: 'bg-yellow-400 shadow-[0_0_8px_rgba(250,204,21,0.5)]',
        text: 'text-yellow-700',
        bg: 'bg-yellow-50',
        border: 'border-yellow-100',
    },
    low: {
        dot: 'bg-slate-400',
        text: 'text-slate-600',
        bg: 'bg-slate-50',
        border: 'border-slate-100',
    },
};

const MONTH_NAMES = ['January','February','March','April','May','June','July','August','September','October','November','December'];
const DAY_NAMES   = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

export default function CalendarIndex({ events, year, month }: Props) {
    const firstDay = new Date(year, month - 1, 1).getDay();
    const daysInMonth = new Date(year, month, 0).getDate();

    // Prev/next navigation
    function navigate(dir: 1 | -1) {
        let y = year, m = month + dir;
        if (m > 12) { y++; m = 1; }
        if (m < 1)  { y--; m = 12; }
        router.get('/calendar', { year: y, month: m }, { preserveState: true });
    }

    // Group events by date
    const eventsByDate = useMemo(() => {
        const map: Record<string, CalendarEvent[]> = {};
        events.forEach(e => {
            if (!map[e.date]) map[e.date] = [];
            map[e.date].push(e);
        });
        return map;
    }, [events]);

    // Build calendar grid (6 rows × 7 cols)
    const cells: Array<number | null> = [];
    for (let i = 0; i < firstDay; i++) cells.push(null);
    for (let d = 1; d <= daysInMonth; d++) cells.push(d);
    while (cells.length % 7 !== 0) cells.push(null);

    const today = new Date();
    const isToday = (d: number) => d === today.getDate() && month === today.getMonth() + 1 && year === today.getFullYear();

    function dateStr(d: number) {
        return `${year}-${String(month).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
    }

    // Format date string nicely for the event cards
    function formatEventDate(dateString: string) {
        const d = new Date(dateString);
        return d.toLocaleDateString('en-US', { day: 'numeric', month: 'short' });
    }

    return (
        <AppLayout title="Workspace Calendar">
            <Head title="Calendar" />

            {/* Top Navigation Row */}
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                <div className="flex items-center gap-3">
                    <div className="flex bg-white rounded-xl border border-gray-200/80 p-0.5 shadow-sm">
                        <Button 
                            onClick={() => navigate(-1)} 
                            className="p-2 rounded-lg hover:bg-gray-50 text-gray-700 hover:text-gray-900 transition-colors"
                        >
                            <ChevronLeft size={16} />
                        </Button>
                        <div className="px-4 flex items-center justify-center min-w-[140px]">
                            <span className="text-sm font-bold text-gray-800">{MONTH_NAMES[month - 1]} {year}</span>
                        </div>
                        <Button 
                            onClick={() => navigate(1)} 
                            className="p-2 rounded-lg hover:bg-gray-50 text-gray-700 hover:text-gray-900 transition-colors"
                        >
                            <ChevronRight size={16} />
                        </Button>
                    </div>

                    <Button
                        onClick={() => router.get('/calendar', { year: today.getFullYear(), month: today.getMonth() + 1 })}
                        className="px-3.5 py-2 text-xs font-semibold bg-white border border-gray-200/80 rounded-xl hover:bg-gray-50 text-gray-700 transition-colors shadow-sm"
                    >
                        Today
                    </Button>
                </div>
                
                <p className="text-xs text-gray-400 flex items-center gap-1.5 self-end sm:self-auto">
                    <Clock size={13} /> Default Timezone: Asia/Kolkata (IST)
                </p>
            </div>

            {events.length === 0 && (
                <div className="mb-6 flex flex-col sm:flex-row items-center justify-between gap-4 bg-indigo-50 border border-indigo-100 rounded-2xl p-5 shadow-sm relative overflow-hidden">
                    <div className="absolute top-0 right-0 p-8 opacity-[0.03] pointer-events-none">
                        <Calendar size={120} />
                    </div>
                    <div className="relative z-10">
                        <h3 className="text-[14px] font-bold text-indigo-900 mb-1">Your calendar is looking empty this month</h3>
                        <p className="text-[13px] text-indigo-700/80">Tasks with due dates and project milestones will automatically appear here once created.</p>
                    </div>
                    <Link
                        href="/tasks/create"
                        className="relative z-10 shrink-0 px-4 py-2 bg-indigo-600 text-white text-[13px] font-semibold rounded-xl hover:bg-indigo-700 transition-colors shadow-sm"
                    >
                        Create a Task
                    </Link>
                </div>
            )}

            {/* Calendar Main Grid Card */}
            <div className="bg-white rounded-2xl border border-gray-200/60 shadow-[0_4px_20px_rgba(0,0,0,0.02)] overflow-hidden mb-8">
                {/* Day headers */}
                <div className="grid grid-cols-7 border-b border-gray-100 bg-gray-50/50">
                    {DAY_NAMES.map(d => (
                        <div key={d} className="py-3 text-[11px] font-bold text-gray-400 tracking-wider text-center uppercase">{d}</div>
                    ))}
                </div>

                {/* Calendar grid */}
                <div className="grid grid-cols-7 gap-px bg-gray-200/60">
                    {cells.map((day, idx) => {
                        const dayEvents = day ? (eventsByDate[dateStr(day)] ?? []) : [];
                        const cellToday = day && isToday(day);
                        return (
                            <div
                                key={idx}
                                className={cn(
                                    'bg-white min-h-[120px] p-2 flex flex-col transition-colors duration-200',
                                    !day && 'bg-gray-50/40',
                                    cellToday && 'bg-indigo-50/20 ring-1 ring-indigo-500/10',
                                    day && 'hover:bg-slate-50/30'
                                )}
                            >
                                {day && (
                                    <>
                                        <div className="flex items-center justify-between mb-1.5">
                                            <span className={cn(
                                                'inline-flex w-6 h-6 items-center justify-center text-xs font-bold rounded-lg transition-all',
                                                cellToday 
                                                    ? 'bg-gradient-to-tr from-indigo-500 to-purple-600 text-white shadow-[0_2px_8px_rgba(99,102,241,0.3)]' 
                                                    : 'text-gray-700 hover:bg-gray-100'
                                            )}>
                                                {day}
                                            </span>
                                            {dayEvents.length > 0 && (
                                                <span className="text-[10px] font-medium text-gray-400">
                                                    {dayEvents.length} {dayEvents.length === 1 ? 'item' : 'items'}
                                                </span>
                                            )}
                                        </div>
                                        
                                        <div className="flex-1 space-y-1 overflow-y-auto max-h-[85px] scrollbar-thin">
                                            {dayEvents.slice(0, 3).map(ev => {
                                                const isMilestone = ev.type === 'milestone';
                                                const priority = PRIORITY_CONFIG[ev.priority] ?? PRIORITY_CONFIG.low;
                                                return (
                                                    <Link
                                                        key={ev.id}
                                                        href={ev.url}
                                                        className={cn(
                                                            'flex items-center gap-1.5 px-2 py-1 rounded-lg text-[10px] font-medium leading-none truncate border transition-all active:scale-98',
                                                            isMilestone
                                                                ? 'bg-purple-50 text-purple-700 border-purple-100/50 hover:bg-purple-100/60 shadow-[0_1px_2px_rgba(168,85,247,0.03)]'
                                                                : ev.status === 'done'
                                                                ? 'bg-emerald-50/40 text-emerald-700/80 border-emerald-100/40 line-through opacity-70 hover:opacity-100'
                                                                : cn('hover:bg-slate-50', priority.bg, priority.text, priority.border)
                                                        )}
                                                        title={ev.title}
                                                    >
                                                        {isMilestone ? (
                                                            <Flag size={9} className="shrink-0 text-purple-500 fill-purple-500/20" />
                                                        ) : ev.status === 'done' ? (
                                                            <CheckCircle2 size={10} className="shrink-0 text-emerald-500" />
                                                        ) : (
                                                            <span className={cn('w-1.5 h-1.5 rounded-full shrink-0', priority.dot)} />
                                                        )}
                                                        <span className="truncate">{ev.title}</span>
                                                    </Link>
                                                );
                                            })}
                                            {dayEvents.length > 3 && (
                                                <div className="text-[9px] font-bold text-indigo-500 bg-indigo-50/40 hover:bg-indigo-50 rounded-md py-0.5 px-1.5 text-center transition-colors">
                                                    +{dayEvents.length - 3} more events
                                                </div>
                                            )}
                                        </div>
                                    </>
                                )}
                            </div>
                        );
                    })}
                </div>
            </div>

            {/* Upcoming List Redesigned into Premium Cards */}
            {events.length > 0 && (
                <div>
                    <h3 className="text-sm font-bold text-gray-900 tracking-wide mb-4">All Events This Month ({events.length})</h3>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {events.map(ev => {
                            const isMilestone = ev.type === 'milestone';
                            const priority = PRIORITY_CONFIG[ev.priority] ?? PRIORITY_CONFIG.low;
                            return (
                                <Link
                                    key={ev.id}
                                    href={ev.url}
                                    className="group flex items-start gap-4 p-4 bg-white rounded-2xl border border-gray-200/60 shadow-[0_1px_3px_rgba(0,0,0,0.01)] hover:-translate-y-0.5 hover:shadow-md hover:border-gray-300/80 transition-all duration-300"
                                >
                                    {/* Left Accent indicator depending on priority or type */}
                                    <div className={cn(
                                        'w-1 self-stretch rounded-full shrink-0',
                                        isMilestone ? 'bg-purple-500 shadow-[0_0_8px_rgba(168,85,247,0.4)]' : 
                                        ev.status === 'done' ? 'bg-emerald-400' :
                                        ev.priority === 'urgent' ? 'bg-rose-500 shadow-[0_0_8px_rgba(244,63,94,0.4)]' :
                                        ev.priority === 'high' ? 'bg-amber-500 shadow-[0_0_8px_rgba(245,158,11,0.4)]' :
                                        ev.priority === 'medium' ? 'bg-yellow-400' : 'bg-slate-300'
                                    )} />

                                    {/* Event Body details */}
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center gap-2 mb-1.5">
                                            <span className="text-[10px] font-bold text-gray-400 flex items-center gap-1">
                                                <Calendar size={11} /> {formatEventDate(ev.date)}
                                            </span>
                                            {isMilestone ? (
                                                <span className="px-2 py-0.5 bg-purple-50 border border-purple-100 text-purple-700 text-[9px] font-bold rounded-full uppercase tracking-wider flex items-center gap-1">
                                                    <Flag size={9} className="fill-purple-500/10" /> Milestone
                                                </span>
                                            ) : (
                                                <span className={cn('px-2 py-0.5 border text-[9px] font-bold rounded-full uppercase tracking-wider capitalize', priority.bg, priority.text, priority.border)}>
                                                    {ev.priority}
                                                </span>
                                            )}
                                        </div>

                                        <p className={cn(
                                            'text-xs font-bold text-gray-800 leading-snug group-hover:text-indigo-600 transition-colors truncate',
                                            ev.status === 'done' && 'line-through text-gray-400 group-hover:text-gray-400'
                                        )}>
                                            {ev.title}
                                        </p>

                                        {/* Project/Assignee metadata row */}
                                        <div className="flex flex-wrap items-center gap-x-3 gap-y-1 mt-2.5 text-[10px] text-gray-400">
                                            {ev.project && (
                                                <span className="flex items-center gap-1 font-medium bg-gray-50 border border-gray-100/60 rounded-md py-0.5 px-1.5">
                                                    <Folder size={10} className="text-indigo-400" /> {ev.project.name}
                                                </span>
                                            )}
                                            {ev.assignee && (
                                                <span className="flex items-center gap-1">
                                                    <User size={10} /> {ev.assignee.name}
                                                </span>
                                            )}
                                        </div>
                                    </div>

                                    {/* Status Icon */}
                                    {ev.status === 'done' && (
                                        <div className="shrink-0 p-1 bg-emerald-50 rounded-lg">
                                            <CheckCircle2 size={15} className="text-emerald-500" />
                                        </div>
                                    )}
                                </Link>
                            );
                        })}
                    </div>
                </div>
            )}
        </AppLayout>
    );
}

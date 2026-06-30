import React from 'react';
import { Button } from '@/Components/ui/Button';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { cn, formatDate } from '@/lib/utils';
import { Zap, ArrowLeft, RefreshCw, Loader2, Calendar, CheckSquare, Clock, Sparkles } from 'lucide-react';

interface BriefingData {
    id: string;
    date: string;
    status: string;
    digest_text: string | null;
    digest_html: string | null;
    delivered_at: string | null;
}

interface TaskData {
    id: string;
    title: string;
    status: string;
    due_date: string | null;
    project: { id: string; name: string } | null;
}

interface CalendarEvent {
    id: string;
    summary: string;
    description: string;
    start: string;
    end: string;
}

interface Suggestion {
    id: string; title: string; description: string | null; role_required: string | null;
    priority: 'low' | 'medium' | 'high' | 'critical' | 'urgent';
    due_date: string | null; estimated_hours: number | null;
    status: 'pending' | 'approved' | 'rejected' | 'modified';
    suggested_by: string; reasoning: string | null; rejection_reason: string | null;
    approved_at: string | null; created_at: string;
    project: { id: string; name: string } | null;
    client: { id: string; name: string } | null;
    approver: { id: string; name: string } | null;
    task: { id: string; title: string; status: string } | null;
}

interface Props {
    briefing: BriefingData;
    tasks_today: TaskData[];
    calendar_events: CalendarEvent[];
    suggestions: Suggestion[];
    projects: { id: string; name: string }[];
    clients: { id: string; name: string }[];
}

export default function BriefingShow({ briefing, tasks_today, calendar_events, suggestions, projects, clients }: Props) {
    function regenerate() {
        router.post('/briefings/generate');
    }

    const formatEventTime = (isoString: string) => {
        const date = new Date(isoString);
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    };

    return (
        <AppLayout title={`Briefing Desk — ${formatDate(briefing.date)}`}>
            <Head title={`Briefing — ${formatDate(briefing.date)}`} />

            <div className="max-w-6xl mx-auto">
                <div className="flex items-center gap-3 mb-6">
                    <Link href="/briefings" className="p-2 rounded-xl hover:bg-gray-100 text-gray-700 transition-colors">
                        <ArrowLeft size={16} />
                    </Link>
                    <div className="flex items-center gap-2">
                        <div className="w-7 h-7 rounded-lg bg-yellow-50 flex items-center justify-center text-yellow-500 shadow-[0_0_12px_rgba(234,179,8,0.15)] animate-pulse">
                            <Zap size={16} className="fill-yellow-500/20" />
                        </div>
                        <h1 className="text-xl font-extrabold text-gray-900 tracking-tight">Start of Day</h1>
                    </div>
                    <span className={cn(
                        'ml-auto px-2.5 py-0.5 rounded-full text-[10px] font-bold capitalize',
                        briefing.status === 'ready' || briefing.status === 'delivered' ? 'bg-emerald-50 text-emerald-700' :
                        briefing.status === 'generating' ? 'bg-indigo-50 text-indigo-800' :
                        briefing.status === 'failed' ? 'bg-rose-50 text-rose-700' : 'bg-gray-50 text-gray-700'
                    )}>
                        {briefing.status}
                    </span>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Left Column: AI Briefing */}
                    <div className="lg:col-span-2 space-y-6">
                        <div className="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm min-h-[400px]">
                            <h2 className="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                                <Zap size={20} className="text-indigo-500" /> Morning Assistant
                            </h2>
                            {briefing.status === 'generating' && (
                                <div className="flex flex-col items-center py-12 text-center">
                                    <Loader2 size={32} className="text-indigo-500 animate-spin mb-4" />
                                    <p className="text-gray-600 font-medium">Generating your briefing…</p>
                                    <p className="text-sm text-gray-400 mt-1">This usually takes 15–30 seconds</p>
                                </div>
                            )}

                            {briefing.status === 'failed' && (
                                <div className="flex flex-col items-center py-12 text-center">
                                    <p className="text-gray-600 mb-4">Briefing generation failed.</p>
                                    <Button onClick={regenerate} className="flex items-center gap-2" >
                                        <RefreshCw size={16} /> Retry
                                    </Button>
                                </div>
                            )}

                            {briefing.status === 'pending' && !briefing.digest_text && (
                                <div className="flex flex-col items-center py-12 text-center">
                                    <p className="text-gray-500 mb-4">This briefing hasn't been generated yet.</p>
                                    <Button onClick={regenerate} className="flex items-center gap-2" >
                                        <Zap size={16} /> Generate Now
                                    </Button>
                                </div>
                            )}

                            {briefing.digest_html ? (
                                <div
                                    className="prose prose-sm max-w-none text-gray-700 leading-relaxed"
                                    dangerouslySetInnerHTML={{ __html: briefing.digest_html }}
                                />
                            ) : briefing.digest_text ? (
                                <p className="text-sm text-gray-700 leading-relaxed whitespace-pre-wrap">{briefing.digest_text}</p>
                            ) : null}
                        </div>

                        {/* AI Task Suggestions */}
                        {suggestions && suggestions.length > 0 && (
                            <div className="space-y-4">
                                <h3 className="text-lg font-bold text-gray-900 flex items-center gap-2">
                                    <Sparkles size={20} className="text-indigo-500" />
                                    Task Suggestions from this Briefing
                                </h3>
                                <div className="grid gap-4">
                                    {suggestions.map(s => (
                                        <div key={s.id} className={cn(
                                            'bg-white rounded-xl border p-4 shadow-sm transition-all',
                                            s.status === 'approved' ? 'border-emerald-200 bg-emerald-50/30' :
                                            s.status === 'rejected' ? 'border-gray-200 bg-gray-50' : 'border-indigo-100'
                                        )}>
                                            <div className="flex justify-between items-start">
                                                <div>
                                                    <h4 className="text-sm font-bold text-gray-900">{s.title}</h4>
                                                    {s.description && <p className="text-xs text-gray-600 mt-1">{s.description}</p>}
                                                    {s.reasoning && <p className="text-xs text-indigo-600 italic mt-2">"{s.reasoning}"</p>}
                                                </div>
                                                <span className={cn('px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider',
                                                    s.status === 'approved' ? 'bg-emerald-100 text-emerald-700' :
                                                    s.status === 'rejected' ? 'bg-gray-200 text-gray-700' : 'bg-indigo-100 text-indigo-700'
                                                )}>
                                                    {s.status}
                                                </span>
                                            </div>
                                            
                                            <div className="flex gap-2 mt-3 items-center text-xs text-gray-500">
                                                {s.priority && <span className="uppercase font-medium border rounded px-1.5 py-0.5">{s.priority}</span>}
                                                {s.project && <span>Project: {s.project.name}</span>}
                                            </div>

                                            {s.status === 'pending' && (
                                                <div className="flex gap-2 mt-4 pt-3 border-t border-gray-100">
                                                    <Button onClick={() => router.post(`/suggestions/${s.id}/approve`, {})} className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs px-3 py-1.5 h-auto">
                                                        Approve
                                                    </Button>
                                                    <Button onClick={() => router.post(`/suggestions/${s.id}/reject`, { reason: 'Dismissed from Briefing' })} className="bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 text-xs px-3 py-1.5 h-auto">
                                                        Dismiss
                                                    </Button>
                                                </div>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Right Column: Context Widgets */}
                    <div className="space-y-6">
                        {/* Today's Calendar */}
                        <div className="bg-white rounded-2xl border border-gray-100 p-5 shadow-sm">
                            <div className="flex items-center justify-between mb-4">
                                <h3 className="text-[13px] font-bold text-gray-900 flex items-center gap-2">
                                    <Calendar size={16} className="text-rose-500" /> Today's Schedule
                                </h3>
                            </div>
                            
                            {calendar_events && calendar_events.length > 0 ? (
                                <div className="space-y-3">
                                    {calendar_events.map(event => (
                                        <div key={event.id} className="flex gap-3 items-start border-l-2 border-rose-200 pl-3 py-1">
                                            <div className="w-14 shrink-0 text-xs text-gray-500 font-medium">
                                                {formatEventTime(event.start)}
                                            </div>
                                            <div className="min-w-0">
                                                <p className="text-sm font-medium text-gray-900 truncate" title={event.summary}>
                                                    {event.summary}
                                                </p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="text-center py-6">
                                    <p className="text-xs text-gray-400">No events scheduled for today, or calendar not connected.</p>
                                </div>
                            )}
                        </div>

                        {/* My Tasks */}
                        <div className="bg-white rounded-2xl border border-gray-100 p-5 shadow-sm">
                            <div className="flex items-center justify-between mb-4">
                                <h3 className="text-[13px] font-bold text-gray-900 flex items-center gap-2">
                                    <CheckSquare size={16} className="text-emerald-500" /> Active Tasks
                                </h3>
                                <Link href="/projects" className="text-[11px] font-medium text-indigo-600 hover:text-indigo-800">
                                    View All
                                </Link>
                            </div>
                            
                            {tasks_today && tasks_today.length > 0 ? (
                                <div className="space-y-3">
                                    {tasks_today.map(task => (
                                        <div key={task.id} className="bg-gray-50 rounded-xl p-3 border border-gray-100">
                                            <div className="flex justify-between items-start mb-1">
                                                <h4 className="text-sm font-medium text-gray-900 leading-tight">
                                                    <Link href={`/projects/${task.project?.id}?tab=tasks`} className="hover:underline">
                                                        {task.title}
                                                    </Link>
                                                </h4>
                                            </div>
                                            <div className="flex items-center gap-3 text-xs mt-2">
                                                {task.project && (
                                                    <span className="text-gray-500 truncate max-w-[120px]">{task.project.name}</span>
                                                )}
                                                <span className={cn('px-1.5 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider',
                                                    task.status === 'in_progress' ? 'bg-blue-100 text-blue-700' :
                                                    'bg-gray-200 text-gray-700'
                                                )}>
                                                    {task.status.replace('_', ' ')}
                                                </span>
                                                {task.due_date && (
                                                    <span className="flex items-center gap-1 text-gray-500 ml-auto">
                                                        <Clock size={12} /> {formatDate(task.due_date)}
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="text-center py-6">
                                    <p className="text-xs text-gray-400">No active tasks assigned to you right now.</p>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

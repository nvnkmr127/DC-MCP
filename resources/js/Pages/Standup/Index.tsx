import React, { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Users, CheckCircle2, Clock, AlertTriangle, ChevronLeft, ChevronRight, Send } from 'lucide-react';
import { cn } from '@/lib/utils';

interface MyStandup {
    id: string; completed_today: string; in_progress: string | null;
    blockers: string | null; tomorrow_plan: string | null; status: string;
}
interface TeamStandup {
    id: string; completed_today: string; in_progress: string | null;
    blockers: string | null; tomorrow_plan: string | null; status: string;
    submitted_at: string | null; has_blockers: boolean;
    user: { id: string; name: string } | null;
}
interface Props {
    myStandup: MyStandup | null;
    teamStandups: TeamStandup[];
    date: string;
    stats: { total_team: number; submitted: number; pending: number; blockers: number };
}

export default function StandupIndex({ myStandup, teamStandups, date, stats }: Props) {
    const [viewDate, setViewDate] = useState(date);

    const form = useForm({
        completed_today: myStandup?.completed_today ?? '',
        in_progress:     myStandup?.in_progress ?? '',
        blockers:        myStandup?.blockers ?? '',
        tomorrow_plan:   myStandup?.tomorrow_plan ?? '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/standup');
    };

    const navigateDate = (offset: number) => {
        const d = new Date(viewDate);
        d.setDate(d.getDate() + offset);
        const newDate = d.toISOString().split('T')[0];
        setViewDate(newDate);
        router.get('/standup', { date: newDate }, { preserveState: true });
    };

    const isToday = viewDate === new Date().toISOString().split('T')[0];

    return (
        <AppLayout>
            <Head title="EOD Standup" />

            <div className="max-w-4xl mx-auto px-4 py-6 space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">EOD Standup</h1>
                        <p className="text-sm text-gray-500 mt-0.5">End-of-day team check-ins</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <button onClick={() => navigateDate(-1)} className="p-1.5 rounded-lg border border-gray-200 hover:bg-gray-50">
                            <ChevronLeft className="w-4 h-4 text-gray-600" />
                        </button>
                        <span className="text-sm font-medium text-gray-700 min-w-28 text-center">
                            {isToday ? 'Today' : viewDate}
                        </span>
                        <button onClick={() => navigateDate(1)} disabled={isToday} className="p-1.5 rounded-lg border border-gray-200 hover:bg-gray-50 disabled:opacity-40">
                            <ChevronRight className="w-4 h-4 text-gray-600" />
                        </button>
                    </div>
                </div>

                {/* Stats */}
                <div className="grid grid-cols-4 gap-4">
                    {[
                        { label: 'Team Members', value: stats.total_team, icon: Users, color: 'text-blue-600', bg: 'bg-blue-50' },
                        { label: 'Submitted', value: stats.submitted, icon: CheckCircle2, color: 'text-emerald-600', bg: 'bg-emerald-50' },
                        { label: 'Pending', value: stats.pending, icon: Clock, color: 'text-amber-600', bg: 'bg-amber-50' },
                        { label: 'Blockers', value: stats.blockers, icon: AlertTriangle, color: 'text-rose-600', bg: 'bg-rose-50' },
                    ].map(({ label, value, icon: Icon, color, bg }) => (
                        <div key={label} className="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-3">
                            <div className={cn('w-9 h-9 rounded-lg flex items-center justify-center', bg)}>
                                <Icon className={cn('w-4 h-4', color)} />
                            </div>
                            <div>
                                <p className="text-xs text-gray-500">{label}</p>
                                <p className="text-xl font-bold text-gray-900">{value}</p>
                            </div>
                        </div>
                    ))}
                </div>

                {/* My Standup Form (today only) */}
                {isToday && (
                    <div className="bg-white rounded-xl border border-gray-200">
                        <div className="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                            <h2 className="text-sm font-semibold text-gray-700">
                                {myStandup ? 'Your Standup (submitted)' : 'Submit Your Standup'}
                            </h2>
                            {myStandup && (
                                <span className="flex items-center gap-1.5 text-xs text-emerald-600 font-medium">
                                    <CheckCircle2 className="w-3.5 h-3.5" /> Submitted
                                </span>
                            )}
                        </div>
                        <form onSubmit={submit} className="px-5 py-4 space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-gray-700 mb-1.5">
                                    What did you complete today? <span className="text-rose-500">*</span>
                                </label>
                                <textarea rows={3} value={form.data.completed_today}
                                    onChange={e => form.setData('completed_today', e.target.value)}
                                    placeholder="Tasks completed, PRs merged, calls done…"
                                    className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 resize-none" />
                                {form.errors.completed_today && <p className="text-xs text-rose-500 mt-1">{form.errors.completed_today}</p>}
                            </div>
                            <div>
                                <label className="block text-xs font-semibold text-gray-700 mb-1.5">In Progress</label>
                                <textarea rows={2} value={form.data.in_progress}
                                    onChange={e => form.setData('in_progress', e.target.value)}
                                    placeholder="Tasks still ongoing…"
                                    className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 resize-none" />
                            </div>
                            <div>
                                <label className="block text-xs font-semibold text-gray-700 mb-1.5">
                                    Blockers
                                    <span className="ml-1.5 text-xs text-gray-400 font-normal">(anything blocking you?)</span>
                                </label>
                                <textarea rows={2} value={form.data.blockers}
                                    onChange={e => form.setData('blockers', e.target.value)}
                                    placeholder="Leave empty if none…"
                                    className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 resize-none" />
                            </div>
                            <div>
                                <label className="block text-xs font-semibold text-gray-700 mb-1.5">Tomorrow's Plan</label>
                                <textarea rows={2} value={form.data.tomorrow_plan}
                                    onChange={e => form.setData('tomorrow_plan', e.target.value)}
                                    placeholder="What's your focus tomorrow?"
                                    className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 resize-none" />
                            </div>
                            <div className="flex justify-end">
                                <button type="submit" disabled={form.processing}
                                    className="flex items-center gap-2 px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors">
                                    <Send className="w-3.5 h-3.5" />
                                    {form.processing ? 'Submitting…' : myStandup ? 'Update Standup' : 'Submit Standup'}
                                </button>
                            </div>
                        </form>
                    </div>
                )}

                {/* Team Standups */}
                <div className="space-y-3">
                    <h2 className="text-sm font-semibold text-gray-700">Team Standups ({teamStandups.length})</h2>
                    {teamStandups.length === 0 && (
                        <div className="bg-white rounded-xl border border-gray-200 px-5 py-10 text-center text-gray-400 text-sm">
                            No standups submitted yet for this date.
                        </div>
                    )}
                    {teamStandups.map(s => (
                        <div key={s.id} className={cn('bg-white rounded-xl border p-5 space-y-3', s.has_blockers ? 'border-rose-200' : 'border-gray-200')}>
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-2">
                                    <div className="w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-sm font-semibold">
                                        {s.user?.name?.[0] ?? '?'}
                                    </div>
                                    <span className="font-semibold text-gray-900 text-sm">{s.user?.name ?? 'Unknown'}</span>
                                    {s.has_blockers && (
                                        <span className="flex items-center gap-1 text-xs text-rose-600 font-medium bg-rose-50 px-2 py-0.5 rounded-full">
                                            <AlertTriangle className="w-3 h-3" /> Blocker
                                        </span>
                                    )}
                                </div>
                                <div className="flex items-center gap-3">
                                    <span className="text-xs text-gray-400">{s.submitted_at ? new Date(s.submitted_at).toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' }) : ''}</span>
                                    {s.status === 'submitted' && (
                                        <button
                                            onClick={() => router.post(`/standup/${s.id}/reviewed`)}
                                            className="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                                            Mark Reviewed
                                        </button>
                                    )}
                                    {s.status === 'reviewed' && (
                                        <span className="text-xs text-emerald-600 font-medium">Reviewed</span>
                                    )}
                                </div>
                            </div>
                            <div className="grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <p className="text-xs font-medium text-gray-500 mb-1">Completed Today</p>
                                    <p className="text-gray-700 whitespace-pre-line text-xs leading-relaxed">{s.completed_today}</p>
                                </div>
                                {s.in_progress && (
                                    <div>
                                        <p className="text-xs font-medium text-gray-500 mb-1">In Progress</p>
                                        <p className="text-gray-700 whitespace-pre-line text-xs leading-relaxed">{s.in_progress}</p>
                                    </div>
                                )}
                                {s.blockers && (
                                    <div className="col-span-2">
                                        <p className="text-xs font-medium text-rose-600 mb-1">Blockers</p>
                                        <p className="text-rose-700 whitespace-pre-line text-xs leading-relaxed bg-rose-50 rounded-lg px-3 py-2">{s.blockers}</p>
                                    </div>
                                )}
                                {s.tomorrow_plan && (
                                    <div>
                                        <p className="text-xs font-medium text-gray-500 mb-1">Tomorrow's Plan</p>
                                        <p className="text-gray-700 whitespace-pre-line text-xs leading-relaxed">{s.tomorrow_plan}</p>
                                    </div>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}

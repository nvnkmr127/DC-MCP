import React from 'react';
import { Link, router } from '@inertiajs/react';

import { cn, formatDate } from '@/lib/utils';
import type { DailyBriefing, PaginatedResponse } from '@/types';
import { Zap, ChevronRight, Loader2, Calendar, Sparkles, AlertCircle } from 'lucide-react';

interface Props {
    briefings: PaginatedResponse<DailyBriefing>;
}

const STATUS_CONFIG: Record<string, { bg: string; text: string; border: string; glow: string; label: string }> = {
    pending: {
        bg: 'bg-gray-50/50',
        text: 'text-gray-500',
        border: 'border-gray-200/60',
        glow: 'hover:shadow-gray-200/10',
        label: 'Scheduled'
    },
    generating: {
        bg: 'bg-amber-50/30',
        text: 'text-amber-600',
        border: 'border-amber-200/50',
        glow: 'hover:shadow-amber-500/5 shadow-[0_0_15px_rgba(245,158,11,0.05)]',
        label: 'Generating'
    },
    ready: {
        bg: 'bg-emerald-50/40',
        text: 'text-emerald-700 border-emerald-100',
        border: 'border-emerald-200/50',
        glow: 'hover:shadow-emerald-500/5',
        label: 'Ready'
    },
    delivered: {
        bg: 'bg-indigo-50/40',
        text: 'text-indigo-700 border-indigo-100',
        border: 'border-indigo-200/50',
        glow: 'hover:shadow-indigo-500/5',
        label: 'Delivered'
    },
    failed: {
        bg: 'bg-rose-50/40',
        text: 'text-rose-700 border-rose-100',
        border: 'border-rose-200/50',
        glow: 'hover:shadow-rose-500/5 shadow-[0_0_15px_rgba(244,63,94,0.05)]',
        label: 'Failed'
    },
};

export default function BriefingsIndex({ briefings }: Props) {
    function generate() {
        router.post('/briefings/generate');
    }

    return (
        <div>
            

            {/* Morning Digest Banner */}
            <div className="relative mb-8 bg-gradient-to-r from-[#0f172a] via-[#1e1b4b] to-[#0f172a] rounded-2xl p-6 border border-indigo-500/10 shadow-[0_4px_30px_rgba(0,0,0,0.15)] overflow-hidden">
                <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,_var(--tw-gradient-stops))] from-indigo-500/10 via-transparent to-transparent pointer-events-none" />
                <div className="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2 mb-1.5">
                            <div className="p-1 bg-indigo-500/10 text-indigo-400 rounded-lg">
                                <Sparkles size={16} className="animate-pulse" />
                            </div>
                            <h2 className="text-sm font-bold text-indigo-200 uppercase tracking-wider">Morning Insights</h2>
                        </div>
                        <h1 className="text-xl font-bold text-white mb-1">Executive Summary Desk</h1>
                        <p className="text-xs text-slate-400 max-w-xl">
                            Catch up on recent updates, SLA alerts, notifications, and key metrics generated automatically by intelligence models each morning.
                        </p>
                    </div>
                    <button
                        onClick={generate}
                        className="flex items-center justify-center gap-2 px-4 py-2.5 bg-gradient-to-r from-indigo-500 to-purple-600 text-white text-xs font-semibold rounded-xl hover:from-indigo-600 hover:to-purple-700 hover:shadow-[0_0_20px_rgba(99,102,241,0.4)] transition-all shrink-0 active:scale-95"
                    >
                        <Zap size={14} className="fill-white/10" /> Compile Today's Briefing
                    </button>
                </div>
            </div>

            {/* Briefings Grid */}
            {briefings.data.length === 0 ? (
                <div className="bg-white rounded-2xl border border-gray-100 p-16 text-center shadow-[0_1px_3px_rgba(0,0,0,0.02)]">
                    <div className="w-12 h-12 rounded-2xl bg-indigo-50 flex items-center justify-center mx-auto mb-4">
                        <Zap size={20} className="text-indigo-500" />
                    </div>
                    <p className="text-sm font-semibold text-gray-900 mb-1">No Briefings Available</p>
                    <p className="text-xs text-gray-500 mb-5 max-w-xs mx-auto">Generate your first automated personal summary to review recent activities across your workspace.</p>
                    <button 
                        onClick={generate} 
                        className="px-4 py-2 text-xs font-semibold bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 transition-colors shadow-sm"
                    >
                        Compile First Briefing
                    </button>
                </div>
            ) : (
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
                    {briefings.data.map((briefing) => {
                        const cfg = STATUS_CONFIG[briefing.status] ?? STATUS_CONFIG.pending;
                        return (
                            <Link
                                key={briefing.id}
                                href={`/briefings/${briefing.id}`}
                                className={cn(
                                    'group flex flex-col bg-white rounded-2xl border transition-all duration-300',
                                    briefing.status === 'generating' ? 'pointer-events-none' : '',
                                    cfg.border,
                                    cfg.glow,
                                    'hover:-translate-y-0.5 hover:shadow-lg'
                                )}
                            >
                                <div className="p-5 flex-1 flex flex-col">
                                    {/* Card Header */}
                                    <div className="flex items-center justify-between mb-4">
                                        <div className="flex items-center gap-2">
                                            <div className="w-8 h-8 rounded-lg bg-gray-50 flex items-center justify-center text-gray-500 group-hover:bg-indigo-50 group-hover:text-indigo-600 transition-colors">
                                                <Calendar size={15} />
                                            </div>
                                            <span className="text-xs font-bold text-gray-900">{formatDate(briefing.date)}</span>
                                        </div>
                                        <span className={cn('px-2 py-0.5 rounded-full text-[10px] font-bold border capitalize', cfg.bg, cfg.text)}>
                                            {cfg.label}
                                        </span>
                                    </div>

                                    {/* Briefing body/loader */}
                                    <div className="flex-1 flex flex-col justify-center min-h-[90px]">
                                        {briefing.status === 'generating' ? (
                                            <div className="flex flex-col items-center justify-center py-4 text-center">
                                                <Loader2 size={24} className="text-amber-500 animate-spin mb-2" />
                                                <p className="text-[11px] font-medium text-slate-500">Processing live syncs & analytics...</p>
                                            </div>
                                        ) : briefing.status === 'failed' ? (
                                            <div className="flex items-start gap-2.5 py-2">
                                                <AlertCircle size={15} className="text-rose-500 shrink-0 mt-0.5" />
                                                <p className="text-xs text-rose-600 leading-snug">
                                                    Generation failed due to missing system analytics variables. Please retry compiling.
                                                </p>
                                            </div>
                                        ) : (
                                            <p className="text-xs text-slate-500 leading-relaxed line-clamp-4">
                                                {briefing.digest_text || 'Briefing is empty or has no text summaries. Check the details page for complete metadata.'}
                                            </p>
                                        )}
                                    </div>

                                    {/* Card Footer / Metadata */}
                                    {briefing.status !== 'generating' && briefing.status !== 'failed' && (
                                        <div className="mt-4 pt-3 border-t border-gray-50 flex items-center justify-between text-[11px] text-gray-400">
                                            <span>{briefing.ai_model || 'Gemini 1.5 Flash'}</span>
                                            {briefing.delivered_via && briefing.delivered_via.length > 0 && (
                                                <div className="flex items-center gap-1">
                                                    <span className="w-1.5 h-1.5 rounded-full bg-emerald-500" />
                                                    <span className="capitalize">{briefing.delivered_via.join(', ')}</span>
                                                </div>
                                            )}
                                        </div>
                                    )}
                                </div>

                                {/* Link bar at the bottom */}
                                {briefing.status !== 'generating' && (
                                    <div className="px-5 py-3.5 bg-gray-50/50 rounded-b-2xl border-t border-gray-50 flex items-center justify-between group-hover:bg-indigo-50/20 transition-colors">
                                        <span className="text-[11px] font-bold text-gray-500 group-hover:text-indigo-600 transition-colors">
                                            {briefing.status === 'failed' ? 'Re-generate' : 'View Full Summary'}
                                        </span>
                                        <ChevronRight size={13} className="text-gray-400 group-hover:text-indigo-600 group-hover:translate-x-0.5 transition-all" />
                                    </div>
                                )}
                            </Link>
                        );
                    })}
                </div>
            )}

            {/* Pagination */}
            {briefings.meta && briefings.meta.last_page > 1 && (
                <div className="px-5 py-3 bg-white rounded-2xl border border-gray-100 flex items-center justify-between shadow-[0_1px_3px_rgba(0,0,0,0.01)]">
                    <p className="text-xs text-gray-400">
                        Showing page {briefings.meta.current_page} of {briefings.meta.last_page} ({briefings.meta.total} briefings total)
                    </p>
                    <div className="flex gap-1">
                        {Array.from({ length: briefings.meta.last_page }, (_, i) => i + 1).map((page) => (
                            <button
                                key={page}
                                onClick={() => router.get('/briefings', { page }, { preserveState: true })}
                                className={cn(
                                    'w-8 h-8 rounded-lg text-xs font-semibold transition-colors',
                                    page === briefings.meta.current_page
                                        ? 'bg-indigo-600 text-white'
                                        : 'text-gray-500 hover:bg-gray-100',
                                )}
                            >
                                {page}
                            </button>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}

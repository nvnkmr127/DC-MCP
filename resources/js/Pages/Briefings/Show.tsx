import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { cn, formatDate } from '@/lib/utils';
import { Zap, ArrowLeft, RefreshCw, Loader2 } from 'lucide-react';

interface BriefingData {
    id: string;
    date: string;
    status: string;
    digest_text: string | null;
    digest_html: string | null;
    delivered_at: string | null;
}

interface Props {
    briefing: BriefingData;
}

export default function BriefingShow({ briefing }: Props) {
    function regenerate() {
        router.post('/briefings/generate');
    }

    return (
        <AppLayout title={`Briefing Desk — ${formatDate(briefing.date)}`}>
            <Head title={`Briefing — ${formatDate(briefing.date)}`} />

            <div className="max-w-3xl mx-auto">
                <div className="flex items-center gap-3 mb-6">
                    <Link href="/briefings" className="p-2 rounded-xl hover:bg-gray-100 text-gray-700 transition-colors">
                        <ArrowLeft size={16} />
                    </Link>
                    <div className="flex items-center gap-2">
                        <div className="w-7 h-7 rounded-lg bg-yellow-50 flex items-center justify-center text-yellow-500 shadow-[0_0_12px_rgba(234,179,8,0.15)] animate-pulse">
                            <Zap size={14} className="fill-yellow-500/20" />
                        </div>
                        <h1 className="text-sm font-bold text-gray-900">Personal Morning Brief</h1>
                    </div>
                    <span className={cn(
                        'ml-auto px-2.5 py-0.5 rounded-full text-[10px] font-bold capitalize',
                        briefing.status === 'ready' || briefing.status === 'delivered' ? 'bg-emerald-50 text-emerald-700' :
                        briefing.status === 'generating' ? 'bg--50 text--800' :
                        briefing.status === 'failed' ? 'bg-rose-50 text-rose-700' : 'bg-gray-50 text-gray-700'
                    )}>
                        {briefing.status}
                    </span>
                </div>

                <div className="bg-white rounded-2xl border border-gray-100 p-6 shadow-[0_1px_3px_rgba(0,0,0,0.02)]">
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
                            <button onClick={regenerate} className="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700">
                                <RefreshCw size={14} /> Retry
                            </button>
                        </div>
                    )}

                    {briefing.status === 'pending' && !briefing.digest_text && (
                        <div className="flex flex-col items-center py-12 text-center">
                            <p className="text-gray-500 mb-4">This briefing hasn't been generated yet.</p>
                            <button onClick={regenerate} className="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700">
                                <Zap size={14} /> Generate Now
                            </button>
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
            </div>
        </AppLayout>
    );
}

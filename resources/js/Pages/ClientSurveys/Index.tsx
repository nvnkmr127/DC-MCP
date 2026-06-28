import React, { useState } from 'react';
import { Button } from '@/Components/ui/Button';
import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { cn } from '@/lib/utils';
import { useConfirm } from '@/hooks/useConfirm';
import { Plus, X, Smile, Trash2 } from 'lucide-react';

interface Survey {
    id: string; nps_score: number | null; feedback: string | null;
    sent_at: string; responded_at: string | null; status: string;
    client: { id: string; name: string } | null;
}
interface Client { id: string; name: string; }
interface NpsStats { avg: number | null; promoters: number; passives: number; detractors: number; }
interface Props { surveys: Survey[]; clients: Client[]; npsStats: NpsStats; }

const scoreColor = (n: number) => n >= 9 ? 'text-emerald-600 bg-emerald-50' : n >= 7 ? 'text-amber-600 bg-amber-50' : 'text-rose-600 bg-rose-50';
const STATUS_STYLES: Record<string, string> = {
    sent: 'bg-blue-100 text-blue-700', responded: 'bg-emerald-100 text-emerald-700', expired: 'bg-gray-100 text-gray-700',
};

function SendModal({ clients, onClose }: { clients: Client[]; onClose: () => void }) {
    const form = useForm({ client_id: '' });
    const confirm = useConfirm();

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        const selectedClient = clients.find(c => c.id === form.data.client_id);
        const ok = await confirm({
            title: 'Send NPS Survey?',
            description: `This will generate and send an NPS survey email to ${selectedClient?.name}. Are you sure?`,
            confirmText: 'Send Survey',
        });
        if (ok) {
            form.post('/client-surveys/send', { onSuccess: onClose });
        }
    };

    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 space-y-3">
                <div className="flex items-center justify-between">
                    <h2 className="text-[15px] font-bold text-gray-900">Send NPS Survey</h2>
                    <Button onClick={onClose}><X size={16} className="text-gray-400" /></Button>
                </div>
                <form onSubmit={handleSubmit} className="space-y-3">
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Client *</label>
                        <select value={form.data.client_id} onChange={e => form.setData('client_id', e.target.value)}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                            <option value="">Select client…</option>
                            {clients.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                        </select>
                    </div>
                    <div className="flex justify-end gap-2 pt-1">
                        <Button type="button" onClick={onClose} variant="ghost" >Cancel</Button>
                        <Button type="submit" disabled={form.processing || !form.data.client_id}
                            className="disabled:opacity-50" >
                            {form.processing ? 'Sending…' : 'Send Survey'}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export default function ClientSurveysIndex({ surveys, clients, npsStats }: Props) {
    const [sendOpen, setSendOpen] = useState(false);
    const confirm = useConfirm();

    const responded = surveys.filter(s => s.nps_score !== null);

    return (
        <AppLayout title="NPS Surveys">
            <Head title="NPS Surveys" />
            <div className="max-w-4xl space-y-5">
                <div className="flex items-center justify-between">
                    <h1 className="text-lg font-bold text-gray-900">NPS Surveys</h1>
                    <Button onClick={() => setSendOpen(true)}
                        className="flex items-center gap-2 px-3 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                        <Plus size={14} /> Send Survey
                    </Button>
                </div>

                <div className="grid grid-cols-4 gap-4">
                    <div className="bg-white rounded-xl border border-gray-200 p-4 text-center">
                        <p className="text-3xl font-bold text-gray-900">{npsStats.avg !== null ? npsStats.avg : '—'}</p>
                        <p className="text-xs text-gray-500 mt-1">Avg NPS Score</p>
                    </div>
                    <div className="bg-emerald-50 rounded-xl border border-emerald-200 p-4 text-center">
                        <p className="text-3xl font-bold text-emerald-700">{npsStats.promoters}</p>
                        <p className="text-xs text-emerald-600 mt-1">Promoters (9–10)</p>
                    </div>
                    <div className="bg-amber-50 rounded-xl border border-amber-200 p-4 text-center">
                        <p className="text-3xl font-bold text-amber-700">{npsStats.passives}</p>
                        <p className="text-xs text-amber-600 mt-1">Passives (7–8)</p>
                    </div>
                    <div className="bg-rose-50 rounded-xl border border-rose-200 p-4 text-center">
                        <p className="text-3xl font-bold text-rose-700">{npsStats.detractors}</p>
                        <p className="text-xs text-rose-600 mt-1">Detractors (0–6)</p>
                    </div>
                </div>

                <div className="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
                    {surveys.length === 0 && (
                        <div className="px-5 py-10 text-center">
                            <Smile size={24} className="text-gray-300 mx-auto mb-2" />
                            <p className="text-sm text-gray-400">No surveys sent yet.</p>
                        </div>
                    )}
                    {surveys.map(s => (
                        <div key={s.id} className="px-5 py-3.5 flex items-center gap-4">
                            <div className="flex-1 min-w-0">
                                <p className="text-sm font-medium text-gray-900">{s.client?.name ?? '—'}</p>
                                <p className="text-xs text-gray-500 mt-0.5">
                                    Sent {new Date(s.sent_at).toLocaleDateString('en-IN')}
                                    {s.responded_at ? ` · Responded ${new Date(s.responded_at).toLocaleDateString('en-IN')}` : ''}
                                </p>
                                {s.feedback && <p className="text-xs text-gray-600 mt-1 line-clamp-1 italic">"{s.feedback}"</p>}
                            </div>
                            <div className="flex items-center gap-3 shrink-0">
                                {s.nps_score !== null && (
                                    <span className={cn('w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold', scoreColor(s.nps_score))}>
                                        {s.nps_score}
                                    </span>
                                )}
                                <span className={cn('px-2 py-0.5 rounded-full text-[10px] font-semibold capitalize', STATUS_STYLES[s.status] ?? STATUS_STYLES.sent)}>
                                    {s.status}
                                </span>
                                <Button onClick={async () => {
                                    const ok = await confirm({
                                        title: 'Delete survey?',
                                        description: 'This action cannot be undone.',
                                        confirmText: 'Delete',
                                        variant: 'destructive',
                                    });
                                    if (!ok) return;
                                    router.delete(`/client-surveys/${s.id}`);
                                }}
                                    className="p-1 text-gray-400 hover:text-rose-500 rounded hover:bg-rose-50 transition-colors">
                                    <Trash2 size={13} />
                                </Button>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
            {sendOpen && <SendModal clients={clients} onClose={() => setSendOpen(false)} />}
        </AppLayout>
    );
}

import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import { Smile } from 'lucide-react';

interface Props { survey: { id: string; status: string; client_name: string }; token: string; submitted: boolean; }

export default function SurveyRespond({ survey, token, submitted: initialSubmitted }: Props) {
    const [score, setScore] = useState<number | null>(null);
    const [feedback, setFeedback] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [done, setDone] = useState(initialSubmitted);

    const scoreColor = (n: number) => {
        if (n >= 9) return 'bg-emerald-500 text-white';
        if (n >= 7) return 'bg-amber-500 text-white';
        return 'bg-rose-500 text-white';
    };
    const hoverColor = (n: number) => {
        if (n >= 9) return 'hover:bg-emerald-500 hover:text-white';
        if (n >= 7) return 'hover:bg-amber-500 hover:text-white';
        return 'hover:bg-rose-500 hover:text-white';
    };

    const submit = () => {
        if (score === null) return;
        setSubmitting(true);
        router.post(`/survey/${token}`, { nps_score: score, feedback }, {
            onSuccess: () => setDone(true),
            onFinish: () => setSubmitting(false),
        });
    };

    if (done) {
        return (
            <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
                <Head title="Survey — Thank You" />
                <div className="bg-white rounded-2xl shadow-xl w-full max-w-md p-8 text-center space-y-4">
                    <div className="w-14 h-14 rounded-full bg-emerald-100 flex items-center justify-center mx-auto">
                        <Smile size={24} className="text-emerald-600" />
                    </div>
                    <h1 className="text-xl font-bold text-gray-900">Thank You!</h1>
                    <p className="text-sm text-gray-500">
                        Your feedback has been recorded. We appreciate your time and will use it to improve our service.
                    </p>
                </div>
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
            <Head title="Rate Our Service" />
            <div className="bg-white rounded-2xl shadow-xl w-full max-w-md p-8 space-y-6">
                <div className="text-center">
                    <div className="w-12 h-12 rounded-xl bg-indigo-50 flex items-center justify-center mx-auto mb-3">
                        <Smile size={22} className="text-indigo-500" />
                    </div>
                    <h1 className="text-xl font-bold text-gray-900">How likely are you to recommend us?</h1>
                    {survey.client_name && (
                        <p className="text-sm text-gray-500 mt-1">Feedback for {survey.client_name}</p>
                    )}
                </div>

                <div>
                    <div className="flex justify-between text-xs text-gray-500 mb-2">
                        <span>Not likely</span>
                        <span>Very likely</span>
                    </div>
                    <div className="flex gap-1.5 justify-between">
                        {[0,1,2,3,4,5,6,7,8,9,10].map(n => (
                            <button key={n} onClick={() => setScore(n)}
                                className={cn(
                                    'w-9 h-9 rounded-lg text-sm font-semibold border transition-all',
                                    score === n ? scoreColor(n) + ' border-transparent' : `border-gray-200 text-gray-600 ${hoverColor(n)}`,
                                )}>
                                {n}
                            </button>
                        ))}
                    </div>
                </div>

                <div>
                    <label className="text-sm font-medium text-gray-700">Any additional comments? (optional)</label>
                    <textarea value={feedback} onChange={e => setFeedback(e.target.value)} rows={4}
                        placeholder="Share your thoughts…"
                        className="w-full mt-2 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 resize-none" />
                </div>

                <button onClick={submit} disabled={score === null || submitting}
                    className="w-full py-3 bg-indigo-600 text-white font-semibold rounded-xl hover:bg-indigo-700 disabled:opacity-50 transition-colors">
                    {submitting ? 'Submitting…' : 'Submit Feedback'}
                </button>
            </div>
        </div>
    );
}

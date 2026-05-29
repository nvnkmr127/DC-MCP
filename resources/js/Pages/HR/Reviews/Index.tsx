import React, { useState } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { cn } from '@/lib/utils';
import { Plus, X, Star } from 'lucide-react';

interface Review {
    id: string; period: string; year: number;
    overall_rating: number | null; technical_rating: number | null;
    communication_rating: number | null; teamwork_rating: number | null;
    strengths: string | null; improvements: string | null; goals_next: string | null;
    status: string; acknowledged_at: string | null;
    reviewer: { id: string; name: string } | null;
    reviewee: { id: string; name: string } | null;
}
interface Props { written: Review[]; received: Review[]; users: { id: string; name: string }[]; }

const STATUS_STYLES: Record<string, string> = {
    draft:        'bg-gray-100 text-gray-600',
    submitted:    'bg-amber-100 text-amber-700',
    acknowledged: 'bg-emerald-100 text-emerald-700',
};

const PERIOD_LABELS: Record<string, string> = { q1: 'Q1', q2: 'Q2', q3: 'Q3', q4: 'Q4', annual: 'Annual' };

function Stars({ rating }: { rating: number | null }) {
    if (!rating) return <span className="text-xs text-gray-400">Not rated</span>;
    return (
        <span className="flex items-center gap-0.5">
            {[1,2,3,4,5].map(i => <Star key={i} size={12} className={i <= rating ? 'text-amber-400 fill-amber-400' : 'text-gray-300'} />)}
        </span>
    );
}

function WriteReviewModal({ users, onClose }: { users: Props['users']; onClose: () => void }) {
    const form = useForm({
        reviewee_id: '', period: 'q1', year: new Date().getFullYear(),
        overall_rating: '', technical_rating: '', communication_rating: '', teamwork_rating: '',
        strengths: '', improvements: '', goals_next: '',
    });

    return (
        <div className="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4 overflow-y-auto">
            <div className="bg-white rounded-2xl shadow-xl w-full max-w-lg my-4">
                <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <h2 className="text-base font-semibold text-gray-900">Write Performance Review</h2>
                    <button onClick={onClose}><X size={16} className="text-gray-400" /></button>
                </div>
                <form onSubmit={e => { e.preventDefault(); form.post('/reviews', { onSuccess: () => { onClose(); form.reset(); } }); }} className="px-6 py-4 space-y-4">
                    <div className="grid grid-cols-2 gap-3">
                        <div className="col-span-2">
                            <label className="block text-xs font-medium text-gray-700 mb-1">Reviewee</label>
                            <select value={form.data.reviewee_id} onChange={e => form.setData('reviewee_id', e.target.value)} required
                                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                <option value="">Select team member…</option>
                                {users.map(u => <option key={u.id} value={u.id}>{u.name}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-700 mb-1">Period</label>
                            <select value={form.data.period} onChange={e => form.setData('period', e.target.value)}
                                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                {Object.entries(PERIOD_LABELS).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-700 mb-1">Year</label>
                            <input type="number" value={form.data.year} onChange={e => form.setData('year', Number(e.target.value))}
                                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        {[
                            { key: 'overall_rating', label: 'Overall Rating' },
                            { key: 'technical_rating', label: 'Technical' },
                            { key: 'communication_rating', label: 'Communication' },
                            { key: 'teamwork_rating', label: 'Teamwork' },
                        ].map(({ key, label }) => (
                            <div key={key}>
                                <label className="block text-xs font-medium text-gray-700 mb-1">{label} (1-5)</label>
                                <input type="number" min={1} max={5} value={(form.data as any)[key]}
                                    onChange={e => form.setData(key as any, e.target.value)}
                                    className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                            </div>
                        ))}
                    </div>
                    {[
                        { key: 'strengths', label: 'Strengths' },
                        { key: 'improvements', label: 'Areas for Improvement' },
                        { key: 'goals_next', label: 'Goals for Next Period' },
                    ].map(({ key, label }) => (
                        <div key={key}>
                            <label className="block text-xs font-medium text-gray-700 mb-1">{label}</label>
                            <textarea value={(form.data as any)[key]} onChange={e => form.setData(key as any, e.target.value)} rows={2}
                                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 resize-none" />
                        </div>
                    ))}
                    <div className="flex justify-end gap-3 pt-2">
                        <button type="button" onClick={onClose} className="px-4 py-2 text-sm text-gray-600">Cancel</button>
                        <button type="submit" disabled={form.processing}
                            className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                            {form.processing ? 'Saving…' : 'Save Review'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

function ReviewCard({ review, showActions }: { review: Review; showActions: boolean }) {
    const [expanded, setExpanded] = useState(false);
    return (
        <div className="border border-gray-200 rounded-xl p-4 bg-white">
            <div className="flex items-start justify-between">
                <div className="flex-1">
                    <div className="flex items-center gap-2 flex-wrap">
                        <span className="text-xs font-bold bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded-full">
                            {PERIOD_LABELS[review.period]} {review.year}
                        </span>
                        <span className={cn('px-2 py-0.5 rounded-full text-xs font-medium', STATUS_STYLES[review.status])}>{review.status}</span>
                    </div>
                    <p className="text-sm font-medium text-gray-900 mt-1">
                        {review.reviewer?.name} → {review.reviewee?.name}
                    </p>
                    <div className="flex items-center gap-3 mt-1 flex-wrap">
                        <Stars rating={review.overall_rating} />
                        {review.overall_rating && <span className="text-xs text-gray-400">Overall {review.overall_rating}/5</span>}
                    </div>
                </div>
                <button onClick={() => setExpanded(!expanded)} className="text-xs text-indigo-600 hover:text-indigo-800 ml-2">
                    {expanded ? 'Collapse' : 'Expand'}
                </button>
            </div>
            {expanded && (
                <div className="mt-3 pt-3 border-t border-gray-100 space-y-2 text-sm text-gray-700">
                    {review.strengths && <div><span className="font-medium text-gray-500 text-xs">Strengths: </span>{review.strengths}</div>}
                    {review.improvements && <div><span className="font-medium text-gray-500 text-xs">Improvements: </span>{review.improvements}</div>}
                    {review.goals_next && <div><span className="font-medium text-gray-500 text-xs">Goals: </span>{review.goals_next}</div>}
                    {showActions && (
                        <div className="flex gap-2 pt-2">
                            {review.status === 'draft' && (
                                <button onClick={() => router.post(`/reviews/${review.id}/submit`)}
                                    className="px-3 py-1.5 bg-indigo-600 text-white text-xs rounded-lg hover:bg-indigo-700">Submit</button>
                            )}
                            {review.status === 'submitted' && (
                                <button onClick={() => router.post(`/reviews/${review.id}/acknowledge`)}
                                    className="px-3 py-1.5 bg-emerald-600 text-white text-xs rounded-lg hover:bg-emerald-700">Acknowledge</button>
                            )}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

export default function ReviewsIndex({ written, received, users }: Props) {
    const [showWrite, setShowWrite] = useState(false);

    return (
        <AppLayout title="Performance Reviews">
            <Head title="Performance Reviews" />
            <div className="max-w-4xl mx-auto px-4 py-6 space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Performance Reviews</h1>
                        <p className="text-sm text-gray-500 mt-0.5">Quarterly and annual performance tracking</p>
                    </div>
                    <button onClick={() => setShowWrite(true)}
                        className="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                        <Plus size={14} /> Write Review
                    </button>
                </div>

                {written.length > 0 && (
                    <div className="space-y-3">
                        <h2 className="text-sm font-semibold text-gray-700">Reviews I've Written</h2>
                        {written.map(r => <ReviewCard key={r.id} review={r} showActions={true} />)}
                    </div>
                )}

                {received.length > 0 && (
                    <div className="space-y-3">
                        <h2 className="text-sm font-semibold text-gray-700">My Performance Reviews</h2>
                        {received.map(r => <ReviewCard key={r.id} review={r} showActions={r.status === 'submitted'} />)}
                    </div>
                )}

                {written.length === 0 && received.length === 0 && (
                    <div className="bg-white rounded-xl border border-dashed border-gray-300 p-12 text-center">
                        <p className="text-gray-400 text-sm">No reviews yet.</p>
                    </div>
                )}
            </div>
            {showWrite && <WriteReviewModal users={users} onClose={() => setShowWrite(false)} />}
        </AppLayout>
    );
}

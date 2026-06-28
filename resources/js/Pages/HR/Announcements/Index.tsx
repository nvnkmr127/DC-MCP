import React, { useState } from 'react';
import { Button } from '@/Components/ui/Button';
import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { cn } from '@/lib/utils';
import { useConfirm } from '@/hooks/useConfirm';
import { Pin, Trash2, Plus, X, Megaphone, Search } from 'lucide-react';

interface Announcement {
    id: string; title: string; body: string; is_pinned: boolean;
    published_at: string | null; expires_at: string | null; created_at: string;
    author: { id: string; name: string };
}
interface Props { announcements: Announcement[]; canPost: boolean; }

function timeAgo(iso: string) {
    const diff = Date.now() - new Date(iso).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 60) return `${mins}m ago`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `${hrs}h ago`;
    return `${Math.floor(hrs / 24)}d ago`;
}

function PostModal({ onClose }: { onClose: () => void }) {
    const form = useForm({ title: '', body: '', is_pinned: false, expires_at: '' });
    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 space-y-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-[15px] font-bold text-gray-900">Post Announcement</h2>
                    <Button onClick={onClose}><X size={16} className="text-gray-400" /></Button>
                </div>
                <form onSubmit={e => { e.preventDefault(); form.post('/announcements', { onSuccess: onClose }); }} className="space-y-3">
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Title *</label>
                        <input type="text" value={form.data.title} onChange={e => form.setData('title', e.target.value)}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Body *</label>
                        <textarea value={form.data.body} onChange={e => form.setData('body', e.target.value)} rows={5}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 resize-none" />
                    </div>
                    <div className="flex gap-4">
                        <label className="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                            <input type="checkbox" checked={form.data.is_pinned} onChange={e => form.setData('is_pinned', e.target.checked)} className="rounded" />
                            Pin to top
                        </label>
                        <div className="flex-1">
                            <label className="text-xs text-gray-500 font-medium">Expires (optional)</label>
                            <input type="date" value={form.data.expires_at} onChange={e => form.setData('expires_at', e.target.value)}
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                        </div>
                    </div>
                    <div className="flex justify-end gap-2 pt-1">
                        <Button type="button" onClick={onClose} variant="ghost" >Cancel</Button>
                        <Button type="submit" disabled={form.processing || !form.data.title || !form.data.body}
                            className="disabled:opacity-50" >
                            {form.processing ? 'Posting…' : 'Post'}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export default function AnnouncementsIndex({ announcements, canPost }: Props) {
    const [open, setOpen] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');

    const filteredAnnouncements = announcements.filter(a => 
        (a.title || '').toLowerCase().includes(searchQuery.toLowerCase()) || 
        (a.body || '').toLowerCase().includes(searchQuery.toLowerCase())
    );

    const pinned = filteredAnnouncements.filter(a => a.is_pinned);
    const rest = filteredAnnouncements.filter(a => !a.is_pinned);

    return (
        <AppLayout title="Announcements">
            <Head title="Announcements" />
            <div className="max-w-3xl space-y-5">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-lg font-bold text-gray-900">Team Announcements</h1>
                        <p className="text-sm text-gray-500 mt-0.5">{announcements.length} active announcement{announcements.length !== 1 ? 's' : ''}</p>
                    </div>
                    <div className="flex items-center gap-3">
                        <div className="relative">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 w-4 h-4" />
                            <input
                                type="text"
                                placeholder="Search announcements..."
                                value={searchQuery}
                                onChange={e => setSearchQuery(e.target.value)}
                                className="pl-9 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 w-64"
                            />
                        </div>
                        {canPost && (
                            <Button onClick={() => setOpen(true)}
                                className="flex items-center gap-2 px-3 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                                <Plus size={14} /> Post Announcement
                            </Button>
                        )}
                    </div>
                </div>

                {pinned.length > 0 && (
                    <div className="space-y-3">
                        <h2 className="text-xs font-semibold text-amber-700 uppercase tracking-wider flex items-center gap-1.5">
                            <Pin size={11} /> Pinned
                        </h2>
                        {pinned.map(a => (
                            <AnnouncementCard key={a.id} announcement={a} canPost={canPost} pinned />
                        ))}
                    </div>
                )}

                {rest.length > 0 && (
                    <div className="space-y-3">
                        {pinned.length > 0 && <h2 className="text-xs font-semibold text-gray-500 uppercase tracking-wider">Recent</h2>}
                        {rest.map(a => (
                            <AnnouncementCard key={a.id} announcement={a} canPost={canPost} pinned={false} />
                        ))}
                    </div>
                )}

                {announcements.length === 0 && (
                    <div className="bg-white rounded-xl border border-dashed border-gray-200 px-5 py-12 text-center">
                        <Megaphone size={24} className="text-gray-300 mx-auto mb-2" />
                        <p className="text-sm text-gray-400">No announcements yet.</p>
                    </div>
                )}
            </div>
            {open && <PostModal onClose={() => setOpen(false)} />}
        </AppLayout>
    );
}

function AnnouncementCard({ announcement: a, canPost, pinned }: { announcement: Announcement; canPost: boolean; pinned: boolean }) {
    const confirm = useConfirm();

    return (
        <div className={cn('bg-white rounded-xl border p-5', pinned ? 'border-amber-200 bg-amber-50/30' : 'border-gray-200')}>
            <div className="flex items-start justify-between gap-3">
                <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 mb-1">
                        {pinned && <Pin size={11} className="text-amber-500 shrink-0" />}
                        <p className="text-sm font-semibold text-gray-900">{a.title}</p>
                    </div>
                    <p className="text-xs text-gray-500 mb-3">{a.author.name} · {timeAgo(a.created_at)}</p>
                    <p className="text-sm text-gray-700 leading-relaxed whitespace-pre-wrap">{a.body}</p>
                    {a.expires_at && (
                        <p className="text-xs text-gray-400 mt-2">Expires {new Date(a.expires_at).toLocaleDateString('en-IN')}</p>
                    )}
                </div>
                {canPost && (
                    <Button onClick={async () => {
                        const ok = await confirm({
                            title: 'Delete this announcement?',
                            description: 'This action cannot be undone.',
                            confirmText: 'Delete',
                            variant: 'destructive',
                        });
                        if (!ok) return;
                        router.delete(`/announcements/${a.id}`);
                    }}
                        className="p-1.5 text-gray-400 hover:text-rose-500 rounded hover:bg-rose-50 transition-colors shrink-0">
                        <Trash2 size={13} />
                    </Button>
                )}
            </div>
        </div>
    );
}

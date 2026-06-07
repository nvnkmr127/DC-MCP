import React, { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useConfirm } from '@/hooks/useConfirm';
import {
    PenTool, Plus, Filter, Globe, FileText,
    Megaphone, Calendar, Clock, CheckCircle2, Eye, Edit3,
    Trash2, ArrowRight, Tag, X, ChevronDown,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { InstagramIcon, YoutubeIcon } from '@/lib/constants';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/Components/ui/Dialog';
import { Button } from '@/Components/ui/Button';
import { Input, Label, TextArea } from '@/Components/ui/Input';

type ContentType = 'social_post' | 'blog' | 'ad_campaign';
type Platform = 'instagram' | 'facebook' | 'twitter' | 'linkedin' | 'youtube' | 'website' | 'google_ads' | 'meta_ads' | 'email';
type Status = 'idea' | 'in_progress' | 'in_review' | 'approved' | 'scheduled' | 'published' | 'cancelled';

interface ContentItem {
    id: string;
    title: string;
    body: string | null;
    type: ContentType;
    platform: Platform | null;
    status: Status;
    due_date: string | null;
    scheduled_at: string | null;
    published_at: string | null;
    tags: string[];
    client: { id: string; name: string } | null;
    project: { id: string; name: string } | null;
    assignee: { id: string; name: string } | null;
    task_id: string | null;
}

interface Props {
    calendarItems: ContentItem[];
    listItems: { data: ContentItem[]; current_page: number; last_page: number };
    stats: { total: number; in_review: number; approved: number; scheduled: number; published: number };
    clients: { id: string; name: string; company: string }[];
    projects: { id: string; name: string; client_id: string }[];
    filters: { clientId: string | null; type: string | null; status: string | null; month: string };
}

const STATUS_STYLES: Record<Status, string> = {
    idea:        'bg-gray-100 text-gray-600',
    in_progress: 'bg-blue-100 text-blue-700',
    in_review:   'bg-yellow-100 text-yellow-700',
    approved:    'bg-emerald-100 text-emerald-700',
    scheduled:   'bg-purple-100 text-purple-700',
    published:   'bg-green-100 text-green-700',
    cancelled:   'bg-red-100 text-red-400',
};

const STATUS_FLOW: Status[] = ['idea', 'in_progress', 'in_review', 'approved', 'scheduled', 'published'];

const TYPE_ICONS: Record<ContentType, React.ElementType> = {
    social_post:  InstagramIcon,
    blog:         FileText,
    ad_campaign:  Megaphone,
};

const PLATFORM_COLORS: Record<string, string> = {
    instagram: 'text-pink-500',
    facebook:  'text-blue-600',
    twitter:   'text-sky-500',
    linkedin:  'text-blue-700',
    youtube:   'text-red-500',
    website:   'text-gray-600',
    google_ads: 'text-yellow-500',
    meta_ads:  'text-blue-500',
    email:     'text-gray-500',
};

function CreateModal({ clients, projects, onClose }: {
    clients: Props['clients'];
    projects: Props['projects'];
    onClose: () => void;
}) {
    const { data, setData, post, processing, errors } = useForm({
        client_id:  '',
        project_id: '',
        title:      '',
        body:       '',
        type:       'social_post' as ContentType,
        platform:   '' as Platform | '',
        status:     'idea' as Status,
        due_date:   '',
        tags:       [] as string[],
    });

    const filteredProjects = data.client_id
        ? projects.filter(p => p.client_id === data.client_id)
        : projects;

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/content', { onSuccess: onClose });
    };

    return (
        <Dialog open={true} onOpenChange={(open) => { if (!open) onClose(); }}>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle>New Content Item</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <Label>Client *</Label>
                            <select className="w-full text-sm border rounded-xl p-2 border-gray-200 bg-white" value={data.client_id} onChange={e => setData('client_id', e.target.value)} required>
                                <option value="">Select client</option>
                                {clients.map(c => <option key={c.id} value={c.id}>{c.company || c.name}</option>)}
                            </select>
                        </div>
                        <div>
                            <Label>Project</Label>
                            <select className="w-full text-sm border rounded-xl p-2 border-gray-200 bg-white" value={data.project_id} onChange={e => setData('project_id', e.target.value)}>
                                <option value="">No project</option>
                                {filteredProjects.map(p => <option key={p.id} value={p.id}>{p.name}</option>)}
                            </select>
                        </div>
                    </div>

                    <div>
                        <Label>Title *</Label>
                        <Input placeholder="Content title or campaign name" value={data.title} onChange={e => setData('title', e.target.value)} required />
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <Label>Type</Label>
                            <select className="w-full text-sm border rounded-xl p-2 border-gray-200 bg-white" value={data.type} onChange={e => setData('type', e.target.value as ContentType)}>
                                <option value="social_post">Social Post</option>
                                <option value="blog">Blog / Article</option>
                                <option value="ad_campaign">Ad Campaign</option>
                            </select>
                        </div>
                        <div>
                            <Label>Platform</Label>
                            <select className="w-full text-sm border rounded-xl p-2 border-gray-200 bg-white" value={data.platform} onChange={e => setData('platform', e.target.value as Platform)}>
                                <option value="">Any</option>
                                {['instagram','facebook','twitter','linkedin','youtube','website','google_ads','meta_ads','email'].map(p => (
                                    <option key={p} value={p}>{p.replace('_', ' ')}</option>
                                ))}
                            </select>
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <Label>Due Date</Label>
                            <Input type="date" value={data.due_date} onChange={e => setData('due_date', e.target.value)} />
                        </div>
                        <div>
                            <Label>Status</Label>
                            <select className="w-full text-sm border rounded-xl p-2 border-gray-200 bg-white" value={data.status} onChange={e => setData('status', e.target.value as Status)}>
                                {STATUS_FLOW.map(s => <option key={s} value={s}>{s.replace('_', ' ')}</option>)}
                            </select>
                        </div>
                    </div>

                    <div>
                        <Label>Brief / Notes</Label>
                        <TextArea rows={3} placeholder="Content brief, copy, or notes..." value={data.body ?? ''} onChange={e => setData('body', e.target.value)} />
                    </div>

                    <div className="flex gap-2 pt-2">
                        <Button type="submit" loading={processing} className="flex-1">
                            Create Item
                        </Button>
                        <Button type="button" variant="ghost" onClick={onClose}>
                            Cancel
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}


function ContentCard({ item }: { item: ContentItem }) {
    const [open, setOpen] = useState(false);
    const confirm = useConfirm();
    const TypeIcon = TYPE_ICONS[item.type];
    const today = new Date().toISOString().split('T')[0];
    const isOverdue = item.due_date && item.due_date < today && !['published', 'cancelled'].includes(item.status);

    const advance = () => {
        const idx = STATUS_FLOW.indexOf(item.status);
        if (idx < STATUS_FLOW.length - 1) {
            router.patch(`/content/${item.id}`, { status: STATUS_FLOW[idx + 1] }, { preserveScroll: true });
        }
    };

    const convertToTask = () => {
        router.post(`/content/${item.id}/convert-to-task`, {}, { preserveScroll: true });
    };

    return (
        <div className={cn(
            'bg-white rounded-xl border p-4 shadow-sm hover:shadow-md transition-all',
            isOverdue && 'border-red-200 bg-red-50/30',
        )}>
            <div className="flex items-start gap-3">
                <div className="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center flex-shrink-0">
                    <TypeIcon className="w-4 h-4 text-indigo-500" />
                </div>
                <div className="flex-1 min-w-0">
                    <p className="text-sm font-semibold text-gray-900 truncate">{item.title}</p>
                    <div className="flex flex-wrap gap-1.5 mt-1.5">
                        <span className={cn('text-xs font-medium px-2 py-0.5 rounded-full', STATUS_STYLES[item.status])}>
                            {item.status.replace('_', ' ')}
                        </span>
                        {item.platform && (
                            <span className={cn('text-xs font-medium', PLATFORM_COLORS[item.platform])}>
                                {item.platform}
                            </span>
                        )}
                        {item.client && (
                            <span className="text-xs text-emerald-700 font-medium">{item.client.name}</span>
                        )}
                    </div>
                    {(item.due_date || item.scheduled_at) && (
                        <div className="flex gap-3 mt-1.5 text-xs text-gray-400">
                            {item.due_date && (
                                <span className={cn('flex items-center gap-1', isOverdue && 'text-red-500 font-medium')}>
                                    <Calendar className="w-3 h-3" /> Due {item.due_date}
                                </span>
                            )}
                            {item.scheduled_at && (
                                <span className="flex items-center gap-1">
                                    <Clock className="w-3 h-3" /> {new Date(item.scheduled_at).toLocaleDateString()}
                                </span>
                            )}
                        </div>
                    )}
                </div>
                <button onClick={() => setOpen(o => !o)} className="p-1 text-gray-400 hover:text-gray-600">
                    <ChevronDown className={cn('w-4 h-4 transition-transform', open && 'rotate-180')} />
                </button>
            </div>

            {open && (
                <div className="mt-3 pt-3 border-t border-gray-100 space-y-2">
                    {item.body && <p className="text-xs text-gray-600 leading-relaxed">{item.body}</p>}
                    <div className="flex gap-2 flex-wrap">
                        {!['published', 'cancelled'].includes(item.status) && (
                            <button
                                onClick={advance}
                                className="flex items-center gap-1 text-xs px-2.5 py-1 bg-indigo-50 text-indigo-700 rounded-lg hover:bg-indigo-100"
                            >
                                <ArrowRight className="w-3 h-3" />
                                Move to {STATUS_FLOW[STATUS_FLOW.indexOf(item.status) + 1]?.replace('_', ' ')}
                            </button>
                        )}
                        {!item.task_id && (
                            <button
                                onClick={convertToTask}
                                className="flex items-center gap-1 text-xs px-2.5 py-1 bg-emerald-50 text-emerald-700 rounded-lg hover:bg-emerald-100"
                            >
                                <CheckCircle2 className="w-3 h-3" /> Create Task
                            </button>
                        )}
                        {item.task_id && (
                            <a href={`/tasks/${item.task_id}`} className="flex items-center gap-1 text-xs px-2.5 py-1 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200">
                                <Eye className="w-3 h-3" /> View Task
                            </a>
                        )}
                        <button
                            onClick={async () => {
                                const ok = await confirm({
                                    title: 'Delete this content item?',
                                    description: 'This cannot be undone.',
                                    confirmText: 'Delete',
                                    variant: 'destructive',
                                });
                                if (!ok) return;
                                router.delete(`/content/${item.id}`, { preserveScroll: true });
                            }}
                            className="flex items-center gap-1 text-xs px-2.5 py-1 text-red-400 hover:text-red-600 ml-auto"
                        >
                            <Trash2 className="w-3 h-3" /> Delete
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}

export default function ContentCalendarIndex({ calendarItems, listItems, stats, clients, projects, filters }: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const [view, setView] = useState<'list' | 'board'>('board');

    const grouped = listItems.data.reduce((acc, item) => {
        (acc[item.status] = acc[item.status] || []).push(item);
        return acc;
    }, {} as Record<string, ContentItem[]>);

    const applyFilter = (key: string, value: string) => {
        const params = new URLSearchParams(window.location.search);
        if (value) params.set(key, value); else params.delete(key);
        router.get('/content?' + params.toString(), {}, { preserveState: true });
    };

    return (
        <AppLayout title="Content Calendar">
            <Head title="Content Calendar" />

            {createOpen && <CreateModal clients={clients} projects={projects} onClose={() => setCreateOpen(false)} />}

            <div className="max-w-7xl mx-auto px-4 py-6 space-y-5">

                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <PenTool className="w-5 h-5 text-indigo-500" />
                        <h1 className="text-xl font-bold text-gray-900">Content Calendar</h1>
                    </div>
                    <Button
                        onClick={() => setCreateOpen(true)}
                        size="sm"
                    >
                        <Plus className="w-4 h-4 mr-1.5" /> New Content
                    </Button>
                </div>

                {/* Stats */}
                <div className="grid grid-cols-5 gap-3">
                    {[
                        { label: 'Total', value: stats.total,     color: 'text-gray-700' },
                        { label: 'In Review', value: stats.in_review, color: 'text-yellow-600' },
                        { label: 'Approved', value: stats.approved,  color: 'text-emerald-600' },
                        { label: 'Scheduled', value: stats.scheduled, color: 'text-purple-600' },
                        { label: 'Published', value: stats.published, color: 'text-green-600' },
                    ].map(s => (
                        <div key={s.label} className="bg-white rounded-xl border border-gray-200 p-3 text-center">
                            <p className={cn('text-xl font-bold', s.color)}>{s.value}</p>
                            <p className="text-xs text-gray-400 mt-0.5">{s.label}</p>
                        </div>
                    ))}
                </div>

                {/* Filters */}
                <div className="flex gap-3 flex-wrap">
                    <select className="text-sm border rounded-lg px-3 py-1.5 bg-white" value={filters.clientId ?? ''} onChange={e => applyFilter('client_id', e.target.value)}>
                        <option value="">All clients</option>
                        {clients.map(c => <option key={c.id} value={c.id}>{c.company || c.name}</option>)}
                    </select>
                    <select className="text-sm border rounded-lg px-3 py-1.5 bg-white" value={filters.type ?? ''} onChange={e => applyFilter('type', e.target.value)}>
                        <option value="">All types</option>
                        <option value="social_post">Social Posts</option>
                        <option value="blog">Blogs</option>
                        <option value="ad_campaign">Ad Campaigns</option>
                    </select>
                    <select className="text-sm border rounded-lg px-3 py-1.5 bg-white" value={filters.status ?? ''} onChange={e => applyFilter('status', e.target.value)}>
                        <option value="">All statuses</option>
                        {STATUS_FLOW.map(s => <option key={s} value={s}>{s.replace('_', ' ')}</option>)}
                    </select>
                </div>

                {/* Board View */}
                <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-3">
                    {(['idea', 'in_progress', 'in_review', 'approved', 'scheduled', 'published'] as Status[]).map(status => (
                        <div key={status} className="space-y-2">
                            <div className="flex items-center justify-between mb-2">
                                <span className={cn('text-xs font-semibold px-2 py-0.5 rounded-full', STATUS_STYLES[status])}>
                                    {status.replace('_', ' ')}
                                </span>
                                <span className="text-xs text-gray-400">{(grouped[status] ?? []).length}</span>
                            </div>
                            {(grouped[status] ?? []).map(item => (
                                <ContentCard key={item.id} item={item} />
                            ))}
                        </div>
                    ))}
                </div>

            </div>
        </AppLayout>
    );
}

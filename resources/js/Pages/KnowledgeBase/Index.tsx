import React, { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { cn } from '@/lib/utils';
import { Plus, X, Search, BookOpen, Eye } from 'lucide-react';

interface Article {
    id: string; title: string; body: string; category: string | null; tags: string[];
    view_count: number; created_at: string; author: { id: string; name: string | null };
}
interface Props { articles: Article[]; categories: string[]; filters: Record<string, string>; }

function ArticleModal({ onClose }: { onClose: () => void }) {
    const form = useForm({ title: '', body: '', category: '', tags: '' });
    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 space-y-3">
                <div className="flex items-center justify-between">
                    <h2 className="text-[15px] font-bold text-gray-900">New Article</h2>
                    <button onClick={onClose}><X size={16} className="text-gray-400" /></button>
                </div>
                <form onSubmit={e => { e.preventDefault(); form.post('/knowledge-base', { onSuccess: onClose }); }} className="space-y-3">
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Title *</label>
                        <input type="text" value={form.data.title} onChange={e => form.setData('title', e.target.value)}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Category</label>
                            <input type="text" value={form.data.category} onChange={e => form.setData('category', e.target.value)}
                                placeholder="e.g. SOPs, Client Tips"
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 font-medium">Tags (comma-separated)</label>
                            <input type="text" value={form.data.tags} onChange={e => form.setData('tags', e.target.value)}
                                placeholder="e.g. SEO, Process"
                                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                        </div>
                    </div>
                    <div>
                        <label className="text-xs text-gray-500 font-medium">Body *</label>
                        <textarea value={form.data.body} onChange={e => form.setData('body', e.target.value)} rows={8}
                            className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 resize-none font-mono text-xs" />
                    </div>
                    <div className="flex justify-end gap-2 pt-1">
                        <button type="button" onClick={onClose} className="px-4 py-2 text-sm text-gray-600">Cancel</button>
                        <button type="submit" disabled={form.processing || !form.data.title || !form.data.body}
                            className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                            {form.processing ? 'Publishing…' : 'Publish Article'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export default function KnowledgeBaseIndex({ articles, categories, filters }: Props) {
    const [modalOpen, setModalOpen] = useState(false);
    const [search, setSearch] = useState(filters?.search ?? '');
    const [catFilter, setCatFilter] = useState(filters?.category ?? '');
    const [tagFilter, setTagFilter] = useState<string | null>(null);

    const isFiltered = (filters?.search && filters.search !== '') || (filters?.category && filters.category !== '') || tagFilter !== null;

    const applyFilter = () => {
        router.get('/knowledge-base', { search, category: catFilter }, { preserveState: true });
    };

    const allTags = Array.from(new Set(articles.flatMap(a => a.tags))).sort();
    const filteredArticles = tagFilter 
        ? articles.filter(a => a.tags.includes(tagFilter))
        : articles;

    return (
        <AppLayout title="Knowledge Base">
            <Head title="Knowledge Base" />
            <div className="max-w-4xl space-y-5">
                <div className="flex items-center justify-between">
                    <h1 className="text-lg font-bold text-gray-900">Knowledge Base</h1>
                    <button onClick={() => setModalOpen(true)}
                        className="flex items-center gap-2 px-3 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                        <Plus size={14} /> New Article
                    </button>
                </div>

                <div className="flex items-center gap-3">
                    <div className="flex-1 flex items-center gap-2 bg-white border border-gray-200 rounded-lg px-3 py-2">
                        <Search size={14} className="text-gray-400" />
                        <input type="text" placeholder="Search articles…" value={search}
                            onChange={e => setSearch(e.target.value)}
                            onKeyDown={e => e.key === 'Enter' && applyFilter()}
                            className="flex-1 text-sm text-gray-900 placeholder-gray-400 outline-none" />
                    </div>
                    <div className="flex items-center gap-2">
                        {['', ...(categories || [])].map(cat => (
                            <button key={cat} onClick={() => { setCatFilter(cat); router.get('/knowledge-base', { search, category: cat }, { preserveState: true }); }}
                                className={cn('px-3 py-1.5 text-xs rounded-lg font-medium transition-colors',
                                    catFilter === cat ? 'bg-indigo-600 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50')}>
                                {cat === '' ? 'All' : cat}
                            </button>
                        ))}
                    </div>
                </div>

                {allTags.length > 0 && (
                    <div className="flex items-center gap-2 flex-wrap pb-2 border-b border-gray-100">
                        <span className="text-xs font-semibold text-gray-500 mr-2">Tags:</span>
                        <button onClick={() => setTagFilter(null)}
                            className={cn('px-2 py-1 text-[11px] rounded-md font-medium transition-colors border',
                                tagFilter === null ? 'bg-indigo-50 border-indigo-200 text-indigo-700' : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50')}>
                            All Tags
                        </button>
                        {allTags.map(tag => (
                            <button key={tag} onClick={() => setTagFilter(tag)}
                                className={cn('px-2 py-1 text-[11px] rounded-md font-medium transition-colors border',
                                    tagFilter === tag ? 'bg-indigo-50 border-indigo-200 text-indigo-700' : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50')}>
                                {tag}
                            </button>
                        ))}
                    </div>
                )}

                <div className="grid grid-cols-2 gap-3">
                    {articles.length === 0 && (
                        <div className="col-span-2 bg-white rounded-xl border border-gray-200 px-5 py-16 text-center shadow-sm">
                            <div className="w-12 h-12 rounded-xl bg-gray-50 border border-gray-100 flex items-center justify-center mx-auto mb-4">
                                <BookOpen size={20} className="text-gray-400" />
                            </div>
                            {isFiltered ? (
                                <>
                                    <p className="text-[14px] font-semibold text-gray-900 mb-1">No articles match your search</p>
                                    <p className="text-[13px] text-gray-500 mb-5">Try using different keywords or clearing your filters.</p>
                                    <button onClick={() => { setSearch(''); setCatFilter(''); setTagFilter(null); router.get('/knowledge-base', {}, { preserveState: true }); }} className="px-4 py-2 bg-white border border-gray-200 rounded-lg text-[13px] font-semibold text-gray-700 hover:bg-gray-50 transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1">
                                        Clear filters
                                    </button>
                                </>
                            ) : (
                                <>
                                    <p className="text-[14px] font-semibold text-gray-900 mb-1">Your knowledge base is empty</p>
                                    <p className="text-[13px] text-gray-500 mb-6">Create your first article to start sharing knowledge, SOPs, and documentation with your team.</p>
                                    <button onClick={() => setModalOpen(true)} className="px-4 py-2 bg-indigo-600 border border-transparent rounded-lg text-[13px] font-semibold text-white hover:bg-indigo-700 transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1">
                                        Create New Article
                                    </button>
                                </>
                            )}
                        </div>
                    )}
                    {filteredArticles.map(a => (
                        <Link key={a.id} href={`/knowledge-base/${a.id}`}
                            className="bg-white rounded-xl border border-gray-200 p-4 hover:border-indigo-200 hover:shadow-sm transition-all block">
                            <div className="flex items-start justify-between gap-2 mb-2">
                                <p className="text-sm font-semibold text-gray-900 leading-tight">{a.title}</p>
                                {a.category && (
                                    <span className="px-1.5 py-0.5 bg-indigo-50 text-indigo-700 rounded text-[10px] font-medium shrink-0">
                                        {a.category}
                                    </span>
                                )}
                            </div>
                            <p className="text-xs text-gray-500 mb-2 line-clamp-2">{a.body.slice(0, 120)}{a.body.length > 120 ? '…' : ''}</p>
                            <div className="flex items-center justify-between text-[10px] text-gray-400">
                                <span>{a.author.name ?? 'Unknown'} · {a.created_at}</span>
                                <span className="flex items-center gap-1"><Eye size={9} /> {a.view_count}</span>
                            </div>
                            {a.tags.length > 0 && (
                                <div className="flex flex-wrap gap-1 mt-2">
                                    {a.tags.map(tag => (
                                        <button 
                                            key={tag} 
                                            onClick={(e) => { e.preventDefault(); setTagFilter(tag); }}
                                            className="px-1.5 py-0.5 bg-gray-100 text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 rounded text-[9px] transition-colors"
                                        >
                                            {tag}
                                        </button>
                                    ))}
                                </div>
                            )}
                        </Link>
                    ))}
                </div>
            </div>
            {modalOpen && <ArticleModal onClose={() => setModalOpen(false)} />}
        </AppLayout>
    );
}

import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useConfirm } from '@/hooks/useConfirm';
import { ArrowLeft, Eye, Trash2 } from 'lucide-react';

interface Article {
    id: string; title: string; body: string; category: string | null; tags: string[];
    view_count: number; created_at: string; author: { id: string; name: string | null };
}
interface Props { article: Article; }

export default function KnowledgeBaseShow({ article }: Props) {
    const confirm = useConfirm();

    return (
        <AppLayout title={article.title}>
            <Head title={article.title} />
            <div className="max-w-3xl space-y-5">
                <div className="flex items-center justify-between">
                    <Link href="/knowledge-base" className="flex items-center gap-1.5 text-sm text-gray-500 hover:text-indigo-600 w-fit">
                        <ArrowLeft size={14} /> Knowledge Base
                    </Link>
                    <button onClick={async () => {
                        const ok = await confirm({
                            title: 'Delete article?',
                            description: 'This action cannot be undone.',
                            confirmText: 'Delete',
                            variant: 'destructive',
                        });
                        if (!ok) return;
                        router.delete(`/knowledge-base/${article.id}`);
                    }}
                        className="p-1.5 text-gray-400 hover:text-rose-500 rounded hover:bg-rose-50 transition-colors">
                        <Trash2 size={14} />
                    </button>
                </div>
                <div className="bg-white rounded-xl border border-gray-200 p-6">
                    <div className="mb-4">
                        {article.category && (
                            <span className="px-2 py-0.5 bg-indigo-50 text-indigo-700 rounded text-xs font-medium mb-2 inline-block">
                                {article.category}
                            </span>
                        )}
                        <h1 className="text-xl font-bold text-gray-900 mt-1">{article.title}</h1>
                        <div className="flex items-center gap-3 mt-1 text-xs text-gray-500">
                            <span>{article.author.name ?? 'Unknown'}</span>
                            <span>·</span>
                            <span>{article.created_at}</span>
                            <span>·</span>
                            <span className="flex items-center gap-1"><Eye size={11} /> {article.view_count} views</span>
                        </div>
                    </div>
                    <div className="prose prose-sm max-w-none">
                        <p className="text-gray-700 whitespace-pre-wrap leading-relaxed">{article.body}</p>
                    </div>
                    {article.tags.length > 0 && (
                        <div className="flex flex-wrap gap-1.5 mt-4 pt-4 border-t border-gray-100">
                            {article.tags.map(tag => (
                                <span key={tag} className="px-2 py-0.5 bg-gray-100 text-gray-500 rounded text-xs">{tag}</span>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}

import React from 'react';
import { useForm, router } from '@inertiajs/react';
import type { Comment } from '@/types';
import { useConfirm } from '@/hooks/useConfirm';
import { Send, Trash2 } from 'lucide-react';
import { timeAgo } from '@/lib/utils';

interface CommentsSectionProps {
    taskId: string;
    comments: Comment[];
}

export const CommentsSection: React.FC<CommentsSectionProps> = ({ taskId, comments }) => {
    const confirm = useConfirm();
    const commentForm = useForm({ body: '' });

    function submitComment(e: React.FormEvent) {
        e.preventDefault();
        commentForm.post(`/tasks/${taskId}/comments`, {
            preserveScroll: true,
            onSuccess: () => commentForm.reset(),
        });
    }

    return (
        <div className="space-y-4">
            {comments.length === 0 && (
                <p className="text-sm text-gray-500 text-center py-4">No comments yet.</p>
            )}
            {comments.map((comment) => (
                <div key={comment.id} className="flex gap-3">
                    <div className="w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 text-xs flex items-center justify-center font-semibold shrink-0">
                        {comment.user?.name?.[0] ?? '?'}
                    </div>
                    <div className="flex-1 bg-gray-50 rounded-lg p-3">
                        <div className="flex items-center gap-2 mb-1">
                            <span className="text-sm font-medium text-gray-900">{comment.user?.name}</span>
                            <span className="text-xs text-gray-400">{timeAgo(comment.created_at)}</span>
                            <button
                                type="button"
                                onClick={async () => {
                                    const ok = await confirm({
                                        title: 'Delete this comment?',
                                        description: 'This cannot be undone.',
                                        confirmText: 'Delete',
                                        variant: 'destructive',
                                    });
                                    if (!ok) return;
                                    router.delete(`/tasks/${taskId}/comments/${comment.id}`, { preserveScroll: true });
                                }}
                                className="ml-auto p-1 text-gray-300 hover:text-red-500 transition-colors rounded"
                            >
                                <Trash2 size={12} />
                            </button>
                        </div>
                        <p className="text-sm text-gray-700">{comment.body}</p>
                    </div>
                </div>
            ))}
            <form onSubmit={submitComment} className="flex gap-3">
                <div className="flex-1">
                    <textarea
                        value={commentForm.data.body}
                        onChange={(e) => commentForm.setData('body', e.target.value)}
                        rows={2}
                        placeholder="Add a comment…"
                        className="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
                    />
                </div>
                <button
                    type="submit"
                    disabled={!commentForm.data.body || commentForm.processing}
                    className="self-end p-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50"
                >
                    <Send size={16} />
                </button>
            </form>
        </div>
    );
};

import React from 'react';
import { Button } from '@/Components/ui/Button';
import { useForm, router } from '@inertiajs/react';
import type { Comment } from '@/types';
import { useConfirm } from '@/hooks/useConfirm';
import { Send, Trash2 } from 'lucide-react';
import { timeAgo, cn } from '@/lib/utils';
import { RichTextEditor } from '@/Components/Shared/RichTextEditor';

interface CommentsSectionProps {
    submitUrl: string;
    deleteUrlTemplate: (commentId: string) => string;
    comments: Comment[];
}

export const CommentsSection: React.FC<CommentsSectionProps> = ({ submitUrl, deleteUrlTemplate, comments }) => {
    const confirm = useConfirm();
    const commentForm = useForm({ body: '' });

    function submitComment(e: React.FormEvent) {
        e.preventDefault();
        commentForm.post(submitUrl, {
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
                            <Button
                                type="button"
                                onClick={async () => {
                                    const ok = await confirm({
                                        title: 'Delete this comment?',
                                        description: 'This cannot be undone.',
                                        confirmText: 'Delete',
                                        variant: 'destructive',
                                    });
                                    if (!ok) return;
                                    router.delete(deleteUrlTemplate(comment.id), { preserveScroll: true });
                                }}
                                className="ml-auto p-1 text-gray-300 hover:text-red-500 transition-colors rounded"
                            >
                                <Trash2 size={12} />
                            </Button>
                        </div>
                        <div 
                            className="text-sm text-gray-700 prose prose-sm max-w-none"
                            dangerouslySetInnerHTML={{ __html: comment.body }} 
                        />
                    </div>
                </div>
            ))}
            <form onSubmit={submitComment} className="flex gap-3">
                <div className="flex-1">
                    <RichTextEditor
                        value={commentForm.data.body}
                        onChange={(value) => commentForm.setData('body', value)}
                        placeholder="Add a comment…"
                        className="bg-white"
                    />
                </div>
                <Button
                    type="submit"
                    disabled={!commentForm.data.body || commentForm.data.body === '<p></p>' || commentForm.processing}
                    className="self-end p-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50"
                >
                    <Send size={16} />
                </Button>
            </form>
        </div>
    );
};

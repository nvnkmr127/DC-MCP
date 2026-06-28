import React, { useEffect } from 'react';
import { useEditor, EditorContent } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import { cn } from '@/lib/utils';
import { Bold, Italic, List, ListOrdered, Quote, Undo, Redo } from 'lucide-react';

interface Props {
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    className?: string;
}

const MenuBar = ({ editor }: { editor: any }) => {
    if (!editor) return null;

    const btnClass = "p-1.5 rounded text-gray-500 hover:bg-gray-100 hover:text-gray-900 transition-colors disabled:opacity-30";
    const activeClass = "bg-indigo-50 text-indigo-700 hover:bg-indigo-100";

    return (
        <div className="flex flex-wrap items-center gap-1 p-1 border-b border-gray-200 bg-gray-50/50 rounded-t-lg">
            <button
                type="button"
                onClick={() => editor.chain().focus().toggleBold().run()}
                disabled={!editor.can().chain().focus().toggleBold().run()}
                className={cn(btnClass, editor.isActive('bold') && activeClass)}
            >
                <Bold size={16} />
            </button>
            <button
                type="button"
                onClick={() => editor.chain().focus().toggleItalic().run()}
                disabled={!editor.can().chain().focus().toggleItalic().run()}
                className={cn(btnClass, editor.isActive('italic') && activeClass)}
            >
                <Italic size={16} />
            </button>
            <div className="w-px h-4 bg-gray-300 mx-1" />
            <button
                type="button"
                onClick={() => editor.chain().focus().toggleBulletList().run()}
                className={cn(btnClass, editor.isActive('bulletList') && activeClass)}
            >
                <List size={16} />
            </button>
            <button
                type="button"
                onClick={() => editor.chain().focus().toggleOrderedList().run()}
                className={cn(btnClass, editor.isActive('orderedList') && activeClass)}
            >
                <ListOrdered size={16} />
            </button>
            <button
                type="button"
                onClick={() => editor.chain().focus().toggleBlockquote().run()}
                className={cn(btnClass, editor.isActive('blockquote') && activeClass)}
            >
                <Quote size={16} />
            </button>
            <div className="flex-1" />
            <button
                type="button"
                onClick={() => editor.chain().focus().undo().run()}
                disabled={!editor.can().chain().focus().undo().run()}
                className={btnClass}
            >
                <Undo size={16} />
            </button>
            <button
                type="button"
                onClick={() => editor.chain().focus().redo().run()}
                disabled={!editor.can().chain().focus().redo().run()}
                className={btnClass}
            >
                <Redo size={16} />
            </button>
        </div>
    );
};

export function RichTextEditor({ value, onChange, placeholder, className }: Props) {
    const editor = useEditor({
        extensions: [
            StarterKit,
        ],
        content: value,
        editorProps: {
            attributes: {
                class: 'prose prose-sm prose-indigo max-w-none focus:outline-none min-h-[120px] p-3 text-sm text-gray-700',
            },
        },
        onUpdate: ({ editor }) => {
            onChange(editor.getHTML());
        },
    });

    useEffect(() => {
        if (editor && editor.getHTML() !== value && value === '') {
            editor.commands.setContent('');
        }
    }, [value, editor]);

    return (
        <div className={cn("border border-gray-200 rounded-lg overflow-hidden focus-within:ring-2 focus-within:ring-indigo-500 focus-within:border-transparent transition-all", className)}>
            <MenuBar editor={editor} />
            <EditorContent editor={editor} className="bg-white" />
        </div>
    );
}

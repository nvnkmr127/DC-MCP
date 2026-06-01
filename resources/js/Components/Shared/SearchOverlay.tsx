import React, { useEffect, useRef } from 'react';
import { Search } from 'lucide-react';

interface SearchOverlayProps {
    open: boolean;
    onClose: () => void;
}

export const SearchOverlay: React.FC<SearchOverlayProps> = ({ open, onClose }) => {
    const inputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (open) {
            const timer = setTimeout(() => inputRef.current?.focus(), 50);
            return () => clearTimeout(timer);
        }
    }, [open]);

    if (!open) return null;

    return (
        <div
            className="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-start justify-center pt-20"
            onClick={onClose}
        >
            <div
                className="w-full max-w-xl mx-4 bg-white rounded-2xl shadow-2xl border border-gray-200 overflow-hidden"
                onClick={(e) => e.stopPropagation()}
            >
                <div className="flex items-center gap-3 px-4 py-3.5 border-b border-gray-100">
                    <Search size={16} className="text-gray-400 shrink-0" />
                    <input
                        ref={inputRef}
                        placeholder="Search projects, tasks, clients…"
                        className="flex-1 text-sm text-gray-900 placeholder-gray-400 outline-none bg-transparent"
                    />
                    <kbd
                        onClick={onClose}
                        className="cursor-pointer text-[10px] bg-gray-100 border border-gray-200 rounded px-1.5 py-0.5 text-gray-400 font-mono"
                    >ESC</kbd>
                </div>
                <div className="px-4 py-10 text-center text-xs text-gray-400">
                    Start typing to search across your workspace
                </div>
                <div className="px-4 py-2.5 bg-gray-50 border-t border-gray-100 flex items-center gap-5 text-[10px] text-gray-400">
                    <span className="flex items-center gap-1"><kbd className="bg-white border border-gray-200 rounded px-1 py-0.5">↵</kbd> Open</span>
                    <span className="flex items-center gap-1"><kbd className="bg-white border border-gray-200 rounded px-1 py-0.5">↑↓</kbd> Navigate</span>
                    <span className="flex items-center gap-1"><kbd className="bg-white border border-gray-200 rounded px-1 py-0.5">ESC</kbd> Close</span>
                </div>
            </div>
        </div>
    );
};

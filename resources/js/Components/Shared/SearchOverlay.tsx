import React, { useEffect, useState, useCallback } from 'react';
import { Command } from 'cmdk';
import { Search, Loader2, FolderKanban, CheckSquare, User, Briefcase, FileText, Send, Flag, Megaphone, BookOpen, Clock, Activity, FileCheck, CircleDollarSign } from 'lucide-react';
import { router } from '@inertiajs/react';
import axios from 'axios';
import { cn } from '@/lib/utils';

interface SearchOverlayProps {
    open: boolean;
    onClose: () => void;
}

interface SearchResult {
    id: string;
    title: string;
    subtitle: string;
    url: string;
}

interface SearchGroup {
    group: string;
    items: SearchResult[];
}

// Map groups to icons
const getIconForGroup = (group: string) => {
    switch (group) {
        case 'Projects': return <FolderKanban size={16} className="text-indigo-600" />;
        case 'Tasks': return <CheckSquare size={16} className="text-emerald-600" />;
        case 'Users': return <User size={16} className="text-blue-600" />;
        case 'Clients': return <Briefcase size={16} className="text-orange-600" />;
        case 'Invoices': return <CircleDollarSign size={16} className="text-green-600" />;
        case 'Proposals': return <Send size={16} className="text-purple-600" />;
        case 'SOWs': return <FileText size={16} className="text-rose-600" />;
        case 'Sprints': return <Activity size={16} className="text-cyan-600" />;
        case 'Prospects': return <FileCheck size={16} className="text-yellow-600" />;
        case 'Goals': return <Flag size={16} className="text-red-600" />;
        case 'Announcements': return <Megaphone size={16} className="text-teal-600" />;
        case 'Knowledge Base': return <BookOpen size={16} className="text-pink-600" />;
        default: return <Search size={16} className="text-gray-600" />;
    }
};

const getBgForGroup = (group: string) => {
    switch (group) {
        case 'Projects': return 'bg-indigo-100';
        case 'Tasks': return 'bg-emerald-100';
        case 'Users': return 'bg-blue-100';
        case 'Clients': return 'bg-orange-100';
        case 'Invoices': return 'bg-green-100';
        case 'Proposals': return 'bg-purple-100';
        case 'SOWs': return 'bg-rose-100';
        case 'Sprints': return 'bg-cyan-100';
        case 'Prospects': return 'bg-yellow-100';
        case 'Goals': return 'bg-red-100';
        case 'Announcements': return 'bg-teal-100';
        case 'Knowledge Base': return 'bg-pink-100';
        default: return 'bg-gray-100';
    }
};

export const SearchOverlay: React.FC<SearchOverlayProps> = ({ open, onClose }) => {
    const [query, setQuery] = useState('');
    const [loading, setLoading] = useState(false);
    const [results, setResults] = useState<SearchGroup[]>([]);

    const fetchResults = useCallback(async (q: string) => {
        if (!q || q.length < 2) {
            setResults([]);
            return;
        }
        setLoading(true);
        try {
            const { data } = await axios.get<SearchGroup[]>(`/api/search?q=${encodeURIComponent(q)}`);
            setResults(Array.isArray(data) ? data : []);
        } catch (error) {
            console.error('Search failed', error);
            setResults([]);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        const debounce = setTimeout(() => {
            fetchResults(query);
        }, 300);
        return () => clearTimeout(debounce);
    }, [query, fetchResults]);

    useEffect(() => {
        if (!open) setQuery('');
    }, [open]);

    const handleSelect = (url: string) => {
        onClose();
        router.visit(url);
    };

    if (!open) return null;

    const hasResults = results.length > 0 && results.some(g => g.items.length > 0);

    return (
        <div 
            className="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-start justify-center pt-20"
            onClick={onClose}
        >
            <div 
                className="w-full max-w-xl mx-4 bg-white rounded-2xl shadow-2xl border border-gray-200 overflow-hidden flex flex-col"
                onClick={(e) => e.stopPropagation()}
            >
                <Command className="w-full h-full flex flex-col bg-white" shouldFilter={false} label="Global Command Menu">
                    <div className="flex items-center gap-3 px-4 py-3.5 border-b border-gray-100">
                        {loading ? <Loader2 size={16} className="text-indigo-500 animate-spin shrink-0" /> : <Search size={16} className="text-gray-400 shrink-0" />}
                        <Command.Input 
                            autoFocus
                            value={query}
                            onValueChange={setQuery}
                            placeholder="Search everything…"
                            className="flex-1 text-sm text-gray-900 placeholder-gray-400 outline-none bg-transparent"
                        />
                        <kbd onClick={onClose} className="cursor-pointer text-[10px] bg-gray-100 border border-gray-200 rounded px-1.5 py-0.5 text-gray-400 font-mono">ESC</kbd>
                    </div>

                    <Command.List className="max-h-[400px] overflow-y-auto p-2 scrollbar-thin">
                        {!loading && query.length >= 2 && !hasResults && (
                            <Command.Empty className="py-6 text-center text-sm text-gray-500">No results found.</Command.Empty>
                        )}
                        
                        {!loading && query.length < 2 && (
                            <div className="py-6 text-center text-sm text-gray-400">Start typing to search across 10+ modules...</div>
                        )}

                        {results.map((group) => (
                            <Command.Group 
                                key={group.group} 
                                heading={<div className="px-2 py-1.5 text-[10px] font-bold text-gray-400 uppercase tracking-wider mt-2">{group.group}</div>}
                            >
                                {group.items.map(item => (
                                    <Command.Item
                                        key={`${group.group}-${item.id}`}
                                        value={`${group.group}-${item.id}`}
                                        onSelect={() => handleSelect(item.url)}
                                        className="flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-gray-50 aria-selected:bg-indigo-50 aria-selected:text-indigo-900 cursor-pointer text-sm transition-colors"
                                    >
                                        <div className={cn("w-8 h-8 rounded-lg flex items-center justify-center shrink-0", getBgForGroup(group.group))}>
                                            {getIconForGroup(group.group)}
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <p className="font-medium truncate">{item.title}</p>
                                            <p className="text-[11px] text-gray-500 truncate">{item.subtitle}</p>
                                        </div>
                                    </Command.Item>
                                ))}
                            </Command.Group>
                        ))}
                    </Command.List>
                </Command>
                <div className="px-4 py-2.5 bg-gray-50 border-t border-gray-100 flex items-center gap-5 text-[10px] text-gray-400">
                    <span className="flex items-center gap-1"><kbd className="bg-white border border-gray-200 rounded px-1 py-0.5">↵</kbd> Open</span>
                    <span className="flex items-center gap-1"><kbd className="bg-white border border-gray-200 rounded px-1 py-0.5">↑↓</kbd> Navigate</span>
                    <span className="flex items-center gap-1"><kbd className="bg-white border border-gray-200 rounded px-1 py-0.5">ESC</kbd> Close</span>
                </div>
            </div>
        </div>
    );
};

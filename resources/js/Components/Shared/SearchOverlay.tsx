import React, { useEffect, useState, useCallback } from 'react';
import { Command } from 'cmdk';
import { Search, Loader2, FolderKanban, CheckSquare, User } from 'lucide-react';
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
    type: 'task' | 'project' | 'user';
}

interface SearchResponse {
    tasks: SearchResult[];
    projects: SearchResult[];
    users: SearchResult[];
}

export const SearchOverlay: React.FC<SearchOverlayProps> = ({ open, onClose }) => {
    const [query, setQuery] = useState('');
    const [loading, setLoading] = useState(false);
    const [results, setResults] = useState<SearchResponse>({ tasks: [], projects: [], users: [] });

    const fetchResults = useCallback(async (q: string) => {
        if (!q || q.length < 2) {
            setResults({ tasks: [], projects: [], users: [] });
            return;
        }
        setLoading(true);
        try {
            const { data } = await axios.get<SearchResponse>(`/api/v1/search?q=${encodeURIComponent(q)}`);
            setResults(data);
        } catch (error) {
            console.error('Search failed', error);
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

    const hasResults = results.tasks.length > 0 || results.projects.length > 0 || results.users.length > 0;

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
                            placeholder="Search projects, tasks, clients…"
                            className="flex-1 text-sm text-gray-900 placeholder-gray-400 outline-none bg-transparent"
                        />
                        <kbd onClick={onClose} className="cursor-pointer text-[10px] bg-gray-100 border border-gray-200 rounded px-1.5 py-0.5 text-gray-400 font-mono">ESC</kbd>
                    </div>

                    <Command.List className="max-h-[300px] overflow-y-auto p-2 scrollbar-thin">
                        {!loading && query.length >= 2 && !hasResults && (
                            <Command.Empty className="py-6 text-center text-sm text-gray-500">No results found.</Command.Empty>
                        )}
                        
                        {!loading && query.length < 2 && (
                            <div className="py-6 text-center text-sm text-gray-400">Start typing to search...</div>
                        )}

                        {results.projects.length > 0 && (
                            <Command.Group heading={<div className="px-2 py-1.5 text-[10px] font-bold text-gray-400 uppercase tracking-wider">Projects</div>}>
                                {results.projects.map(project => (
                                    <Command.Item
                                        key={project.id}
                                        value={project.id}
                                        onSelect={() => handleSelect(project.url)}
                                        className="flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-gray-50 aria-selected:bg-indigo-50 aria-selected:text-indigo-900 cursor-pointer text-sm"
                                    >
                                        <div className="w-8 h-8 rounded-lg bg-indigo-100 flex items-center justify-center shrink-0">
                                            <FolderKanban size={14} className="text-indigo-600" />
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <p className="font-medium truncate">{project.title}</p>
                                            <p className="text-[11px] text-gray-500 truncate">{project.subtitle}</p>
                                        </div>
                                    </Command.Item>
                                ))}
                            </Command.Group>
                        )}

                        {results.tasks.length > 0 && (
                            <Command.Group heading={<div className="px-2 py-1.5 text-[10px] font-bold text-gray-400 uppercase tracking-wider mt-2">Tasks</div>}>
                                {results.tasks.map(task => (
                                    <Command.Item
                                        key={task.id}
                                        value={task.id}
                                        onSelect={() => handleSelect(task.url)}
                                        className="flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-gray-50 aria-selected:bg-indigo-50 aria-selected:text-indigo-900 cursor-pointer text-sm"
                                    >
                                        <div className="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center shrink-0">
                                            <CheckSquare size={14} className="text-emerald-600" />
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <p className="font-medium truncate">{task.title}</p>
                                            <p className="text-[11px] text-gray-500 truncate">{task.subtitle}</p>
                                        </div>
                                    </Command.Item>
                                ))}
                            </Command.Group>
                        )}

                        {results.users.length > 0 && (
                            <Command.Group heading={<div className="px-2 py-1.5 text-[10px] font-bold text-gray-400 uppercase tracking-wider mt-2">Users</div>}>
                                {results.users.map(user => (
                                    <Command.Item
                                        key={user.id}
                                        value={user.id}
                                        onSelect={() => handleSelect(user.url)}
                                        className="flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-gray-50 aria-selected:bg-indigo-50 aria-selected:text-indigo-900 cursor-pointer text-sm"
                                    >
                                        <div className="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center shrink-0">
                                            <User size={14} className="text-blue-600" />
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <p className="font-medium truncate">{user.title}</p>
                                            <p className="text-[11px] text-gray-500 truncate">{user.subtitle}</p>
                                        </div>
                                    </Command.Item>
                                ))}
                            </Command.Group>
                        )}
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

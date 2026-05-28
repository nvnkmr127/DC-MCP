import React, { useState, useEffect, useRef } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import { usePermissions } from '@/hooks/usePermissions';
import { getInitials, cn } from '@/lib/utils';
import type { PageProps } from '@/types';
import { toast } from 'sonner';
import {
    LayoutDashboard, FolderKanban, CheckSquare, Users, BarChart3,
    Settings, Bell, Search, ChevronLeft, ChevronRight,
    Briefcase, Calendar, Plug, LogOut, User, Sun, ChevronDown,
    Command, Plus, Zap, HelpCircle, Sparkles, PenTool, Globe,
    TrendingUp, GitBranch, ClipboardList, Activity, FileText,
} from 'lucide-react';

interface NavItem {
    label: string;
    href: string;
    icon: React.ElementType;
    roles?: string[];
    badge?: number;
    section?: string;
}

const NAV_ITEMS: NavItem[] = [
    { label: 'Dashboard',      href: '/dashboard',     icon: LayoutDashboard, section: 'main' },
    { label: 'Projects',       href: '/projects',      icon: FolderKanban,    section: 'main' },
    { label: 'Tasks',          href: '/tasks',         icon: CheckSquare,     section: 'main' },
    { label: 'Calendar',       href: '/calendar',      icon: Calendar,        section: 'main' },
    { label: 'Clients',        href: '/clients',       icon: Briefcase,       section: 'main' },
    { label: 'Content',        href: '/content',       icon: PenTool,         section: 'main' },
    { label: 'Revenue',        href: '/retainers',    icon: TrendingUp,   section: 'main',     roles: ['ceo', 'project_manager'] },
    { label: 'Pipeline',       href: '/prospects',    icon: GitBranch,    section: 'main',     roles: ['ceo', 'project_manager'] },
    { label: 'SOW',            href: '/sow',          icon: FileText,     section: 'main',     roles: ['ceo', 'project_manager'] },
    { label: 'Capacity',       href: '/capacity',     icon: Activity,     section: 'main',     roles: ['ceo', 'project_manager'] },
    { label: 'Standup',        href: '/standup',      icon: ClipboardList, section: 'main' },
    { label: 'Daily Briefing', href: '/briefings',    icon: Sun,      section: 'insights' },
    { label: 'AI Suggestions', href: '/suggestions',  icon: Sparkles, section: 'insights', roles: ['ceo', 'project_manager'] },
    { label: 'Reports',        href: '/reports',      icon: BarChart3, section: 'insights' },
    { label: 'Team',           href: '/settings/team', icon: Users,           section: 'manage', roles: ['ceo', 'project_manager'] },
    { label: 'MCP Connect',    href: '/settings/mcp',           icon: Plug,   section: 'manage', roles: ['ceo', 'project_manager'] },
    { label: 'Client Portal',  href: '/settings/client-portal', icon: Globe,  section: 'manage', roles: ['ceo'] },
    { label: 'Settings',       href: '/settings',               icon: Settings, section: 'manage' },
];

const NAV_SECTIONS = [
    { key: 'main',     label: 'Workspace' },
    { key: 'insights', label: 'Insights' },
    { key: 'manage',   label: 'Manage' },
];

export default function AppLayout({ children, title }: { children: React.ReactNode; title?: string }) {
    const { auth, app, flash } = usePage<PageProps>().props;
    const { hasRole } = usePermissions();
    const [collapsed, setCollapsed] = useState(false);
    const [userMenuOpen, setUserMenuOpen] = useState(false);
    const [searchOpen, setSearchOpen] = useState(false);
    const userMenuRef = useRef<HTMLDivElement>(null);
    const user = auth.user!;

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error)   toast.error(flash.error);
    }, [flash?.success, flash?.error]);

    useEffect(() => {
        function handleKey(e: KeyboardEvent) {
            if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                e.preventDefault();
                setSearchOpen(true);
            }
            if (e.key === 'Escape') {
                setSearchOpen(false);
                setUserMenuOpen(false);
            }
        }
        window.addEventListener('keydown', handleKey);
        return () => window.removeEventListener('keydown', handleKey);
    }, []);

    useEffect(() => {
        function handleClick(e: MouseEvent) {
            if (userMenuRef.current && !userMenuRef.current.contains(e.target as Node)) {
                setUserMenuOpen(false);
            }
        }
        document.addEventListener('mousedown', handleClick);
        return () => document.removeEventListener('mousedown', handleClick);
    }, []);

    const visibleNav = NAV_ITEMS.filter(
        (item) => !item.roles || item.roles.some((r) => hasRole(r)),
    );

    const currentPath = typeof window !== 'undefined' ? window.location.pathname : '';

    function isActive(href: string) {
        if (href === '/dashboard') return currentPath === '/dashboard';
        return currentPath.startsWith(href);
    }

    return (
        <div className="flex h-screen bg-[#f4f5f7] overflow-hidden">

            {/* ──────────── SIDEBAR ──────────── */}
            <aside className={cn(
                'relative flex flex-col flex-shrink-0 transition-all duration-300 ease-in-out',
                'bg-gradient-to-b from-[#0e1017] via-[#131622] to-[#090b10] border-r border-[#1c1e2b] shadow-[4px_0_24px_rgba(0,0,0,0.12)]',
                collapsed ? 'w-[58px]' : 'w-[230px]',
            )}>

                {/* Logo row */}
                <div className={cn(
                    'flex items-center h-[56px] border-b border-[#1c1e2b] px-4 gap-3 flex-shrink-0',
                    collapsed && 'justify-center',
                )}>
                    <div className="w-8 h-8 rounded-xl bg-gradient-to-tr from-indigo-500 via-purple-500 to-violet-500 flex items-center justify-center shrink-0 shadow-[0_0_15px_rgba(99,102,241,0.35)] animate-pulse">
                        <Zap size={14} className="text-white fill-white/10" />
                    </div>
                    {!collapsed && (
                        <>
                            <div className="flex-1 min-w-0">
                                <p className="text-[13px] font-bold text-white tracking-wide truncate leading-tight">{app?.name ?? 'Digicloudify'}</p>
                                <p className="text-[9px] text-[#4e566d] font-semibold uppercase tracking-wider leading-tight">Morning Digest & Ops</p>
                            </div>
                            <button
                                onClick={() => setCollapsed(true)}
                                className="p-1 rounded-lg text-[#4e566d] hover:text-[#a5b4fc] hover:bg-white/5 transition-colors"
                            >
                                <ChevronLeft size={14} />
                            </button>
                        </>
                    )}
                </div>

                {/* Expand toggle (collapsed state) */}
                {collapsed && (
                    <button
                        onClick={() => setCollapsed(false)}
                        className="flex items-center justify-center mx-auto mt-2.5 w-8 h-8 rounded-lg text-[#4e566d] hover:text-[#a5b4fc] hover:bg-white/5 transition-colors"
                    >
                        <ChevronRight size={14} />
                    </button>
                )}

                {/* Search */}
                <div className={cn('px-3 pt-3 pb-1.5', collapsed && 'flex justify-center')}>
                    {!collapsed ? (
                        <button
                            onClick={() => setSearchOpen(true)}
                            className="w-full flex items-center gap-2 px-3 py-2 rounded-xl bg-[#171a27] border border-[#23273a]/40 text-[#5f6885] hover:text-indigo-200 hover:border-indigo-500/20 hover:bg-[#1a1e2f] transition-all text-[12px]"
                        >
                            <Search size={12} className="shrink-0" />
                            <span className="flex-1 text-left">Search…</span>
                            <span className="flex items-center gap-0.5 text-[10px] opacity-50 font-mono">
                                ⌘K
                            </span>
                        </button>
                    ) : (
                        <button
                            onClick={() => setSearchOpen(true)}
                            className="w-9 h-9 flex items-center justify-center rounded-xl text-[#5f6885] hover:text-[#a5b4fc] hover:bg-white/5 transition-colors"
                        >
                            <Search size={15} />
                        </button>
                    )}
                </div>

                {/* Nav */}
                <nav className="flex-1 overflow-y-auto sidebar-scroll pb-3 space-y-4 mt-2">
                    {NAV_SECTIONS.map(({ key, label }) => {
                        const items = visibleNav.filter((i) => i.section === key);
                        if (!items.length) return null;
                        return (
                            <div key={key}>
                                {!collapsed && (
                                    <p className="px-5 mb-1.5 text-[9px] font-bold uppercase tracking-widest text-[#3b4157]">
                                        {label}
                                    </p>
                                )}
                                <div className="space-y-[3px] px-3">
                                    {items.map((item) => {
                                        const Icon = item.icon;
                                        const active = isActive(item.href);
                                        return (
                                            <Link
                                                key={item.href}
                                                href={item.href}
                                                title={collapsed ? item.label : undefined}
                                                className={cn(
                                                    'flex items-center gap-2.5 px-3 py-2 rounded-xl text-[13px] font-medium transition-all duration-200 relative group',
                                                    active
                                                        ? 'bg-indigo-600/10 text-indigo-200 shadow-[inset_0_1px_1px_rgba(255,255,255,0.03)] border border-indigo-500/20'
                                                        : 'text-gray-400 hover:bg-white/5 hover:text-white border border-transparent',
                                                    collapsed && 'justify-center',
                                                )}
                                            >
                                                <Icon
                                                    size={15}
                                                    className={cn('shrink-0 transition-colors', active ? 'text-indigo-400' : 'text-[#4e566d] group-hover:text-indigo-300')}
                                                />
                                                {!collapsed && <span className="truncate">{item.label}</span>}
                                                {!collapsed && !!item.badge && (
                                                    <span className="ml-auto min-w-[18px] h-[18px] text-[10px] font-bold bg-indigo-500 text-white rounded-full flex items-center justify-center px-1">
                                                        {item.badge}
                                                    </span>
                                                )}
                                            </Link>
                                        );
                                    })}
                                </div>
                            </div>
                        );
                    })}
                </nav>

                {/* User row */}
                <div className="border-t border-[#1c1e2b] p-3 flex-shrink-0" ref={userMenuRef}>
                    <div className="relative">
                        <button
                            onClick={() => setUserMenuOpen(!userMenuOpen)}
                            className={cn(
                                'w-full flex items-center gap-2.5 p-1.5 rounded-xl hover:bg-white/5 transition-colors group',
                                collapsed && 'justify-center',
                            )}
                        >
                            <div className="w-8 h-8 rounded-xl bg-gradient-to-tr from-indigo-500 via-purple-500 to-violet-500 flex items-center justify-center text-[11px] font-bold text-white shrink-0 ring-2 ring-transparent group-hover:ring-indigo-500/20 transition-all shadow-[0_2px_8px_rgba(0,0,0,0.2)]">
                                {user.avatar_url
                                    ? <img src={user.avatar_url} alt={user.name} className="w-full h-full rounded-xl object-cover" />
                                    : getInitials(user.name)
                                }
                            </div>
                            {!collapsed && (
                                <>
                                    <div className="flex-1 min-w-0 text-left">
                                        <p className="text-[12px] font-semibold text-[#e2e8f0] truncate leading-tight">{user.name}</p>
                                        <p className="text-[10px] text-[#4e566d] truncate leading-tight">{user.email}</p>
                                    </div>
                                    <ChevronDown size={11} className="text-[#4e566d] shrink-0" />
                                </>
                            )}
                        </button>

                        {userMenuOpen && (
                            <div className={cn(
                                'absolute bottom-full mb-2.5 z-50 bg-[#12141f] border border-[#23273a]/60 rounded-2xl shadow-[0_10px_30px_rgba(0,0,0,0.5)] py-1.5 min-w-[200px] backdrop-blur-xl',
                                collapsed ? 'left-11' : 'left-0 right-0',
                            )}>
                                <div className="px-3.5 py-2 border-b border-[#23273a]/60 mb-1">
                                    <p className="text-[12px] font-semibold text-white truncate">{user.name}</p>
                                    <p className="text-[10px] text-[#4e566d] truncate">{user.email}</p>
                                </div>
                                {[
                                    { href: '/settings/profile', Icon: User,        label: 'Profile Settings' },
                                    { href: '/settings',         Icon: Settings,     label: 'Preferences' },
                                    { href: '/help',             Icon: HelpCircle,   label: 'Help & Support' },
                                ].map(({ href, Icon, label }) => (
                                    <Link
                                        key={href}
                                        href={href}
                                        className="flex items-center gap-2 px-3.5 py-2 text-[12px] text-gray-400 hover:text-white hover:bg-white/5 transition-all mx-1.5 rounded-xl"
                                    >
                                        <Icon size={12} className="shrink-0" /> {label}
                                    </Link>
                                ))}
                                <div className="my-1.5 border-t border-[#23273a]/60" />
                                <Link
                                    href="/logout"
                                    method="post"
                                    as="button"
                                    className="flex items-center gap-2 w-[calc(100%-12px)] px-3.5 py-2 text-[12px] text-red-400 hover:text-red-300 hover:bg-red-500/10 transition-all mx-1.5 rounded-xl"
                                >
                                    <LogOut size={12} className="shrink-0" /> Sign Out
                                </Link>
                            </div>
                        )}
                    </div>
                </div>
            </aside>

            {/* ──────────── MAIN ──────────── */}
            <div className="flex-1 flex flex-col overflow-hidden min-w-0">

                {/* Topbar */}
                <header
                    className="h-[56px] bg-white/90 backdrop-blur-md border-b border-gray-100 flex items-center px-6 gap-3 z-10 flex-shrink-0 shadow-[0_1px_10px_rgba(0,0,0,0.01)]"
                >
                    <div className="flex-1 min-w-0">
                        {title && (
                            <h1 className="text-[15px] font-semibold text-gray-900 truncate">{title}</h1>
                        )}
                    </div>

                    <div className="flex items-center gap-1.5">
                        {/* Search pill */}
                        <button
                            onClick={() => setSearchOpen(true)}
                            className="hidden md:flex items-center gap-2 px-3 py-1.5 rounded-lg bg-gray-50 border border-gray-200 text-gray-400 hover:bg-gray-100 hover:text-gray-600 text-xs transition-colors"
                        >
                            <Search size={13} />
                            <span className="text-gray-400">Search…</span>
                            <kbd className="ml-1 text-[10px] bg-white border border-gray-200 rounded px-1 py-0.5 font-mono text-gray-400">⌘K</kbd>
                        </button>

                        {/* Notifications */}
                        <Link
                            href="/notifications"
                            className="relative p-2 rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-700 transition-colors"
                        >
                            <Bell size={17} />
                            <span className="absolute top-[7px] right-[7px] w-[7px] h-[7px] bg-red-500 rounded-full ring-[1.5px] ring-white" />
                        </Link>

                        {/* Quick create */}
                        <button className="flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg transition-colors shadow-sm">
                            <Plus size={13} /> New
                        </button>

                        {/* Avatar */}
                        <button
                            onClick={() => router.visit('/settings/profile')}
                            className="ml-0.5 w-8 h-8 rounded-full bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center text-[11px] font-bold text-white hover:ring-2 hover:ring-indigo-400 hover:ring-offset-1 transition-all"
                        >
                            {user.avatar_url
                                ? <img src={user.avatar_url} alt={user.name} className="w-full h-full rounded-full object-cover" />
                                : getInitials(user.name)
                            }
                        </button>
                    </div>
                </header>

                {/* Page */}
                <main className="flex-1 overflow-y-auto p-6">
                    {children}
                </main>
            </div>

            {/* ──────────── SEARCH OVERLAY ──────────── */}
            {searchOpen && (
                <div
                    className="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-start justify-center pt-20"
                    onClick={() => setSearchOpen(false)}
                >
                    <div
                        className="w-full max-w-xl mx-4 bg-white rounded-2xl shadow-2xl border border-gray-200 overflow-hidden"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <div className="flex items-center gap-3 px-4 py-3.5 border-b border-gray-100">
                            <Search size={16} className="text-gray-400 shrink-0" />
                            <input
                                autoFocus
                                placeholder="Search projects, tasks, clients…"
                                className="flex-1 text-sm text-gray-900 placeholder-gray-400 outline-none bg-transparent"
                            />
                            <kbd
                                onClick={() => setSearchOpen(false)}
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
            )}
        </div>
    );
}

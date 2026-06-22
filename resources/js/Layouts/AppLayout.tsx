import React, { useState, useEffect, useRef } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import { usePermissions } from '@/hooks/usePermissions';
import { getInitials, cn } from '@/lib/utils';
import type { PageProps } from '@/types';
import { toast } from 'sonner';
import { SearchOverlay } from '@/Components/Shared/SearchOverlay';
import { ConfirmProvider } from '@/hooks/useConfirm';
import { ThemeToggle } from '@/Components/Shared/ThemeToggle';
import { useNotificationPoller } from '@/hooks/useNotificationPoller';
import axios from 'axios';
import {
    LayoutDashboard, FolderKanban, CheckSquare, Users, BarChart3,
    Settings, Bell, Search, ChevronLeft, ChevronRight,
    Briefcase, Calendar, Plug, LogOut, User, Sun, ChevronDown,
    Command, Plus, Zap, HelpCircle, Sparkles, PenTool, Globe,
    TrendingUp, GitBranch, ClipboardList, Activity, FileText,
    DollarSign, CreditCard, UserCheck, Target, Flag, Clock,
    Users2, RotateCcw, Bug, Layers, GitMerge, CheckCircle2,
    ListChecks, Star, ReceiptText, Package, Percent,
    FileCheck, MessageSquare, UserPlus, BookOpen, Smile,
    BarChart2, Workflow, ShoppingCart, FileX, Trophy, Send,
    Megaphone, Trash2, CheckCheck, UploadCloud
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
    { label: 'My Activity',    href: '/my-activity',   icon: Activity,        section: 'main' },
    { label: 'Projects',       href: '/projects',      icon: FolderKanban,    section: 'main' },
    { label: 'Tasks',          href: '/tasks',         icon: CheckSquare,     section: 'main' },
    { label: 'Calendar',       href: '/calendar',      icon: Calendar,        section: 'main' },
    { label: 'Clients',        href: '/clients',       icon: Briefcase,       section: 'main' },
    { label: 'Content',        href: '/content',       icon: PenTool,         section: 'main' },
    { label: 'Revenue',        href: '/retainers',    icon: TrendingUp,   section: 'financial',     roles: ['ceo', 'project_manager'] },
    { label: 'P&L',            href: '/financials',    icon: DollarSign,   section: 'financial', roles: ['ceo'] },
    { label: 'Payroll',        href: '/payroll',       icon: CreditCard,   section: 'financial', roles: ['ceo'] },
    { label: 'Ad Budgets',     href: '/campaign-budgets', icon: Target,      section: 'financial', roles: ['ceo', 'project_manager'] },
    { label: 'GST Report',     href: '/gst-report',       icon: Percent,    section: 'financial', roles: ['ceo'] },
    { label: 'Rate Card',      href: '/rate-cards',       icon: ReceiptText, section: 'financial', roles: ['ceo'] },
    { label: 'Purchase Orders', href: '/purchase-orders', icon: ShoppingCart, section: 'financial', roles: ['ceo', 'project_manager'] },
    { label: 'Credit Notes',   href: '/credit-notes',     icon: FileX,      section: 'financial', roles: ['ceo'] },

    { label: 'Pipeline',       href: '/prospects',    icon: GitBranch,    section: 'main',     roles: ['ceo', 'project_manager'] },
    { label: 'SOW',            href: '/sow',          icon: FileText,     section: 'main',     roles: ['ceo', 'project_manager'] },
    { label: 'Capacity',       href: '/capacity',     icon: Activity,     section: 'main',     roles: ['ceo', 'project_manager'] },
    { label: 'Standup',        href: '/standup',      icon: ClipboardList, section: 'main' },
    { label: 'Onboarding',     href: '/onboarding',    icon: UserCheck,    section: 'main', roles: ['ceo', 'project_manager'] },
    { label: 'Goals',          href: '/goals',            icon: Flag,        section: 'main', roles: ['ceo', 'project_manager'] },
    { label: 'Timesheets',     href: '/timesheets',        icon: Clock,       section: 'main' },
    { label: '1:1 Notes',      href: '/one-on-one',        icon: Users2,      section: 'main', roles: ['ceo', 'project_manager'] },
    { label: 'Recurring',      href: '/recurring-tasks',   icon: RotateCcw,   section: 'main', roles: ['ceo', 'project_manager'] },
    { label: 'Issues',         href: '/issues',           icon: Bug,        section: 'main' },
    { label: 'Sprints',        href: '/sprints',          icon: GitMerge,   section: 'main', roles: ['ceo', 'project_manager'] },
    { label: 'Templates',      href: '/project-templates', icon: Layers,    section: 'main', roles: ['ceo', 'project_manager'] },
    { label: 'Asset Approvals', href: '/asset-approvals', icon: CheckCircle2, section: 'main', roles: ['ceo', 'project_manager'] },
    { label: 'Audit Checklists', href: '/audit-checklists', icon: ListChecks, section: 'main' },
    { label: 'Proposals',      href: '/proposals',        icon: Send,       section: 'main', roles: ['ceo', 'project_manager'] },
    { label: 'Client Reports', href: '/client-reports',   icon: FileCheck,  section: 'main', roles: ['ceo', 'project_manager'] },
    { label: 'NPS Surveys',    href: '/client-surveys',   icon: Smile,      section: 'main', roles: ['ceo', 'project_manager'] },
    { label: 'Daily Briefing', href: '/briefings',    icon: Sun,      section: 'insights' },
    { label: 'AI Suggestions', href: '/suggestions',  icon: Sparkles, section: 'insights', roles: ['ceo', 'project_manager'] },
    { label: 'Reports',        href: '/reports',      icon: BarChart3, section: 'insights' },
    // HR section
    { label: 'Leave',           href: '/leave',         icon: Calendar,       section: 'hr' },
    { label: 'Reviews',         href: '/reviews',       icon: Star,           section: 'hr', roles: ['ceo', 'project_manager'] },
    { label: 'Announcements',   href: '/announcements', icon: Megaphone,      section: 'hr' },
    { label: 'Hiring',          href: '/hiring',        icon: UserPlus,       section: 'hr', roles: ['ceo', 'project_manager'] },
    { label: 'Freelancers',     href: '/freelancers',   icon: Users2,         section: 'hr', roles: ['ceo', 'project_manager'] },
    { label: 'Knowledge Base',  href: '/knowledge-base', icon: BookOpen,      section: 'hr' },
    // Manage
    { label: 'System Health',  href: '/settings/health', icon: Activity,      section: 'manage', roles: ['ceo'] },
    { label: 'Client Portal',  href: '/settings/client-portal', icon: Globe,   section: 'manage', roles: ['ceo'] },    { label: 'Settings',       href: '/settings',               icon: Settings, section: 'manage' },
    { label: 'Trash',          href: '/settings/trash',         icon: Trash2,   section: 'manage' },
    { label: 'Data Import',    href: '/settings/import',        icon: UploadCloud, section: 'manage' },
    { label: 'Audit Logs',     href: '/admin/audit-logs',       icon: ClipboardList, section: 'manage', roles: ['super_admin'] },
    { label: 'Feature Flags',  href: '/admin/feature-flags',    icon: Flag,          section: 'manage', roles: ['super_admin'] },
    // MCP
    { label: 'Integrations',   href: '/settings/mcp',           icon: Plug,    section: 'mcp', roles: ['ceo', 'project_manager'] },
    { label: 'Global Hub',     href: '/admin/mcp',              icon: Plug,    section: 'mcp', roles: ['super_admin'] },
    { label: 'Providers',      href: '/admin/mcp/providers',    icon: Settings, section: 'mcp', roles: ['super_admin'] },
];

const NAV_SECTIONS = [
    { key: 'main',      label: 'Workspace' },
    { key: 'financial', label: 'Financial' },
    { key: 'insights',  label: 'Insights' },
    { key: 'hr',        label: 'HR' },
    { key: 'manage',    label: 'Manage' },
    { key: 'mcp',       label: 'MCP' },
];

export default function AppLayout({ children, title }: { children: React.ReactNode; title?: string }) {
    const { auth, app, flash, mcp_errors } = usePage<PageProps & { mcp_errors?: any[] }>().props;
    const { hasRole } = usePermissions();
    const [collapsed, setCollapsed] = useState(false);
    const [userMenuOpen, setUserMenuOpen] = useState(false);
    const [searchOpen, setSearchOpen] = useState(false);
    const [openSections, setOpenSections] = useState<Record<string, boolean>>({ main: true, financial: false, insights: false, hr: false, manage: false, mcp: false });
    const userMenuRef = useRef<HTMLDivElement>(null);
    const user = auth.user!;
    const { unreadCount, requestNotificationPermission, permission, refetch } = useNotificationPoller(30000);

    const markAllReadGlobally = async () => {
        try {
            await axios.post('/api/v1/notifications/mark-all-read');
            toast.success('All notifications marked as read');
            refetch(); // Trigger the poller to reset badge to 0
        } catch (error) {
            toast.error('Failed to mark notifications as read');
        }
    };

    const toggleSection = (key: string) => {
        setOpenSections(prev => {
            // If already open, just close it.
            if (prev[key]) return { ...prev, [key]: false };
            
            // Accordion behavior: close all others, open the selected one
            const next = Object.keys(prev).reduce((acc, k) => {
                acc[k] = false;
                return acc;
            }, {} as Record<string, boolean>);
            next[key] = true;
            return next;
        });
    };

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error)   toast.error(flash.error);
    }, [flash?.success, flash?.error]);

    useEffect(() => {
        function handleKey(e: KeyboardEvent) {
            // Ignore if user is typing in an input or textarea
            if (e.target instanceof HTMLInputElement || e.target instanceof HTMLTextAreaElement) {
                return;
            }
            if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                e.preventDefault();
                setSearchOpen(true);
            }
            if (e.key === '/') {
                e.preventDefault();
                setSearchOpen(true);
            }
            if (e.key.toLowerCase() === 'n') {
                e.preventDefault();
                router.visit('/tasks/create');
            }
            if (e.key === 'Escape') {
                setSearchOpen(false);
                setUserMenuOpen(false);
            }
        }
        window.addEventListener('keydown', handleKey);
        return () => window.removeEventListener('keydown', handleKey);
    }, []);

    // Listen for real-time notifications via Reverb/Echo
    useEffect(() => {
        const echo = (window as any).Echo;
        if (echo && user) {
            echo.private(`user.${user.id}`)
                .listen('.notification.created', (e: any) => {
                    toast.success(e.title, {
                        description: e.body,
                    });
                    refetch(); // Update the notification badge
                });
        }
        return () => {
            if (echo && user) {
                echo.leave(`user.${user.id}`);
            }
        };
    }, [user.id, refetch]);

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
        <ConfirmProvider>
            <div className="flex h-screen bg-[#f4f5f7] overflow-hidden">

            {/* ──────────── SIDEBAR ──────────── */}
            <aside className={cn(
                'relative flex flex-col flex-shrink-0 transition-all duration-300 ease-in-out print:hidden',
                'bg-slate-900 dark:bg-slate-950 border-r border-slate-800 shadow-xl',
                collapsed ? 'w-[58px]' : 'w-[230px]',
            )}>

                {/* Logo row */}
                <div className={cn(
                    'flex items-center h-[56px] border-b border-slate-800 px-4 gap-3 flex-shrink-0',
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
                                className="p-1 rounded-lg text-slate-400 hover:text-indigo-300 hover:bg-white/5 transition-colors"
                            >
                                <ChevronLeft size={14} />
                            </button>
                        </>
                    )}
                </div>

                {collapsed && (
                    <button
                        onClick={() => setCollapsed(false)}
                        className="flex items-center justify-center mx-auto mt-2.5 w-8 h-8 rounded-lg text-slate-400 hover:text-indigo-300 hover:bg-white/5 transition-colors"
                    >
                        <ChevronRight size={14} />
                    </button>
                )}

                {/* Search */}
                <div className={cn('px-3 pt-3 pb-1.5', collapsed && 'flex justify-center')}>
                    {!collapsed ? (
                        <button
                            onClick={() => setSearchOpen(true)}
                            className="w-full flex items-center gap-2 px-3 py-2 rounded-xl bg-slate-800 border border-slate-700 text-slate-400 hover:text-indigo-200 hover:border-indigo-500/30 transition-all text-[12px]"
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
                            className="w-9 h-9 flex items-center justify-center rounded-xl text-slate-400 hover:text-indigo-300 hover:bg-white/5 transition-colors"
                        >
                            <Search size={15} />
                        </button>
                    )}
                </div>

                <nav className="flex-1 overflow-y-auto sidebar-scroll pb-3 mt-2">
                    {NAV_SECTIONS.map(({ key, label }) => {
                        const items = visibleNav.filter((i) => i.section === key);
                        if (!items.length) return null;
                        const isOpen = collapsed || openSections[key];
                        return (
                            <div key={key} className="mb-2">
                                {!collapsed && (
                                    <button
                                        onClick={() => toggleSection(key)}
                                        className="w-full flex items-center justify-between px-5 py-1.5 text-[10px] font-bold uppercase tracking-widest text-slate-500 hover:text-slate-300 transition-colors"
                                    >
                                        {label}
                                        <ChevronDown size={12} className={cn("transition-transform duration-200", isOpen ? "rotate-180" : "")} />
                                    </button>
                                )}
                                {isOpen && (
                                    <div className="space-y-[2px] px-3 mt-1">
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
                                                            ? 'bg-indigo-600/15 text-indigo-100 shadow-[inset_0_1px_1px_rgba(255,255,255,0.05)] border border-indigo-500/30'
                                                            : 'text-slate-400 hover:bg-white/5 hover:text-slate-100 border border-transparent',
                                                        collapsed && 'justify-center',
                                                    )}
                                                >
                                                    <Icon
                                                        size={15}
                                                        className={cn('shrink-0 transition-colors', active ? 'text-indigo-400' : 'text-slate-500 group-hover:text-indigo-300')}
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
                                )}
                            </div>
                        );
                    })}
                </nav>

                {/* User row */}
                <div className="border-t border-slate-800 p-3 flex-shrink-0" ref={userMenuRef}>
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
                                        <p className="text-[12px] font-semibold text-slate-200 truncate leading-tight">{user.name}</p>
                                        <p className="text-[10px] text-slate-500 truncate leading-tight">{user.email}</p>
                                    </div>
                                    <ChevronDown size={11} className="text-slate-500 shrink-0" />
                                </>
                            )}
                        </button>

                        {userMenuOpen && (
                            <div className={cn(
                                'absolute bottom-full mb-2.5 z-50 bg-slate-900 border border-slate-700 rounded-2xl shadow-[0_10px_30px_rgba(0,0,0,0.5)] py-1.5 min-w-[200px]',
                                collapsed ? 'left-11' : 'left-0 right-0',
                            )}>
                                <div className="px-3.5 py-2 border-b border-slate-800 mb-1">
                                    <p className="text-[12px] font-semibold text-white truncate">{user.name}</p>
                                    <p className="text-[10px] text-slate-400 truncate">{user.email}</p>
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
                                <div className="my-1.5 border-t border-slate-800" />
                                <div className="px-3.5 py-1.5">
                                    <ThemeToggle />
                                </div>
                                <div className="my-1.5 border-t border-slate-800" />
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

                {/* MCP Connection Errors Banner */}
                {mcp_errors && mcp_errors.length > 0 && (
                    <div className="bg-red-50 border-b border-red-100 text-red-700 px-6 py-2.5 text-xs font-semibold flex items-center justify-between z-20 shadow-sm">
                        <span className="flex items-center gap-2">
                            <span className="flex h-2 w-2 rounded-full bg-red-500 animate-pulse"></span>
                            Your {mcp_errors.map(e => e.label || e.provider.replace('_', ' ')).join(', ')} integration{mcp_errors.length > 1 ? 's are' : ' is'} disconnected or failing. Please reconnect.
                        </span>
                        <Link href="/settings/mcp" className="bg-red-100 hover:bg-red-200 px-3 py-1 rounded transition-colors text-red-800">
                            Fix Connections
                        </Link>
                    </div>
                )}

                {/* Impersonation Banner */}
                {(user as any).is_impersonating && (
                    <div className="bg-amber-100 border-b border-amber-200 text-amber-800 px-6 py-2.5 text-xs font-semibold flex items-center justify-between z-20">
                        <span className="flex items-center gap-2">
                            <span className="flex h-2 w-2 rounded-full bg-amber-500 animate-pulse"></span>
                            You are currently impersonating {user.name}.
                        </span>
                        <Link href="/settings/stop-impersonating" method="post" as="button" className="bg-amber-200 hover:bg-amber-300 px-3 py-1 rounded transition-colors">
                            Stop Impersonating
                        </Link>
                    </div>
                )}

                {/* Topbar */}
                <header                    className="h-[56px] bg-white/90 backdrop-blur-md border-b border-gray-100 flex items-center px-6 gap-3 z-10 flex-shrink-0 shadow-[0_1px_10px_rgba(0,0,0,0.01)]"
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

                        {/* Push Notification Request */}
                        {permission === 'default' && (
                            <button
                                onClick={requestNotificationPermission}
                                className="hidden md:flex items-center gap-1.5 px-3 py-1.5 bg-blue-50 hover:bg--100 text--700 border border-blue-200 text-xs font-semibold rounded-lg transition-colors shadow-sm"
                            >
                                <Bell size={13} /> Enable Alerts
                            </button>
                        )}

                        {/* Global Mark All Read */}
                        {unreadCount > 0 && (
                            <button
                                onClick={markAllReadGlobally}
                                title="Mark all notifications as read"
                                className="hidden sm:flex items-center gap-1.5 p-2 rounded-lg text-gray-400 hover:bg-indigo-50 hover:text-indigo-600 transition-colors"
                            >
                                <CheckCheck size={17} />
                            </button>
                        )}

                        {/* Notifications */}
                        <Link
                            href="/notifications"
                            className="relative p-2 rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-700 transition-colors"
                        >
                            <Bell size={17} />
                            {unreadCount > 0 && (
                                <span className="absolute top-[4px] right-[4px] min-w-[14px] h-[14px] px-[3px] bg-red-500 text-white text-[9px] font-bold rounded-full flex items-center justify-center ring-[1.5px] ring-white">
                                    {unreadCount > 99 ? '99+' : unreadCount}
                                </span>
                            )}
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
            <SearchOverlay open={searchOpen} onClose={() => setSearchOpen(false)} />
            </div>
        </ConfirmProvider>
    );
}

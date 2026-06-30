import React from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';
import { User, Building, Users, Shield, Bell, Globe, Database, Trash2, Plug, Activity, Code } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Breadcrumbs } from '@/Components/Shared/Breadcrumbs';

const SETTINGS_TABS = [
    { label: 'Profile', href: '/settings/profile', icon: User },
    { label: 'Organization', href: '/settings/organization', icon: Building },
    { label: 'Team', href: '/settings/team', icon: Users },
    { label: 'Roles', href: '/settings/roles', icon: Shield },
    { label: 'Notifications', href: '/settings/notifications', icon: Bell },
    { label: 'Localization', href: '/settings/localization', icon: Globe },
    { label: 'Integrations', href: '/settings/mcp', icon: Plug },
    { label: 'Client Portal', href: '/settings/client-portal', icon: Code },
    { label: 'Data Import', href: '/settings/import', icon: Database },
    { label: 'Trash', href: '/settings/trash', icon: Trash2 },
    { label: 'Health', href: '/settings/health', icon: Activity },
];

export default function SettingsLayout({ children, title, breadcrumbs = [] }: { children: React.ReactNode; title?: string; breadcrumbs?: { label: string; href?: string }[] }) {
    const currentPath = typeof window !== 'undefined' ? window.location.pathname : '';
    
    return (
        <AppLayout title={title ?? 'Settings'}>
            <div className="max-w-6xl mx-auto space-y-6">
                <div className="mb-6">
                    <Breadcrumbs items={[
                        { label: 'Settings', href: '/settings' },
                        ...breadcrumbs
                    ]} />
                </div>
                
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">{title ?? 'Settings'}</h1>
                    <p className="text-sm text-gray-500 mt-0.5">Manage your personal and workspace preferences</p>
                </div>
                
                <div className="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-[0_1px_3px_rgba(0,0,0,0.02)]">
                    <div className="border-b border-gray-200 overflow-x-auto hide-scrollbar">
                        <nav className="flex items-center px-2 min-w-max" aria-label="Tabs">
                            {SETTINGS_TABS.map((tab) => {
                                const Icon = tab.icon;
                                const isActive = currentPath.startsWith(tab.href);
                                return (
                                    <Link
                                        key={tab.href}
                                        href={tab.href}
                                        className={cn(
                                            'flex items-center gap-2 px-4 py-3.5 text-[13px] font-semibold whitespace-nowrap border-b-2 transition-colors',
                                            isActive 
                                                ? 'border-indigo-600 text-indigo-700 bg-indigo-50/50' 
                                                : 'border-transparent text-gray-500 hover:text-gray-900 hover:border-gray-300 hover:bg-gray-50'
                                        )}
                                    >
                                        <Icon size={16} className={isActive ? "text-indigo-600" : "text-gray-400"} />
                                        {tab.label}
                                    </Link>
                                );
                            })}
                        </nav>
                    </div>
                    
                    <div className="p-6 bg-[#fcfcfd]">
                        {children}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

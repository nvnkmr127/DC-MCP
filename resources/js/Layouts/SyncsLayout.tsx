import React from 'react';
import { Link, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { usePermissions } from '@/hooks/usePermissions';
import { cn } from '@/lib/utils';
import { ClipboardList, Users2 } from 'lucide-react';

export default function SyncsLayout({ children, title }: { children: React.ReactNode; title?: string }) {
    const { url } = usePage();
    const { hasRole } = usePermissions();
    
    const tabs = [
        { name: 'Daily Standup', href: '/standup', icon: ClipboardList, active: url.startsWith('/standup') },
    ];
    
    if (hasRole('ceo') || hasRole('project_manager')) {
        tabs.push({ name: '1:1 Notes', href: '/one-on-one', icon: Users2, active: url.startsWith('/one-on-one') });
    }

    return (
        <AppLayout title="Team Syncs">
            <div className="bg-white border-b border-gray-200 px-6 pt-4 flex gap-6 shadow-sm z-10 relative">
                {tabs.map(tab => (
                    <Link
                        key={tab.name}
                        href={tab.href}
                        className={cn(
                            'flex items-center gap-2 pb-3 -mb-[1px] border-b-2 font-medium text-sm transition-colors',
                            tab.active 
                                ? 'border-indigo-600 text-indigo-600' 
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                        )}
                    >
                        <tab.icon className="w-4 h-4 shrink-0" /> {tab.name}
                    </Link>
                ))}
            </div>
            <div className="flex-1 relative">
                {children}
            </div>
        </AppLayout>
    );
}

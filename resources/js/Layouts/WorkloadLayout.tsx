import React from 'react';
import { Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { cn } from '@/lib/utils';
import { Activity, CheckSquare, Clock } from 'lucide-react';

interface Props {
    title: string;
    currentTab: 'capacity' | 'tasks' | 'timesheets';
    children: React.ReactNode;
}

export default function WorkloadLayout({ title, currentTab, children }: Props) {
    const tabs = [
        { id: 'capacity', label: 'Overview', icon: Activity, href: '/capacity' },
        { id: 'tasks', label: 'Tasks', icon: CheckSquare, href: '/tasks' },
        { id: 'timesheets', label: 'Timesheets', icon: Clock, href: '/timesheets' },
    ];

    return (
        <AppLayout title={title}>
            <div className="max-w-7xl mx-auto space-y-6">
                
                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-end justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 tracking-tight">Team Workload</h1>
                        <p className="text-sm text-gray-500 mt-1">Manage team capacity, active tasks, and time entries.</p>
                    </div>
                </div>

                {/* Tabs */}
                <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-1">
                    <nav className="flex space-x-1" aria-label="Tabs">
                        {tabs.map((tab) => {
                            const active = currentTab === tab.id;
                            const Icon = tab.icon;
                            return (
                                <Link
                                    key={tab.id}
                                    href={tab.href}
                                    className={cn(
                                        'flex items-center gap-2 px-4 py-2.5 text-sm font-medium rounded-lg transition-all duration-200',
                                        active
                                            ? 'bg-indigo-50 text-indigo-700 shadow-sm'
                                            : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'
                                    )}
                                >
                                    <Icon size={16} className={cn('shrink-0', active ? 'text-indigo-600' : 'text-gray-400')} />
                                    {tab.label}
                                </Link>
                            );
                        })}
                    </nav>
                </div>

                {/* Content */}
                <div className="mt-6">
                    {children}
                </div>

            </div>
        </AppLayout>
    );
}

import React from 'react';
import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { cn, timeAgo } from '@/lib/utils';
import { Activity as ActivityIcon, CheckCircle2, MessageSquare, Plus, Edit, Trash2, Link as LinkIcon, UserPlus } from 'lucide-react';
import { Pagination } from '@/Components/ui/Pagination';

interface Activity {
    id: string;
    description: string;
    subject_type: string;
    subject_id: string;
    changes?: any;
    created_at: string;
    subject?: any;
}

interface Props {
    groupedActivities: Record<string, Activity[]>;
    pagination: {
        current_page: number;
        last_page: number;
        total: number;
    };
}

export default function MyActivity({ groupedActivities, pagination }: Props) {
    
    // Map activity descriptions or changes to icons and colors
    const getActivityMeta = (activity: Activity) => {
        const desc = activity.description.toLowerCase();
        
        if (desc.includes('created')) {
            return { icon: Plus, color: 'text-emerald-500', bg: 'bg-emerald-100', border: 'border-emerald-200' };
        }
        if (desc.includes('updated') || desc.includes('changed')) {
            return { icon: Edit, color: 'text-blue-500', bg: 'bg-blue-100', border: 'border-blue-200' };
        }
        if (desc.includes('deleted') || desc.includes('removed')) {
            return { icon: Trash2, color: 'text-red-500', bg: 'bg-red-100', border: 'border-red-200' };
        }
        if (desc.includes('assigned')) {
            return { icon: UserPlus, color: 'text-indigo-500', bg: 'bg-indigo-100', border: 'border-indigo-200' };
        }
        if (desc.includes('comment')) {
            return { icon: MessageSquare, color: 'text-amber-500', bg: 'bg-amber-100', border: 'border-amber-200' };
        }
        if (desc.includes('completed') || desc.includes('done')) {
            return { icon: CheckCircle2, color: 'text-green-500', bg: 'bg-green-100', border: 'border-green-200' };
        }
        return { icon: ActivityIcon, color: 'text-gray-500', bg: 'bg-gray-100', border: 'border-gray-200' };
    };

    const getSubjectLink = (activity: Activity) => {
        if (!activity.subject) return null;
        
        if (activity.subject_type.includes('Task')) {
            return (
                <Link href={`/tasks/${activity.subject.id}`} className="text-indigo-600 hover:underline font-medium">
                    {activity.subject.title || 'Task'}
                </Link>
            );
        }
        if (activity.subject_type.includes('Project')) {
            return (
                <Link href={`/projects/${activity.subject.id}`} className="text-indigo-600 hover:underline font-medium">
                    {activity.subject.name || 'Project'}
                </Link>
            );
        }
        return <span className="font-medium text-gray-700">{activity.subject.name || activity.subject.title || 'Item'}</span>;
    };

    return (
        <AppLayout title="My Activity">
            <Head title="My Activity" />
            <div className="max-w-3xl mx-auto">
                <div className="flex items-center justify-between mb-8">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 tracking-tight flex items-center gap-2">
                            <ActivityIcon className="text-indigo-600" size={24} />
                            My Activity
                        </h1>
                        <p className="text-sm text-gray-500 mt-1">A timeline of your recent actions and updates.</p>
                    </div>
                </div>

                {Object.keys(groupedActivities).length === 0 ? (
                    <div className="bg-white rounded-xl border border-gray-200 p-12 text-center shadow-sm">
                        <ActivityIcon size={32} className="mx-auto text-gray-300 mb-3" />
                        <h3 className="text-lg font-medium text-gray-900">No recent activity</h3>
                        <p className="text-sm text-gray-500 mt-1">You haven't performed any logged actions recently.</p>
                    </div>
                ) : (
                    <div className="space-y-8">
                        {Object.entries(groupedActivities).map(([dateLabel, activities]) => (
                            <div key={dateLabel}>
                                <h3 className="text-[13px] font-bold text-gray-500 uppercase tracking-wider mb-4 pl-4 border-l-2 border-indigo-500">
                                    {dateLabel}
                                </h3>
                                <div className="space-y-4">
                                    {activities.map((activity) => {
                                        const meta = getActivityMeta(activity);
                                        const Icon = meta.icon;
                                        
                                        return (
                                            <div key={activity.id} className="relative flex gap-4 p-4 rounded-xl bg-white border border-gray-100 shadow-sm hover:shadow-md transition-shadow group">
                                                <div className={cn("w-10 h-10 rounded-full flex items-center justify-center shrink-0 border", meta.bg, meta.color, meta.border)}>
                                                    <Icon size={18} />
                                                </div>
                                                <div className="flex-1 min-w-0 pt-0.5">
                                                    <div className="flex items-center justify-between gap-2 mb-1">
                                                        <p className="text-sm text-gray-900">
                                                            {activity.description} {activity.subject && <span className="text-gray-400 mx-1">on</span>} {getSubjectLink(activity)}
                                                        </p>
                                                        <span className="text-[11px] font-medium text-gray-400 whitespace-nowrap">
                                                            {timeAgo(activity.created_at)}
                                                        </span>
                                                    </div>
                                                    
                                                    {/* Optional: Render specific changes if available */}
                                                    {activity.changes && Object.keys(activity.changes).length > 0 && (
                                                        <div className="mt-2 text-[12px] bg-gray-50 rounded-lg p-3 border border-gray-100">
                                                            {Object.entries(activity.changes).map(([key, val]: [string, any]) => {
                                                                if (key === 'old' || key === 'attributes') return null; // Standard Spatie format fallback
                                                                return (
                                                                    <div key={key} className="flex gap-2">
                                                                        <span className="text-gray-500 capitalize">{key.replace('_', ' ')}:</span>
                                                                        <span className="text-gray-900 font-medium">{String(val)}</span>
                                                                    </div>
                                                                );
                                                            })}
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        ))}

                        {pagination.last_page > 1 && (
                            <div className="mt-8 pt-4 border-t border-gray-200">
                                <Pagination 
                                    current={pagination.current_page}
                                    total={pagination.last_page}
                                    onPageChange={(p) => window.location.href = `/my-activity?page=${p}`}
                                />
                            </div>
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

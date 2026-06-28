import React from 'react';
import { formatDistanceToNow } from 'date-fns';
import { User, Activity, Edit3, Plus, CheckCircle } from 'lucide-react';
import { getInitials } from '@/lib/utils';

export function ActivityTimeline({ activities }: { activities: any[] }) {
    if (!activities || activities.length === 0) {
        return (
            <div className="text-center py-8 text-slate-500">
                No activity recorded yet.
            </div>
        );
    }

    const getIcon = (event: string) => {
        switch (event) {
            case 'created': return <Plus size={16} className="text-emerald-500" />;
            case 'status_changed': return <CheckCircle size={16} className="text-blue-500" />;
            case 'assigned': return <User size={16} className="text-indigo-500" />;
            default: return <Activity size={16} className="text-slate-500" />;
        }
    };

    return (
        <div className="relative pl-6 border-l border-slate-200 dark:border-slate-700 space-y-6">
            {activities.map((activity, index) => (
                <div key={activity.id || index} className="relative">
                    <div className="absolute -left-[31px] bg-white dark:bg-slate-900 rounded-full p-1 border border-slate-200 dark:border-slate-700">
                        {getIcon(activity.event)}
                    </div>
                    <div>
                        <p className="text-sm text-slate-700 dark:text-slate-300">
                            <span className="font-medium text-slate-900 dark:text-white">
                                {activity.user ? activity.user.name : 'System'}
                            </span>{' '}
                            {activity.description}
                        </p>
                        <p className="text-xs text-slate-500 mt-1">
                            {formatDistanceToNow(new Date(activity.created_at), { addSuffix: true })}
                        </p>
                    </div>
                </div>
            ))}
        </div>
    );
}

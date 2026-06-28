import React from 'react';
import { Clock } from 'lucide-react';

interface Activity {
    id: string;
    description: string;
    created_at: string;
    user?: {
        name: string;
    };
    changes?: {
        before?: Record<string, any>;
        after?: Record<string, any>;
    };
}

interface Props {
    activities: Activity[];
}

export function ActivityLog({ activities }: Props) {
    if (!activities || activities.length === 0) {
        return (
            <div className="py-8 text-center border border-dashed border-gray-200 rounded-xl bg-gray-50/50">
                <Clock size={20} className="mx-auto text-gray-300 mb-2" />
                <p className="text-sm font-medium text-gray-900">No activity yet</p>
                <p className="text-xs text-gray-500 mt-1">Changes to this item will appear here.</p>
            </div>
        );
    }

    return (
        <div className="flow-root">
            <ul role="list" className="-mb-8">
                {activities.map((activity, activityIdx) => (
                    <li key={activity.id}>
                        <div className="relative pb-8">
                            {activityIdx !== activities.length - 1 ? (
                                <span className="absolute left-4 top-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true" />
                            ) : null}
                            <div className="relative flex space-x-3">
                                <div>
                                    <span className="h-8 w-8 rounded-full bg-gray-100 flex items-center justify-center ring-8 ring-white">
                                        <Clock size={16} className="text-gray-500" />
                                    </span>
                                </div>
                                <div className="flex min-w-0 flex-1 justify-between space-x-4 pt-1.5">
                                    <div>
                                        <p className="text-sm text-gray-500">
                                            {activity.user ? (
                                                <span className="font-medium text-gray-900 mr-1">{activity.user.name}</span>
                                            ) : null}
                                            {activity.description.replace('_', ' ')}
                                        </p>
                                        
                                        {/* Optional: Render changes if present */}
                                        {activity.changes?.after && (
                                            <div className="mt-2 text-xs border-l-2 border-indigo-200 pl-3">
                                                {Object.entries(activity.changes.after).map(([key, val]) => {
                                                    // Don't show technical fields
                                                    if (['updated_at', 'created_at', 'id'].includes(key)) return null;
                                                    
                                                    const beforeVal = activity.changes?.before?.[key];
                                                    return (
                                                        <div key={key} className="text-gray-600 mb-1">
                                                            <span className="font-semibold text-gray-900 capitalize">{key.replace('_', ' ')}</span> changed
                                                            {beforeVal ? <span className="line-through text-gray-400 mx-1">{String(beforeVal)}</span> : ''}
                                                            <span className="text-indigo-600 font-medium"> ➔ {String(val)}</span>
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                        )}
                                    </div>
                                    <div className="whitespace-nowrap text-right text-xs text-gray-500">
                                        {new Date(activity.created_at).toLocaleString()}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </li>
                ))}
            </ul>
        </div>
    );
}

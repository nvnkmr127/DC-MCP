import React, { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { X, Search } from 'lucide-react';
import { cn } from '@/lib/utils';
import { useConfirm } from '@/hooks/useConfirm';

export interface TaskDep {
    id: string;
    title: string;
    status: string;
    project_id: string;
    project?: { name: string } | null;
}

interface DependenciesSectionProps {
    taskId: string;
    dependencies: TaskDep[];
    projectTasks: TaskDep[];
}

export const DependenciesSection: React.FC<DependenciesSectionProps> = ({
    taskId,
    dependencies,
    projectTasks = [],
}) => {
    const [depSearch, setDepSearch] = useState('');
    const confirm = useConfirm();

    const availableDeps = projectTasks.filter(t =>
        t.id !== taskId &&
        !dependencies.some(d => d.id === t.id) &&
        t.title.toLowerCase().includes(depSearch.toLowerCase())
    );

    return (
        <div className="space-y-4">
            {/* Current dependencies */}
            {dependencies.length === 0 ? (
                <p className="text-sm text-gray-400 text-center py-4">No dependencies. This task can start immediately.</p>
            ) : (
                <div className="space-y-2">
                    <p className="text-xs font-medium text-gray-500 uppercase tracking-wide">Blocked by</p>
                    {dependencies.map(dep => (
                        <div key={dep.id} className="flex items-center gap-3 p-3 bg-gray-50 rounded-lg group">
                            <span className={cn('w-2 h-2 rounded-full shrink-0',
                                dep.status === 'done' ? 'bg-emerald-400' : dep.status === 'in_progress' ? 'bg-blue-400' : 'bg-rose-400'
                            )} />
                            <div className="flex-1 min-w-0">
                                <Link href={`/tasks/${dep.id}`} className="text-sm font-medium text-gray-800 hover:text-indigo-600 truncate block">
                                    {dep.title}
                                </Link>
                                <p className="text-xs text-gray-400 capitalize">
                                    {dep.status.replace('_', ' ')}{dep.project ? ` · ${dep.project.name}` : ''}
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={async () => {
                                    const ok = await confirm({
                                        title: 'Remove this dependency?',
                                        description: 'This will unblock the task if no other dependencies remain.',
                                        confirmText: 'Remove',
                                        variant: 'destructive',
                                    });
                                    if (!ok) return;
                                    router.delete(`/tasks/${taskId}/dependencies/${dep.id}`, { preserveScroll: true });
                                }}
                                className="p-1.5 text-gray-300 hover:text-rose-500 transition-colors rounded opacity-0 group-hover:opacity-100 shrink-0"
                            >
                                <X size={13} />
                            </button>
                        </div>
                    ))}
                </div>
            )}

            {/* Add dependency */}
            {projectTasks.length > 0 && (
                <div>
                    <p className="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Add dependency</p>
                    <div className="relative mb-2">
                        <Search size={12} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
                        <input
                            value={depSearch}
                            onChange={e => setDepSearch(e.target.value)}
                            placeholder="Search tasks in project…"
                            className="w-full pl-8 pr-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        />
                    </div>
                    <div className="space-y-1 max-h-48 overflow-y-auto">
                        {availableDeps.slice(0, 10).map(t => (
                            <button 
                                key={t.id} 
                                type="button"
                                onClick={() => router.post(`/tasks/${taskId}/dependencies`, { depends_on_task_id: t.id }, { preserveScroll: true })}
                                className="w-full flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-indigo-50 text-left group transition-colors"
                            >
                                <span className="text-sm text-gray-700 flex-1 truncate">{t.title}</span>
                                <span className="text-xs text-gray-400 capitalize shrink-0">{t.status.replace('_', ' ')}</span>
                            </button>
                        ))}
                        {availableDeps.length === 0 && (
                            <p className="text-xs text-gray-400 text-center py-3">No matching tasks</p>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
};

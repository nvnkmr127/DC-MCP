import React, { useState } from 'react';
import { Button } from '@/Components/ui/Button';
import { Head, router } from '@inertiajs/react';
import {
    DndContext, DragEndEvent, DragStartEvent,
    DragOverlay, closestCorners, PointerSensor, useSensor, useSensors,
} from '@dnd-kit/core';
import { SortableContext, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { cn, formatDate, dueDateLabel } from '@/lib/utils';
import type { Task, Project } from '@/types';
import { Plus, GripVertical, Clock, ChevronLeft } from 'lucide-react';
import { Link } from '@inertiajs/react';
import { StatusBadge } from '@/Components/Shared/StatusBadge';

const COLUMNS = ['backlog', 'todo', 'in_progress', 'in_review', 'blocked', 'done'] as const;
type ColKey = typeof COLUMNS[number];

const COLUMN_LABELS: Record<ColKey, string> = {
    backlog:     'Backlog',
    todo:        'To Do',
    in_progress: 'In Progress',
    in_review:   'In Review',
    blocked:     'Blocked',
    done:        'Done',
};

const COLUMN_TOP: Record<ColKey, string> = {
    backlog:     'bg-gray-300',
    todo:        'bg-blue-400',
    in_progress: 'bg-indigo-500',
    in_review:   'bg-yellow-400',
    blocked:     'bg-red-500',
    done:        'bg-emerald-500',
};



interface Props {
    project: Project;
    tasks: Task[];
}

function TaskCard({ task, isDragging = false }: { task: Task; isDragging?: boolean }) {
    const due = dueDateLabel(task.due_date);
    return (
        <div className={cn(
            'bg-white rounded-xl border border-gray-100 p-3.5 select-none',
            'hover:border-gray-200 hover:shadow-[0_2px_12px_rgba(0,0,0,0.06)] transition-all duration-100',
            isDragging && 'opacity-60 rotate-1 shadow-2xl border-indigo-200',
        )}>
            {/* Title */}
            <p className="text-[13px] font-semibold text-gray-900 mb-2.5 line-clamp-2 leading-snug">
                {task.title}
            </p>

            {/* Chips */}
            <div className="flex items-center flex-wrap gap-1.5">
                <StatusBadge type="task-priority" value={task.priority} className="text-[10px]" />
                {task.due_date && (
                    <span className={cn(
                        'flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium',
                        due.variant === 'destructive' ? 'bg--50 text--700' :
                        due.variant === 'warning'     ? 'bg--50 text--800' :
                                                        'bg-gray-50 text-gray-700',
                    )}>
                        <Clock size={12} /> {formatDate(task.due_date)}
                    </span>
                )}
                {task.estimated_hours > 0 && (
                    <span className="text-[10px] text-gray-400">{task.estimated_hours}h</span>
                )}
            </div>

            {/* Assignee */}
            {task.assignee && (
                <div className="mt-2.5 pt-2.5 border-t border-gray-50 flex items-center gap-2">
                    <div className="w-5 h-5 rounded-full bg-gradient-to-br from-indigo-400 to-violet-500 flex items-center justify-center text-[9px] font-bold text-white">
                        {task.assignee.name[0]}
                    </div>
                    <span className="text-[11px] text-gray-500 font-medium">{task.assignee.name.split(' ')[0]}</span>
                </div>
            )}
        </div>
    );
}

function SortableTaskCard({ task }: { task: Task }) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: task.id });
    return (
        <div
            ref={setNodeRef}
            style={{ transform: CSS.Transform.toString(transform), transition }}
            className="group"
        >
            <div className="flex items-start gap-1">
                <Button
                    {...attributes}
                    {...listeners}
                    className="mt-3 p-0.5 cursor-grab opacity-0 group-hover:opacity-100 transition-opacity" 
                variant="ghost" size="icon" >
                    <GripVertical size={16} />
                </Button>
                <div className="flex-1">
                    <TaskCard task={task} isDragging={isDragging} />
                </div>
            </div>
        </div>
    );
}

export default function KanbanTab({ project, tasks: initialTasks }: Props) {
    const [tasks, setTasks] = useState(initialTasks);
    const [activeTask, setActiveTask] = useState<Task | null>(null);

    const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 5 } }));

    const tasksByStatus = COLUMNS.reduce<Record<string, Task[]>>((acc, col) => {
        acc[col] = tasks.filter((t) => t.status === col);
        return acc;
    }, {});

    function handleDragStart({ active }: DragStartEvent) {
        setActiveTask(tasks.find((t) => t.id === active.id) ?? null);
    }

    function handleDragEnd({ active, over }: DragEndEvent) {
        setActiveTask(null);
        if (!over) return;
        const taskId = active.id as string;
        const overId = over.id as string;
        const newStatus = (COLUMNS as readonly string[]).includes(overId)
            ? overId
            : tasks.find((t) => t.id === overId)?.status;
        if (!newStatus) return;
        const task = tasks.find((t) => t.id === taskId);
        if (!task || task.status === newStatus) return;
        setTasks((prev) => prev.map((t) => t.id === taskId ? { ...t, status: newStatus as Task['status'] } : t));
        router.post(`/tasks/${taskId}/move`, { status: newStatus }, { preserveState: true, preserveScroll: true });
    }

    return (
        <div>
            <DndContext
                sensors={sensors}
                collisionDetection={closestCorners}
                onDragStart={handleDragStart}
                onDragEnd={handleDragEnd}
            >
                <div className="flex gap-4 sm:gap-3 overflow-x-auto pb-4 snap-x snap-mandatory sm:snap-none scroll-smooth" style={{ height: 'calc(100vh - 18rem)' }}>
                    {COLUMNS.map((col) => {
                        const colTasks = tasksByStatus[col] ?? [];
                        const topColor = COLUMN_TOP[col];
                        return (
                            <div
                                key={col}
                                className="flex flex-col w-[85vw] sm:w-[264px] shrink-0 bg-[#f0f1f3] rounded-xl overflow-hidden snap-center sm:snap-none"
                            >
                                {/* Column header */}
                                <div className="px-3 py-2.5 flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <div className={cn('w-2 h-2 rounded-full', topColor)} />
                                        <span className="text-[12px] font-semibold text-gray-700">{COLUMN_LABELS[col]}</span>
                                        <span className="min-w-[20px] h-5 px-1.5 bg-white border border-gray-200 text-gray-500 text-[10px] rounded-full font-semibold flex items-center justify-center">
                                            {colTasks.length}
                                        </span>
                                    </div>
                                    <Button
                                        onClick={() => router.get(`/tasks/create?project_id=${project.id}&status=${col}`)}
                                        className="p-1 rounded-md text-gray-400 hover:text-indigo-600 hover:bg-white transition-colors"
                                    >
                                        <Plus size={16} />
                                    </Button>
                                </div>

                                {/* Column body */}
                                <SortableContext
                                    items={colTasks.map((t) => t.id)}
                                    strategy={verticalListSortingStrategy}
                                    id={col}
                                >
                                    <div className="flex-1 overflow-y-auto px-2 pb-2 space-y-2 min-h-[60px]">
                                        {colTasks.map((task) => (
                                            <SortableTaskCard key={task.id} task={task} />
                                        ))}
                                        {colTasks.length === 0 && (
                                            <div className="h-16 rounded-lg border-2 border-dashed border-gray-200 flex items-center justify-center">
                                                <span className="text-[11px] text-gray-300">Drop here</span>
                                            </div>
                                        )}
                                    </div>
                                </SortableContext>
                            </div>
                        );
                    })}
                </div>

                <DragOverlay>
                    {activeTask && <TaskCard task={activeTask} isDragging />}
                </DragOverlay>
            </DndContext>
        </div>
    );
}

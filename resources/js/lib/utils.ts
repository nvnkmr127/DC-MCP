import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';
import { format, formatDistanceToNow, isToday, isTomorrow, isPast } from 'date-fns';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function formatDate(date: string | Date | null): string {
    if (!date) return '—';
    return format(new Date(date), 'dd MMM yyyy');
}

export function formatDateTime(date: string | Date | null): string {
    if (!date) return '—';
    return format(new Date(date), 'dd MMM yyyy, hh:mm a');
}

export function timeAgo(date: string | Date | null): string {
    if (!date) return '—';
    return formatDistanceToNow(new Date(date), { addSuffix: true });
}

export function dueDateLabel(date: string | null): { label: string; variant: 'default' | 'warning' | 'destructive' } {
    if (!date) return { label: 'No due date', variant: 'default' };
    const d = new Date(date);
    if (isToday(d)) return { label: 'Due today', variant: 'warning' };
    if (isTomorrow(d)) return { label: 'Due tomorrow', variant: 'warning' };
    if (isPast(d)) return { label: `Overdue · ${formatDate(date)}`, variant: 'destructive' };
    return { label: `Due ${formatDate(date)}`, variant: 'default' };
}

export function formatCurrency(amount: number): string {
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 0,
    }).format(amount);
}

export function formatHours(hours: number): string {
    const h = Math.floor(hours);
    const m = Math.round((hours - h) * 60);
    if (h === 0) return `${m}m`;
    if (m === 0) return `${h}h`;
    return `${h}h ${m}m`;
}

export function getInitials(name: string): string {
    return name
        .split(' ')
        .slice(0, 2)
        .map((n) => n[0])
        .join('')
        .toUpperCase();
}

export { TASK_STATUS_COLORS, PRIORITY_COLORS } from './constants';

export const TASK_COLUMNS = ['backlog', 'todo', 'in_progress', 'in_review', 'blocked', 'done'] as const;
export const TASK_COLUMN_LABELS: Record<string, string> = {
    backlog:     'Backlog',
    todo:        'To Do',
    in_progress: 'In Progress',
    in_review:   'In Review',
    blocked:     'Blocked',
    done:        'Done',
};


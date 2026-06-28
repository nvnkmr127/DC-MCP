import React from 'react';
import { Badge } from '@/Components/ui/Badge';

type BadgeVariant = 'default' | 'indigo' | 'emerald' | 'amber' | 'rose' | 'sky' | 'violet' | 'gray';

interface StatusBadgeProps {
    type?: 'task-status' | 'task-priority' | 'project-status' | 'client-tier' | 'client-status' | string;
    value: string;
    className?: string;
}

export const StatusBadge: React.FC<StatusBadgeProps> = ({ type, value, className }) => {
    if (!value) return null;
    const val = value.toLowerCase();
    
    let variant: BadgeVariant = 'default';
    let label = value.replace(/_/g, ' ');
    let showDot = true;

    if (type === 'task-status') {
        if (val === 'backlog') variant = 'gray';
        else if (val === 'todo') variant = 'sky';
        else if (val === 'in_progress') variant = 'indigo';
        else if (val === 'in_review') variant = 'amber';
        else if (val === 'blocked') variant = 'rose';
        else if (val === 'done') variant = 'emerald';
        else if (val === 'cancelled') variant = 'gray';
    } else if (type === 'task-priority') {
        if (val === 'low') variant = 'gray';
        else if (val === 'medium') variant = 'sky';
        else if (val === 'high') variant = 'amber';
        else if (val === 'critical' || val === 'urgent') variant = 'rose';
    } else if (type === 'project-status') {
        if (val === 'draft' || val === 'planning') variant = 'gray';
        else if (val === 'active') variant = 'emerald';
        else if (val === 'on_hold') variant = 'amber';
        else if (val === 'completed') variant = 'indigo';
        else if (val === 'cancelled') variant = 'rose';
    } else if (type === 'client-tier') {
        showDot = false;
        if (val === 'basic' || val === 'standard') variant = 'gray';
        else if (val === 'premium') variant = 'indigo';
        else if (val === 'enterprise') variant = 'violet';
    } else if (type === 'client-status') {
        if (val === 'active') variant = 'emerald';
        else if (val === 'inactive') variant = 'gray';
        else if (val === 'prospect') variant = 'sky';
        else if (val === 'churned') variant = 'rose';
    } else {
        // Smart Generic Fallback
        if (['active', 'completed', 'resolved', 'paid', 'approved', 'published', 'success', 'sent', 'done', 'won', 'cleared'].includes(val)) {
            variant = 'emerald';
        } else if (['failed', 'rejected', 'cancelled', 'blacklisted', 'voided', 'at_risk', 'failing', 'churned', 'blocked', 'critical', 'urgent', 'error', 'lost'].includes(val)) {
            variant = 'rose';
        } else if (['on_hold', 'pending', 'revisions_requested', 'high', 'in_review', 'warning', 'needs_attention', 'paused'].includes(val)) {
            variant = 'amber';
        } else if (['in_progress', 'processing', 'scheduled', 'syncing', 'medium', 'premium', 'open'].includes(val)) {
            variant = 'sky';
        } else if (['draft', 'inactive', 'not_started', 'planning', 'archived', 'backlog', 'low', 'unassigned', 'unknown', 'closed'].includes(val)) {
            variant = 'gray';
        } else {
            variant = 'gray'; // Safe default
        }
    }

    return (
        <Badge
            variant={variant}
            showDot={showDot}
            className={className}
        >
            <span className="capitalize">{label}</span>
        </Badge>
    );
};

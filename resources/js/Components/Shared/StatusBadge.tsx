import React from 'react';
import { Badge } from '@/Components/ui/Badge';

type BadgeVariant = 'default' | 'indigo' | 'emerald' | 'amber' | 'rose' | 'sky' | 'violet' | 'gray';

interface StatusBadgeProps {
    type: 'task-status' | 'task-priority' | 'project-status' | 'client-tier' | 'client-status';
    value: string;
    className?: string;
}

export const StatusBadge: React.FC<StatusBadgeProps> = ({ type, value, className }) => {
    if (!value) return null;
    const val = value.toLowerCase();
    
    let variant: BadgeVariant = 'default';
    let label = value;
    let showDot = true;

    if (type === 'task-status') {
        label = value.replace(/_/g, ' ');
        if (val === 'backlog') variant = 'gray';
        else if (val === 'todo') variant = 'sky';
        else if (val === 'in_progress') variant = 'indigo';
        else if (val === 'in_review') variant = 'amber';
        else if (val === 'blocked') variant = 'rose';
        else if (val === 'done') variant = 'emerald';
        else if (val === 'cancelled') variant = 'gray';
    } else if (type === 'task-priority') {
        label = value;
        if (val === 'low') variant = 'gray';
        else if (val === 'medium') variant = 'sky';
        else if (val === 'high') variant = 'amber';
        else if (val === 'critical') variant = 'rose';
        else if (val === 'urgent') variant = 'rose';
    } else if (type === 'project-status') {
        if (val === 'draft') { variant = 'gray'; label = 'Draft'; }
        else if (val === 'planning') { variant = 'gray'; label = 'Planning'; }
        else if (val === 'active') { variant = 'emerald'; label = 'Active'; }
        else if (val === 'on_hold') { variant = 'amber'; label = 'On Hold'; }
        else if (val === 'completed') { variant = 'indigo'; label = 'Completed'; }
        else if (val === 'cancelled') { variant = 'rose'; label = 'Cancelled'; }
    } else if (type === 'client-tier') {
        showDot = false;
        if (val === 'basic') { variant = 'gray'; label = 'Basic'; }
        else if (val === 'standard') { variant = 'gray'; label = 'Standard'; }
        else if (val === 'premium') { variant = 'indigo'; label = 'Premium'; }
        else if (val === 'enterprise') { variant = 'violet'; label = 'Enterprise'; }
    } else if (type === 'client-status') {
        if (val === 'active') { variant = 'emerald'; label = 'Active'; }
        else if (val === 'inactive') { variant = 'gray'; label = 'Inactive'; }
        else if (val === 'prospect') { variant = 'sky'; label = 'Prospect'; }
        else if (val === 'churned') { variant = 'rose'; label = 'Churned'; }
    }

    return (
        <Badge
            variant={variant}
            showDot={showDot}
            className={className}
        >
            {label}
        </Badge>
    );
};

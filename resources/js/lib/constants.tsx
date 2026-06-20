import React from 'react';

// Brand SVGs
export const InstagramIcon = (props: React.SVGProps<SVGSVGElement>) => (
    <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" strokeWidth="2" fill="none" strokeLinecap="round" strokeLinejoin="round" {...props}>
        <rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect>
        <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
        <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>
    </svg>
);

export const YoutubeIcon = (props: React.SVGProps<SVGSVGElement>) => (
    <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" strokeWidth="2" fill="none" strokeLinecap="round" strokeLinejoin="round" {...props}>
        <path d="M22.54 6.42a2.78 2.78 0 0 0-1.95-1.96C18.88 4 12 4 12 4s-6.88 0-8.59.46a2.78 2.78 0 0 0-1.95 1.96A29 29 0 0 0 1 12a29 29 0 0 0 .46 5.58 2.78 2.78 0 0 0 1.95 1.96C5.12 20 12 20 12 20s6.88 0 8.59-.46a2.78 2.78 0 0 0 1.95-1.96A29 29 0 0 0 23 12a29 29 0 0 0-.46-5.58z"></path>
        <polygon points="9.75 15.02 15.5 12 9.75 8.98 9.75 15.02"></polygon>
    </svg>
);

// Status and Priority color/class configurations
export const TASK_STATUS_COLORS: Record<string, string> = {
    backlog:     'bg-gray-100 text-gray-700',
    todo:        'bg-blue-100 text-blue-700',
    in_progress: 'bg-indigo-100 text-indigo-700',
    in_review:   'bg-yellow-100 text-yellow-800',
    blocked:     'bg-red-100 text-red-700',
    done:        'bg-green-100 text-green-700',
    cancelled:   'bg-gray-200 text-gray-700',
};

export const PRIORITY_COLORS: Record<string, string> = {
    low:    'bg-gray-100 text-gray-700',
    medium: 'bg-blue-100 text-blue-700',
    high:   'bg-orange-100 text-orange-800',
    critical: 'bg-red-100 text-red-700',
    urgent: 'bg-red-100 text-red-700',
};

export const PROJECT_STATUS_CONFIG: Record<string, { label: string; dot: string; badge: string }> = {
    draft:     { label: 'Draft',     dot: 'bg-gray-300',    badge: 'bg-gray-50 text-gray-700' },
    planning:  { label: 'Planning',  dot: 'bg-gray-400',    badge: 'bg-gray-100 text-gray-700' },
    active:    { label: 'Active',    dot: 'bg-emerald-500', badge: 'bg-emerald-50 text-emerald-700' },
    on_hold:   { label: 'On Hold',   dot: 'bg-yellow-400',  badge: 'bg-yellow-50 text-yellow-800' },
    completed: { label: 'Completed', dot: 'bg-blue-500',    badge: 'bg-blue-50 text-blue-700' },
    cancelled: { label: 'Cancelled', dot: 'bg-red-400',     badge: 'bg-red-50 text-red-700' },
};

export const TASK_STATUS_DOT: Record<string, string> = {
    backlog:     'bg-gray-300',
    todo:        'bg-blue-400',
    in_progress: 'bg-indigo-500',
    in_review:   'bg-yellow-400',
    blocked:     'bg-red-500', // standard Tailwind red
    done:        'bg-emerald-500',
    cancelled:   'bg-gray-400',
};

export const TASK_STATUS_CHIP: Record<string, string> = {
    backlog:     'bg-gray-100 text-gray-700',
    todo:        'bg-blue-50 text-blue-700',
    in_progress: 'bg-indigo-50 text-indigo-700',
    in_review:   'bg-yellow-50 text-yellow-800',
    blocked:     'bg-red-50 text-red-700',
    done:        'bg-emerald-50 text-emerald-700',
    cancelled:   'bg-gray-100 text-gray-700',
};

export const TASK_PRIORITY_DOT: Record<string, string> = {
    low:    'bg-gray-300',
    medium: 'bg-blue-400',
    high:   'bg-orange-400',
    critical: 'bg-red-500',
    urgent: 'bg-red-500',
};

export const TASK_PRIORITY_CHIP: Record<string, string> = {
    low:    'bg-gray-100 text-gray-700',
    medium: 'bg-blue-50 text-blue-700',
    high:   'bg-orange-50 text-orange-800',
    critical: 'bg-red-50 text-red-700',
    urgent: 'bg-red-50 text-red-700',
};

export const CLIENT_TIER_CONFIG: Record<string, { label: string; badge: string; dot: string }> = {
    basic:      { label: 'Basic',      badge: 'bg-gray-50 text-gray-700',     dot: 'bg-gray-300' },
    standard:   { label: 'Standard',   badge: 'bg-gray-100 text-gray-700',    dot: 'bg-gray-400' },
    premium:    { label: 'Premium',    badge: 'bg-indigo-50 text-indigo-700', dot: 'bg-indigo-500' },
    enterprise: { label: 'Enterprise', badge: 'bg-violet-50 text-violet-700', dot: 'bg-violet-500' },
};

export const CLIENT_STATUS_CONFIG: Record<string, { label: string; badge: string; dot: string }> = {
    active:   { label: 'Active',   badge: 'bg-emerald-50 text-emerald-700', dot: 'bg-emerald-500' },
    inactive: { label: 'Inactive', badge: 'bg-gray-100 text-gray-700',      dot: 'bg-gray-300' },
    prospect: { label: 'Prospect', badge: 'bg-yellow-50 text-yellow-800',   dot: 'bg-yellow-400' },
    churned:  { label: 'Churned',  badge: 'bg-rose-50 text-rose-800',       dot: 'bg-rose-500' },
};

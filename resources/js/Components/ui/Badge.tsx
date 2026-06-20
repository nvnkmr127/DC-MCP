import React from 'react';
import { cn } from '@/lib/utils';

export interface BadgeProps extends React.HTMLAttributes<HTMLSpanElement> {
    variant?: 'default' | 'indigo' | 'emerald' | 'amber' | 'rose' | 'sky' | 'violet' | 'gray';
    showDot?: boolean;
    dotClassName?: string;
}

export const Badge = React.forwardRef<HTMLSpanElement, BadgeProps>(
    ({ className, variant = 'default', showDot, dotClassName, children, ...props }, ref) => {
        return (
            <span
                ref={ref}
                className={cn(
                    'inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-bold tracking-wide shrink-0',
                    
                    // Variants
                    variant === 'default' && 'bg-gray-150 text-gray-700',
                    variant === 'gray' && 'bg-gray-100 text-gray-700',
                    variant === 'indigo' && 'bg-indigo-50 text-indigo-700 border border-indigo-100/50',
                    variant === 'emerald' && 'bg-emerald-50 text-emerald-700 border border-emerald-100/50',
                    variant === 'amber' && 'bg--50 text--800 border border-yellow-100/50',
                    variant === 'rose' && 'bg-rose-50 text-rose-700 border border-rose-100/50',
                    variant === 'sky' && 'bg-sky-50 text-sky-700 border border-sky-100/50',
                    variant === 'violet' && 'bg-violet-50 text-violet-700 border border-violet-100/50',
                    
                    className
                )}
                {...props}
            >
                {showDot && (
                    <span
                        className={cn(
                            'w-1.5 h-1.5 rounded-full shrink-0',
                            variant === 'gray' && 'bg-gray-400',
                            variant === 'indigo' && 'bg-indigo-550',
                            variant === 'emerald' && 'bg-emerald-500',
                            variant === 'amber' && 'bg-yellow-500',
                            variant === 'rose' && 'bg-rose-500',
                            variant === 'sky' && 'bg-sky-500',
                            variant === 'violet' && 'bg-violet-500',
                            dotClassName
                        )}
                    />
                )}
                {children}
            </span>
        );
    }
);

Badge.displayName = 'Badge';

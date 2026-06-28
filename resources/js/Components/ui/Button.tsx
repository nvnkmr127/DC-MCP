import React from 'react';
import { cn } from '@/lib/utils';
import { Loader2 } from 'lucide-react';

export interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: 'default' | 'outline' | 'destructive' | 'ghost' | 'secondary';
    size?: 'sm' | 'md' | 'lg' | 'icon';
    loading?: boolean;
}

export const Button = React.forwardRef<HTMLButtonElement, ButtonProps>(
    ({ className, variant = 'default', size = 'md', loading, children, disabled, ...props }, ref) => {
        return (
            <button
                ref={ref}
                disabled={disabled || loading}
                className={cn(
                    'inline-flex items-center justify-center font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50 disabled:pointer-events-none rounded-xl active:scale-[0.98] duration-150',
                    
                    // Variants
                    variant === 'default' && 'bg-indigo-600 text-white hover:bg-indigo-750 shadow-sm border border-indigo-700/10',
                    variant === 'outline' && 'bg-white text-gray-700 border border-gray-200 hover:bg-gray-50 hover:text-gray-900',
                    variant === 'destructive' && 'bg-red-600 text-white hover:bg-red-700 shadow-sm',
                    variant === 'ghost' && 'text-gray-600 hover:bg-gray-50 hover:text-gray-900',
                    variant === 'secondary' && 'bg-gray-100 text-gray-900 hover:bg-gray-200',

                    // Sizes
                    size === 'sm' && 'px-3 py-1.5 text-xs',
                    size === 'md' && 'px-4 py-2 text-sm',
                    size === 'lg' && 'px-6 py-3 text-base',
                    size === 'icon' && 'p-1.5',
                    className
                )}
                {...props}
            >
                {loading && (
                    <Loader2 className="animate-spin -ml-1 mr-2 h-4 w-4 text-current" />
                )}
                {children}
            </button>
        );
    }
);

Button.displayName = 'Button';

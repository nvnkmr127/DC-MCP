import React from 'react';
import { cn } from '@/lib/utils';

export interface LabelProps extends React.LabelHTMLAttributes<HTMLLabelElement> {}

export const Label = React.forwardRef<HTMLLabelElement, LabelProps>(
    ({ className, children, ...props }, ref) => (
        <label
            ref={ref}
            className={cn('block text-xs font-bold text-gray-500 mb-1 uppercase tracking-wider', className)}
            {...props}
        >
            {children}
        </label>
    )
);
Label.displayName = 'Label';

export interface InputProps extends React.InputHTMLAttributes<HTMLInputElement> {
    error?: string | boolean;
}

export const Input = React.forwardRef<HTMLInputElement, InputProps>(
    ({ className, error, ...props }, ref) => (
        <input
            ref={ref}
            className={cn(
                'w-full px-3.5 py-2 text-sm border rounded-xl bg-white text-gray-900 focus:outline-none focus:ring-2 transition-all placeholder-gray-400 font-medium',
                error 
                    ? 'border-red-300 focus:ring-red-500/20 focus:border-red-500' 
                    : 'border-gray-200 hover:border-gray-300 focus:ring-indigo-500/20 focus:border-indigo-500',
                className
            )}
            {...props}
        />
    )
);
Input.displayName = 'Input';

export interface TextAreaProps extends React.TextareaHTMLAttributes<HTMLTextAreaElement> {
    error?: string | boolean;
}

export const TextArea = React.forwardRef<HTMLTextAreaElement, TextAreaProps>(
    ({ className, error, ...props }, ref) => (
        <textarea
            ref={ref}
            className={cn(
                'w-full px-3.5 py-2 text-sm border rounded-xl bg-white text-gray-900 focus:outline-none focus:ring-2 transition-all placeholder-gray-400 font-medium resize-none',
                error 
                    ? 'border-red-300 focus:ring-red-500/20 focus:border-red-500' 
                    : 'border-gray-200 hover:border-gray-300 focus:ring-indigo-500/20 focus:border-indigo-500',
                className
            )}
            {...props}
        />
    )
);
TextArea.displayName = 'TextArea';

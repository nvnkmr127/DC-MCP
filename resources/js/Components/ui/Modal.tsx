import React, { Fragment } from 'react';

export default function Modal({
    children,
    show = false,
    maxWidth = '2xl',
    closeable = true,
    onClose = () => {},
}: {
    children: React.ReactNode;
    show: boolean;
    maxWidth?: 'sm' | 'md' | 'lg' | 'xl' | '2xl';
    closeable?: boolean;
    onClose: () => void;
}) {
    if (!show) return null;

    const maxWidthClass = {
        sm: 'sm:max-w-sm',
        md: 'sm:max-w-md',
        lg: 'sm:max-w-lg',
        xl: 'sm:max-w-xl',
        '2xl': 'sm:max-w-2xl',
    }[maxWidth];

    return (
        <div className="relative z-50">
            <div className="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/80 transition-opacity" onClick={() => closeable && onClose()} />

            <div className="fixed inset-0 z-10 overflow-y-auto pointer-events-none">
                <div className="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <div
                        className={`pointer-events-auto relative transform overflow-hidden rounded-xl bg-white dark:bg-slate-800 text-left shadow-xl transition-all sm:my-8 sm:w-full ${maxWidthClass}`}
                    >
                        {children}
                    </div>
                </div>
            </div>
        </div>
    );
}

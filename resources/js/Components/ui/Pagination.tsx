import React from 'react';
import { cn } from '@/lib/utils';

export interface PaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface PaginationProps {
    meta?: PaginationMeta | null;
    onPageChange: (page: number) => void;
    labelSingular?: string;
    labelPlural?: string;
    className?: string;
    alwaysShowCount?: boolean;
}

export const Pagination: React.FC<PaginationProps> = ({
    meta,
    onPageChange,
    labelSingular = 'item',
    labelPlural = 'items',
    className,
    alwaysShowCount = true,
}) => {
    if (!meta) return null;
    
    if (meta.last_page <= 1 && !alwaysShowCount) return null;

    const startItem = (meta.current_page - 1) * meta.per_page + 1;
    const endItem = Math.min(meta.current_page * meta.per_page, meta.total);
    const label = meta.total === 1 ? labelSingular : labelPlural;

    return (
        <div className={cn('flex items-center justify-between px-4 py-3 border-t border-gray-100', className)}>
            <p className="text-[12px] text-gray-400">
                {startItem}–{endItem} of {meta.total} {label}
            </p>
            <div className="flex gap-1">
                {meta.last_page > 1 && Array.from({ length: meta.last_page }, (_, i) => i + 1).map((page) => (
                    <button
                        key={page}
                        type="button"
                        onClick={() => onPageChange(page)}
                        className={cn(
                            'w-8 h-8 rounded-lg text-[12px] font-medium transition-colors',
                            page === meta.current_page
                                ? 'bg-indigo-600 text-white'
                                : 'text-gray-500 hover:bg-gray-100',
                        )}
                    >
                        {page}
                    </button>
                ))}
            </div>
        </div>
    );
};

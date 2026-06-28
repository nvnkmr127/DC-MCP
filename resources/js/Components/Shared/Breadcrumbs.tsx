import React from 'react';
import { Link } from '@inertiajs/react';
import { ChevronRight, Home } from 'lucide-react';
import { cn } from '@/lib/utils';

export interface BreadcrumbItem {
    label: string;
    href?: string;
}

interface BreadcrumbsProps {
    items: BreadcrumbItem[];
    className?: string;
}

export function Breadcrumbs({ items, className }: BreadcrumbsProps) {
    if (!items || items.length === 0) return null;

    return (
        <nav className={cn("flex items-center space-x-1 text-sm text-gray-500", className)} aria-label="Breadcrumb">
            <Link 
                href="/dashboard" 
                className="p-1 hover:bg-gray-100 rounded-md transition-colors text-gray-400 hover:text-gray-700"
            >
                <Home size={16} />
            </Link>
            
            {items.map((item, index) => {
                const isLast = index === items.length - 1;
                
                return (
                    <div key={index} className="flex items-center">
                        <ChevronRight size={16} className="mx-1 text-gray-300 shrink-0" />
                        {isLast || !item.href ? (
                            <span className="font-semibold text-gray-800" aria-current="page">
                                {item.label}
                            </span>
                        ) : (
                            <Link
                                href={item.href}
                                className="hover:text-indigo-600 transition-colors"
                            >
                                {item.label}
                            </Link>
                        )}
                    </div>
                );
            })}
        </nav>
    );
}

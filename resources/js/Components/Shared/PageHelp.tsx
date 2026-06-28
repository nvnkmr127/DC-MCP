import React from 'react';
import { HelpCircle } from 'lucide-react';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/Components/ui/Tooltip';

interface Props {
    content: string | React.ReactNode;
}

export function PageHelp({ content }: Props) {
    return (
        <TooltipProvider delayDuration={100}>
            <Tooltip>
                <TooltipTrigger asChild>
                    <button type="button" className="text-gray-400 hover:text-indigo-600 focus:outline-none transition-colors">
                        <HelpCircle size={16} />
                    </button>
                </TooltipTrigger>
                <TooltipContent side="right" className="max-w-xs text-xs leading-relaxed shadow-lg">
                    {content}
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
}

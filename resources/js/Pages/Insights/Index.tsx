import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { cn } from '@/lib/utils';
import BriefingsTab from './BriefingsTab';
import SuggestionsTab from './SuggestionsTab';

export default function InsightsIndex(props: any) {
    const [activeTab, setActiveTab] = useState<'briefings' | 'suggestions' | 'custom_reports'>('briefings');

    return (
        <AppLayout title="Insights">
            <Head title="Insights" />
            <div className="max-w-6xl mx-auto space-y-6">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Insights Hub</h1>
                        <p className="text-sm text-gray-500 mt-0.5">Daily Briefings, AI Suggestions, and Custom Reports</p>
                    </div>
                </div>

                {/* Tabs Navigation */}
                <div className="flex border-b border-gray-200">
                    <button
                        onClick={() => setActiveTab('briefings')}
                        className={cn(
                            "pb-3 px-1 text-sm font-medium mr-8 border-b-2 transition-colors",
                            activeTab === 'briefings' ? "border-indigo-600 text-indigo-600" : "border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300"
                        )}
                    >
                        Today's Briefing
                    </button>
                    <button
                        onClick={() => setActiveTab('suggestions')}
                        className={cn(
                            "pb-3 px-1 text-sm font-medium mr-8 border-b-2 transition-colors",
                            activeTab === 'suggestions' ? "border-indigo-600 text-indigo-600" : "border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300"
                        )}
                    >
                        Suggestions
                    </button>
                    <button
                        onClick={() => setActiveTab('custom_reports')}
                        className={cn(
                            "pb-3 px-1 text-sm font-medium border-b-2 transition-colors",
                            activeTab === 'custom_reports' ? "border-indigo-600 text-indigo-600" : "border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300"
                        )}
                    >
                        Custom Reports
                    </button>
                </div>

                {activeTab === 'briefings' && <BriefingsTab briefings={props.briefings} />}
                {activeTab === 'suggestions' && <SuggestionsTab pending={props.pending} recent={props.recent} stats={props.suggestionStats} projects={props.projects} clients={props.clients} />}
                {activeTab === 'custom_reports' && (
                    <div className="bg-white rounded-xl border border-dashed border-gray-200 py-12 flex flex-col items-center justify-center text-center">
                        <h3 className="text-sm font-medium text-gray-900">Custom Reports</h3>
                        <p className="text-sm text-gray-500 mt-1 max-w-sm">Build your own custom insights (Coming Soon).</p>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

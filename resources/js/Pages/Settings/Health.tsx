import React from 'react';
import { Breadcrumbs } from '@/Components/Shared/Breadcrumbs';
import { Head } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

export default function Health({ status }: { status: Record<string, boolean> }) {
    return (
        <AppLayout title="System Health">
            <Head title="System Health" />
            <div className="mb-6">
                <Breadcrumbs items={[
                    { label: 'Settings', href: '/settings' },
                    { label: 'System Health' }
                ]} />
            </div>
            <div className="max-w-4xl mx-auto py-8">
                <h1 className="text-2xl font-bold mb-6">System Health</h1>
                <div className="bg-white rounded-xl border border-gray-200 p-6">
                    <div className="space-y-4">
                        {Object.entries(status).map(([key, val]) => (
                            <div key={key} className="flex justify-between items-center p-3 border rounded-lg">
                                <span className="capitalize font-medium text-gray-700">{key}</span>
                                <span className={`px-2 py-1 rounded text-xs font-bold ${val ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                                    {val ? 'OPERATIONAL' : 'OFFLINE'}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

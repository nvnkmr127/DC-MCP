import React from 'react';
import { Head } from '@inertiajs/react';
import { Building2 } from 'lucide-react';

export default function PortalLogin() {
    return (
        <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
            <Head title="Client Portal" />
            <div className="bg-white rounded-2xl shadow-xl w-full max-w-md p-8 text-center space-y-4">
                <div className="w-14 h-14 rounded-2xl bg-gradient-to-br from-indigo-50 to-violet-50 flex items-center justify-center mx-auto">
                    <Building2 size={24} className="text-indigo-500" />
                </div>
                <h1 className="text-xl font-bold text-gray-900">Client Portal</h1>
                <p className="text-sm text-gray-500">
                    Access your project reports, deliverables, and updates.
                    Use the magic link sent to your email to sign in.
                </p>
                <div className="bg-indigo-50 border border-indigo-100 rounded-xl px-4 py-3 text-sm text-indigo-700">
                    Check your email for a login link from your account manager.
                </div>
                <p className="text-xs text-gray-400">
                    Links expire after 7 days. Contact your account manager to request a new one.
                </p>
            </div>
        </div>
    );
}

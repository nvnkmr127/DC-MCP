import React from 'react';
import { Button } from '@/Components/ui/Button';
import { Head, Link } from '@inertiajs/react';
import { ShieldAlert, ArrowLeft } from 'lucide-react';

export default function Forbidden({ message }: { message?: string }) {
    return (
        <div className="min-h-screen bg-slate-50 flex flex-col items-center justify-center p-6">
            <Head title="Access Denied" />
            <div className="max-w-md w-full bg-white rounded-2xl shadow-xl border border-gray-100 p-8 text-center">
                <div className="w-16 h-16 bg-red-50 rounded-2xl flex items-center justify-center mx-auto mb-6 shadow-inner">
                    <ShieldAlert size={32} className="text-red-500" />
                </div>
                <h1 className="text-2xl font-bold text-gray-900 mb-2">Access Denied</h1>
                <p className="text-gray-500 mb-8 leading-relaxed">
                    {message || "You don't have permission to view this page or perform this action. If you believe this is a mistake, please contact your administrator."}
                </p>
                <div className="flex flex-col gap-3">
                    <Link
                        href="/dashboard"
                        className="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-indigo-600 text-white font-medium rounded-xl hover:bg-indigo-700 transition-colors shadow-sm"
                    >
                        Return to Dashboard
                    </Link>
                    <Button
                        onClick={() => window.history.back()}
                        className="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-white text-gray-700 font-medium rounded-xl hover:bg-gray-50 transition-colors border border-gray-200 shadow-sm"
                    >
                        <ArrowLeft size={16} /> Go Back
                    </Button>
                </div>
            </div>
        </div>
    );
}

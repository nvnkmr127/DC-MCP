import React from 'react';
import { Head } from '@inertiajs/react';

export default function PortalLogin() {
    return (
        <div className="min-h-screen bg-gradient-to-br from-[#0e1017] to-[#1a1f2e] flex items-center justify-center p-4">
            <Head title="Client Portal" />
            <div className="w-full max-w-sm">
                <div className="text-center mb-8">
                    <div className="w-12 h-12 bg-indigo-600 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <span className="text-white font-bold text-xl">D</span>
                    </div>
                    <h1 className="text-xl font-bold text-white">Client Portal</h1>
                    <p className="text-gray-400 text-sm mt-1">Digicloudify</p>
                </div>

                <div className="bg-white/5 border border-white/10 rounded-2xl p-6 text-center">
                    <p className="text-gray-300 text-sm leading-relaxed">
                        Access your portal using the magic link sent to your email.
                    </p>
                    <p className="text-gray-500 text-xs mt-3">
                        Contact your account manager if you haven't received your link.
                    </p>
                </div>
            </div>
        </div>
    );
}

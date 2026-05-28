import React from 'react';
import { Zap, Shield, Users, BarChart3 } from 'lucide-react';

const FEATURES = [
    { icon: BarChart3, label: 'Live dashboards',    desc: 'Real-time KPIs and team metrics' },
    { icon: Users,     label: 'Team collaboration', desc: 'Roles, permissions & workspaces' },
    { icon: Shield,    label: 'Enterprise security', desc: 'SSO, audit logs, and access control' },
];

export default function AuthLayout({ children }: { children: React.ReactNode }) {
    return (
        <div className="min-h-screen flex bg-white">

            {/* ── Left panel (brand) ── */}
            <div className="hidden lg:flex w-[480px] flex-shrink-0 flex-col bg-[#1a1d23] p-10 relative overflow-hidden">
                {/* Decorative glow */}
                <div className="absolute -top-32 -left-32 w-96 h-96 bg-indigo-600/20 rounded-full blur-3xl pointer-events-none" />
                <div className="absolute -bottom-32 -right-20 w-80 h-80 bg-violet-600/15 rounded-full blur-3xl pointer-events-none" />

                {/* Logo */}
                <div className="relative flex items-center gap-3">
                    <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center shadow-lg">
                        <Zap size={18} className="text-white" />
                    </div>
                    <div>
                        <p className="text-[15px] font-bold text-white leading-tight">Digicloudify</p>
                        <p className="text-[11px] text-[#4d5563] leading-tight">Operations Platform</p>
                    </div>
                </div>

                {/* Headline */}
                <div className="relative mt-auto mb-10">
                    <h2 className="text-3xl font-bold text-white leading-snug mb-4">
                        The workspace<br />
                        your team deserves
                    </h2>
                    <p className="text-[14px] text-[#7d8898] leading-relaxed">
                        Manage projects, track tasks, and ship work faster — all in one beautifully crafted platform.
                    </p>
                </div>

                {/* Feature list */}
                <div className="relative space-y-4">
                    {FEATURES.map(({ icon: Icon, label, desc }) => (
                        <div key={label} className="flex items-start gap-3">
                            <div className="w-8 h-8 rounded-lg bg-[#252930] flex items-center justify-center shrink-0 mt-0.5">
                                <Icon size={15} className="text-indigo-400" />
                            </div>
                            <div>
                                <p className="text-[13px] font-semibold text-[#c9d1db]">{label}</p>
                                <p className="text-[12px] text-[#5c6370]">{desc}</p>
                            </div>
                        </div>
                    ))}
                </div>

                {/* Footer */}
                <p className="relative mt-10 text-[11px] text-[#3a4050]">
                    © {new Date().getFullYear()} Digicloudify · All rights reserved
                </p>
            </div>

            {/* ── Right panel (form) ── */}
            <div className="flex-1 flex items-center justify-center p-6 bg-[#f4f5f7]">
                <div className="w-full max-w-sm">
                    {/* Mobile logo */}
                    <div className="flex lg:hidden items-center justify-center gap-2 mb-8">
                        <div className="w-8 h-8 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center">
                            <Zap size={15} className="text-white" />
                        </div>
                        <span className="text-lg font-bold text-gray-900">Digicloudify</span>
                    </div>

                    <div className="bg-white rounded-2xl border border-gray-200 p-8 shadow-sm">
                        {children}
                    </div>
                </div>
            </div>
        </div>
    );
}

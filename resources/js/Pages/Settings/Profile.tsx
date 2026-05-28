import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { cn, getInitials } from '@/lib/utils';
import { User } from '@/types';
import { User as UserIcon, Lock, Globe, Save } from 'lucide-react';

interface Props {
    user: Pick<User, 'id' | 'name' | 'email' | 'timezone'>;
}

function Field({
    label, hint, error, children,
}: {
    label: string; hint?: string; error?: string; children: React.ReactNode;
}) {
    return (
        <div>
            <label className="block text-[12px] font-semibold text-gray-700 mb-1.5">{label}</label>
            {hint && <p className="text-[11px] text-gray-400 mb-1.5">{hint}</p>}
            {children}
            {error && <p className="mt-1.5 text-[11px] text-red-500 font-medium">{error}</p>}
        </div>
    );
}

const inputCls = 'w-full px-3.5 py-2.5 border border-gray-200 rounded-lg text-[13px] bg-gray-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all placeholder-gray-400';

export default function ProfileSettings({ user }: Props) {
    const profileForm = useForm({
        name:     user.name,
        email:    user.email,
        timezone: user.timezone ?? 'Asia/Kolkata',
    });

    const passwordForm = useForm({
        current_password:      '',
        password:              '',
        password_confirmation: '',
    });

    function submitProfile(e: React.FormEvent) {
        e.preventDefault();
        profileForm.patch('/settings/profile');
    }

    function submitPassword(e: React.FormEvent) {
        e.preventDefault();
        passwordForm.patch('/settings/password', { onSuccess: () => passwordForm.reset() });
    }

    return (
        <AppLayout title="Profile Settings">
            <Head title="Profile Settings" />

            <div className="max-w-2xl space-y-5">

                {/* Avatar + identity card */}
                <div className="bg-white rounded-xl border border-gray-100 p-6 shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
                    <div className="flex items-center gap-4 mb-6 pb-5 border-b border-gray-100">
                        <div className="w-14 h-14 rounded-full bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center text-lg font-bold text-white shrink-0">
                            {getInitials(user.name)}
                        </div>
                        <div>
                            <p className="text-[15px] font-bold text-gray-900">{user.name}</p>
                            <p className="text-[12px] text-gray-400 mt-0.5">{user.email}</p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2 mb-5">
                        <div className="w-6 h-6 rounded-md bg-indigo-50 flex items-center justify-center">
                            <UserIcon size={13} className="text-indigo-600" />
                        </div>
                        <h2 className="text-[13px] font-semibold text-gray-900">Profile Information</h2>
                    </div>

                    <form onSubmit={submitProfile} className="space-y-4">
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <Field label="Full name" error={profileForm.errors.name}>
                                <input
                                    type="text"
                                    value={profileForm.data.name}
                                    onChange={e => profileForm.setData('name', e.target.value)}
                                    className={inputCls}
                                    required
                                />
                            </Field>
                            <Field label="Email address" error={profileForm.errors.email}>
                                <input
                                    type="email"
                                    value={profileForm.data.email}
                                    onChange={e => profileForm.setData('email', e.target.value)}
                                    className={inputCls}
                                    required
                                />
                            </Field>
                        </div>
                        <Field label="Timezone" hint="Used for scheduling and due date display">
                            <select
                                value={profileForm.data.timezone}
                                onChange={e => profileForm.setData('timezone', e.target.value)}
                                className={inputCls}
                            >
                                <option value="Asia/Kolkata">Asia/Kolkata (IST +5:30)</option>
                                <option value="UTC">UTC</option>
                                <option value="America/New_York">America/New_York (ET)</option>
                                <option value="America/Los_Angeles">America/Los_Angeles (PT)</option>
                                <option value="Europe/London">Europe/London (GMT)</option>
                                <option value="Asia/Singapore">Asia/Singapore (SGT)</option>
                                <option value="Asia/Dubai">Asia/Dubai (GST)</option>
                            </select>
                        </Field>
                        <div className="pt-1">
                            <button
                                type="submit"
                                disabled={profileForm.processing}
                                className="flex items-center gap-1.5 px-4 py-2 bg-indigo-600 text-white text-[13px] font-semibold rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors shadow-sm"
                            >
                                <Save size={13} />
                                {profileForm.processing ? 'Saving…' : 'Save changes'}
                            </button>
                        </div>
                    </form>
                </div>

                {/* Password card */}
                <div className="bg-white rounded-xl border border-gray-100 p-6 shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
                    <div className="flex items-center gap-2 mb-5">
                        <div className="w-6 h-6 rounded-md bg-red-50 flex items-center justify-center">
                            <Lock size={13} className="text-red-500" />
                        </div>
                        <h2 className="text-[13px] font-semibold text-gray-900">Change Password</h2>
                    </div>
                    <form onSubmit={submitPassword} className="space-y-4">
                        <Field label="Current password" error={passwordForm.errors.current_password}>
                            <input
                                type="password"
                                value={passwordForm.data.current_password}
                                onChange={e => passwordForm.setData('current_password', e.target.value)}
                                className={inputCls}
                                required
                            />
                        </Field>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <Field label="New password" error={passwordForm.errors.password}>
                                <input
                                    type="password"
                                    value={passwordForm.data.password}
                                    onChange={e => passwordForm.setData('password', e.target.value)}
                                    className={inputCls}
                                    required
                                />
                            </Field>
                            <Field label="Confirm new password">
                                <input
                                    type="password"
                                    value={passwordForm.data.password_confirmation}
                                    onChange={e => passwordForm.setData('password_confirmation', e.target.value)}
                                    className={inputCls}
                                    required
                                />
                            </Field>
                        </div>
                        <div className="pt-1">
                            <button
                                type="submit"
                                disabled={passwordForm.processing}
                                className="flex items-center gap-1.5 px-4 py-2 bg-gray-900 text-white text-[13px] font-semibold rounded-lg hover:bg-gray-800 disabled:opacity-50 transition-colors shadow-sm"
                            >
                                <Lock size={13} />
                                {passwordForm.processing ? 'Updating…' : 'Update password'}
                            </button>
                        </div>
                    </form>
                </div>

                {/* Danger zone */}
                <div className="bg-white rounded-xl border border-red-100 p-5">
                    <h2 className="text-[13px] font-semibold text-red-600 mb-1">Danger zone</h2>
                    <p className="text-[12px] text-gray-400 mb-4">Permanently delete your account and all associated data.</p>
                    <button
                        type="button"
                        className="px-4 py-2 border border-red-200 text-red-600 text-[12px] font-semibold rounded-lg hover:bg-red-50 transition-colors"
                    >
                        Delete account
                    </button>
                </div>
            </div>
        </AppLayout>
    );
}

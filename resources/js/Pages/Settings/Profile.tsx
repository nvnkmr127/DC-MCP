import React, { useState } from 'react';
import { Button } from '@/Components/ui/Button';
import { Breadcrumbs } from '@/Components/Shared/Breadcrumbs';
import { Head, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { cn, getInitials } from '@/lib/utils';
import { User, PageProps } from '@/types';
import { User as UserIcon, Lock, Globe, Save, Key, MonitorSmartphone, Link as LinkIcon, Download, Trash2, Copy, Check } from 'lucide-react';
import { useConfirm } from '@/hooks/useConfirm';
import { toast } from 'sonner';

interface Session {
    id: string;
    ip_address: string;
    user_agent: string;
    last_activity: string;
    is_current: boolean;
}

interface ApiToken {
    id: string;
    name: string;
    last_used_at: string | null;
    created_at: string;
}

interface ConnectedAccount {
    id: string;
    provider: string;
}

interface Props extends PageProps {
    user: Pick<User, 'id' | 'name' | 'email' | 'timezone'> & {
        display_name?: string | null;
        phone?: string | null;
        created_at?: string;
        avatar_url?: string | null;
    };
    sessions: Session[];
    tokens: ApiToken[];
    connectedAccounts: ConnectedAccount[];
    flash: {
        success: string | null;
        error: string | null;
        new_token: string | null;
    };
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

export default function ProfileSettings({ user, sessions, tokens, connectedAccounts, flash }: Props) {
    const confirm = useConfirm();
    const [copied, setCopied] = useState(false);

    const profileForm = useForm({
        name:         user.name,
        display_name: user.display_name || '',
        email:        user.email,
        phone:        user.phone || '',
        timezone:     user.timezone ?? 'Asia/Kolkata',
        avatar:       null as File | null,
        _method:      'patch',
    });

    const passwordForm = useForm({
        current_password:      '',
        password:              '',
        password_confirmation: '',
    });

    const tokenForm = useForm({
        token_name: '',
    });

    const deleteAccountForm = useForm({});

    function submitProfile(e: React.FormEvent) {
        e.preventDefault();
        profileForm.post('/settings/profile', {
            preserveScroll: true,
            forceFormData: true,
        });
    }

    function submitPassword(e: React.FormEvent) {
        e.preventDefault();
        passwordForm.patch('/settings/password', { onSuccess: () => passwordForm.reset() });
    }

    function createToken(e: React.FormEvent) {
        e.preventDefault();
        tokenForm.post('/settings/tokens', {
            preserveScroll: true,
            onSuccess: () => tokenForm.reset('token_name'),
        });
    }

    function revokeToken(id: string) {
        confirm({
            title: 'Revoke API Token',
            description: 'Are you sure you want to revoke this API token? Any applications using it will lose access.',
            confirmText: 'Revoke',
            onConfirm: () => {
                tokenForm.delete(`/settings/tokens/${id}`, { preserveScroll: true });
            }
        });
    }

    function destroySession(id: string) {
        confirm({
            title: 'Terminate Session',
            description: 'Are you sure you want to log out from this device?',
            confirmText: 'Terminate',
            onConfirm: () => {
                profileForm.delete(`/settings/sessions/${id}`, { preserveScroll: true });
            }
        });
    }

    function exportData() {
        window.location.href = '/settings/export-data';
    }

    function deleteAccount() {
        confirm({
            title: 'Delete Account',
            description: 'Are you absolutely sure you want to delete your account? This action cannot be undone.',
            confirmText: 'Delete My Account',
            onConfirm: () => {
                deleteAccountForm.delete('/settings/account');
            }
        });
    }

    function copyToken(token: string) {
        navigator.clipboard.writeText(token);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
        toast.success("Token copied to clipboard");
    }

    return (
        <AppLayout title="Account Settings">
            <Head title="Account Settings" />
            <div className="mb-6">
                <Breadcrumbs items={[
                    { label: 'Settings', href: '/settings' },
                    { label: 'Account Settings' }
                ]} />
            </div>

            <div className="max-w-2xl space-y-5">
                {/* Avatar + identity card */}
                <div className="bg-white rounded-xl border border-gray-100 p-6 shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
                    <div className="flex items-center gap-4 mb-6 pb-5 border-b border-gray-100">
                        <div className="relative group">
                            {user.avatar_url ? (
                                <img src={user.avatar_url} alt="Avatar" className="w-16 h-16 rounded-full object-cover" />
                            ) : (
                                <div className="w-16 h-16 rounded-full bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center text-xl font-bold text-white shrink-0">
                                    {getInitials(user.name)}
                                </div>
                            )}
                            <label className="absolute inset-0 bg-black/40 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 cursor-pointer transition-opacity">
                                <span className="text-[10px] text-white font-bold uppercase tracking-wide">Upload</span>
                                <input 
                                    type="file" 
                                    className="hidden" 
                                    accept="image/*"
                                    onChange={e => {
                                        if (e.target.files && e.target.files.length > 0) {
                                            profileForm.setData('avatar', e.target.files[0]);
                                        }
                                    }}
                                />
                            </label>
                        </div>
                        <div>
                            <p className="text-[15px] font-bold text-gray-900">{user.name}</p>
                            <p className="text-[12px] text-gray-400 mt-0.5">{user.email}</p>
                            {user.created_at && (
                                <p className="text-[11px] text-gray-400 mt-1">Account created on: {new Date(user.created_at).toLocaleDateString()}</p>
                            )}
                            {profileForm.data.avatar && (
                                <p className="text-[11px] text-indigo-600 mt-1 font-semibold">Avatar ready to save.</p>
                            )}
                            {profileForm.errors.avatar && (
                                <p className="text-[11px] text-red-500 mt-1">{profileForm.errors.avatar}</p>
                            )}
                        </div>
                    </div>

                    <div className="flex items-center gap-2 mb-5">
                        <div className="w-6 h-6 rounded-md bg-indigo-50 flex items-center justify-center">
                            <UserIcon size={16} className="text-indigo-600" />
                        </div>
                        <h2 className="text-[13px] font-semibold text-gray-900">Profile Information</h2>
                    </div>

                    <form onSubmit={submitProfile} className="space-y-4">
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <Field label="Legal name" error={profileForm.errors.name}>
                                <input type="text" value={profileForm.data.name} onChange={e => profileForm.setData('name', e.target.value)} className={inputCls} required />
                            </Field>
                            <Field label="Display name (Nickname)" error={profileForm.errors.display_name}>
                                <input type="text" value={profileForm.data.display_name} onChange={e => profileForm.setData('display_name', e.target.value)} className={inputCls} />
                            </Field>
                            <Field label="Email address" error={profileForm.errors.email}>
                                <input type="email" value={profileForm.data.email} onChange={e => profileForm.setData('email', e.target.value)} className={inputCls} required />
                            </Field>
                            <Field label="Phone number" error={profileForm.errors.phone}>
                                <input type="tel" value={profileForm.data.phone} onChange={e => profileForm.setData('phone', e.target.value)} className={inputCls} />
                            </Field>
                        </div>
                        <Field label="Timezone" hint="Used for scheduling and due date display">
                            <select value={profileForm.data.timezone} onChange={e => profileForm.setData('timezone', e.target.value)} className={inputCls}>
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
                            <Button type="submit" disabled={profileForm.processing} className="flex items-center gap-1.5 disabled:opacity-50" >
                                <Save size={16} /> {profileForm.processing ? 'Saving…' : 'Save changes'}
                            </Button>
                        </div>
                    </form>
                </div>

                {/* Password card */}
                <div className="bg-white rounded-xl border border-gray-100 p-6 shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
                    <div className="flex items-center gap-2 mb-5">
                        <div className="w-6 h-6 rounded-md bg-red-50 flex items-center justify-center">
                            <Lock size={16} className="text-red-500" />
                        </div>
                        <h2 className="text-[13px] font-semibold text-gray-900">Change Password</h2>
                    </div>
                    <form onSubmit={submitPassword} className="space-y-4">
                        <Field label="Current password" error={passwordForm.errors.current_password}>
                            <input type="password" value={passwordForm.data.current_password} onChange={e => passwordForm.setData('current_password', e.target.value)} className={inputCls} required />
                        </Field>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <Field label="New password" error={passwordForm.errors.password}>
                                <input type="password" value={passwordForm.data.password} onChange={e => passwordForm.setData('password', e.target.value)} className={inputCls} required />
                            </Field>
                            <Field label="Confirm new password">
                                <input type="password" value={passwordForm.data.password_confirmation} onChange={e => passwordForm.setData('password_confirmation', e.target.value)} className={inputCls} required />
                            </Field>
                        </div>
                        <div className="pt-1">
                            <Button type="submit" disabled={passwordForm.processing} className="flex items-center gap-1.5 disabled:opacity-50" variant="ghost" >
                                <Lock size={16} /> {passwordForm.processing ? 'Updating…' : 'Update password'}
                            </Button>
                        </div>
                    </form>
                </div>

                {/* Connected Accounts */}
                <div className="bg-white rounded-xl border border-gray-100 p-6 shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
                    <div className="flex items-center gap-2 mb-5">
                        <div className="w-6 h-6 rounded-md bg-blue-50 flex items-center justify-center">
                            <LinkIcon size={16} className="text-blue-500" />
                        </div>
                        <h2 className="text-[13px] font-semibold text-gray-900">Connected Accounts</h2>
                    </div>
                    <p className="text-[12px] text-gray-500 mb-4">Connect your external accounts to quickly log in or sync data.</p>
                    <div className="space-y-3">
                        {['google', 'zoho'].map(provider => {
                            const isConnected = connectedAccounts?.some(a => a.provider === provider);
                            return (
                                <div key={provider} className="flex items-center justify-between p-3 border border-gray-100 rounded-lg">
                                    <div className="flex items-center gap-3">
                                        <div className="w-8 h-8 rounded-full bg-gray-50 flex items-center justify-center border border-gray-100">
                                            <Globe size={16} className="text-gray-500" />
                                        </div>
                                        <div>
                                            <p className="text-[13px] font-semibold text-gray-900 capitalize">{provider}</p>
                                            <p className="text-[11px] text-gray-500">{isConnected ? 'Connected' : 'Not connected'}</p>
                                        </div>
                                    </div>
                                    <Button 
                                        className={cn("px-3 py-1.5 text-[12px] font-semibold rounded-md border transition-colors", 
                                            isConnected ? "text-gray-500 border-gray-200 hover:bg-gray-50" : "text-blue-600 border-blue-100 bg-blue-50 hover:bg-blue-100"
                                        )}
                                        onClick={() => !isConnected && toast.info(`${provider} integration is coming soon!`)}
                                    >
                                        {isConnected ? 'Disconnect' : 'Connect'}
                                    </Button>
                                </div>
                            );
                        })}
                    </div>
                </div>

                {/* API Tokens */}
                <div className="bg-white rounded-xl border border-gray-100 p-6 shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
                    <div className="flex items-center gap-2 mb-5">
                        <div className="w-6 h-6 rounded-md bg-violet-50 flex items-center justify-center">
                            <Key size={16} className="text-violet-500" />
                        </div>
                        <h2 className="text-[13px] font-semibold text-gray-900">API Tokens</h2>
                    </div>
                    <p className="text-[12px] text-gray-500 mb-4">Generate tokens for programmatic access to your account via the API.</p>
                    
                    {flash.new_token && (
                        <div className="mb-4 p-4 bg-emerald-50 border border-emerald-200 rounded-lg">
                            <p className="text-[12px] font-medium text-emerald-800 mb-2">Here is your new API token. Please copy it now, as it won't be shown again.</p>
                            <div className="flex items-center gap-2">
                                <code className="flex-1 block p-2 bg-white border border-emerald-200 rounded text-[12px] text-gray-800 font-mono overflow-x-auto">
                                    {flash.new_token}
                                </code>
                                <Button onClick={() => copyToken(flash.new_token!)} className="p-2 bg-white border border-emerald-200 rounded hover:bg-gray-50 text-emerald-700">
                                    {copied ? <Check size={16} /> : <Copy size={16} />}
                                </Button>
                            </div>
                        </div>
                    )}

                    <form onSubmit={createToken} className="flex gap-3 items-end mb-6">
                        <div className="flex-1">
                            <Field label="Token name" error={tokenForm.errors.token_name}>
                                <input type="text" placeholder="e.g. Zapier Integration" value={tokenForm.data.token_name} onChange={e => tokenForm.setData('token_name', e.target.value)} className={inputCls} required />
                            </Field>
                        </div>
                        <Button type="submit" disabled={tokenForm.processing} className="py-2.5 disabled:opacity-50 h-[42px]" variant="ghost" >
                            {tokenForm.processing ? 'Creating…' : 'Create Token'}
                        </Button>
                    </form>

                    {tokens && tokens.length > 0 && (
                        <div className="space-y-3">
                            {tokens.map(token => (
                                <div key={token.id} className="flex items-center justify-between p-3 border border-gray-100 rounded-lg">
                                    <div>
                                        <p className="text-[13px] font-semibold text-gray-900">{token.name}</p>
                                        <p className="text-[11px] text-gray-500">Created: {new Date(token.created_at).toLocaleDateString()} · Last used: {token.last_used_at || 'Never'}</p>
                                    </div>
                                    <Button onClick={() => revokeToken(token.id)} className="text-red-500 hover:text-red-700 p-2 rounded hover:bg-red-50" title="Revoke Token">
                                        <Trash2 size={16} />
                                    </Button>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* Active Sessions */}
                <div className="bg-white rounded-xl border border-gray-100 p-6 shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
                    <div className="flex items-center gap-2 mb-5">
                        <div className="w-6 h-6 rounded-md bg-amber-50 flex items-center justify-center">
                            <MonitorSmartphone size={16} className="text-amber-600" />
                        </div>
                        <h2 className="text-[13px] font-semibold text-gray-900">Active Sessions</h2>
                    </div>
                    <p className="text-[12px] text-gray-500 mb-4">Manage and log out your active sessions on other browsers and devices.</p>
                    
                    <div className="space-y-3">
                        {sessions && sessions.map(session => (
                            <div key={session.id} className="flex items-center justify-between p-3 border border-gray-100 rounded-lg">
                                <div className="flex items-center gap-3">
                                    <MonitorSmartphone size={16} className={session.is_current ? "text-amber-500" : "text-gray-400"} />
                                    <div>
                                        <p className="text-[13px] font-semibold text-gray-900 truncate max-w-[200px] sm:max-w-[300px]" title={session.user_agent}>
                                            {session.user_agent.substring(0, 40)}{session.user_agent.length > 40 ? '...' : ''}
                                        </p>
                                        <p className="text-[11px] text-gray-500">
                                            {session.ip_address} · {session.is_current ? 'Current session' : `Last active ${session.last_activity}`}
                                        </p>
                                    </div>
                                </div>
                                {!session.is_current && (
                                    <Button onClick={() => destroySession(session.id)} className="text-[12px] text-red-600 hover:text-red-800 font-semibold px-2 py-1 rounded hover:bg-red-50">
                                        Log out
                                    </Button>
                                )}
                            </div>
                        ))}
                    </div>
                </div>

                {/* Data Export & Danger Zone */}
                <div className="bg-white rounded-xl border border-red-100 p-6">
                    <div className="flex items-center gap-2 mb-5">
                        <div className="w-6 h-6 rounded-md bg-red-50 flex items-center justify-center">
                            <Trash2 size={16} className="text-red-500" />
                        </div>
                        <h2 className="text-[13px] font-semibold text-red-600">Danger Zone</h2>
                    </div>
                    
                    <div className="grid sm:grid-cols-2 gap-4">
                        <div className="p-4 border border-gray-200 rounded-lg bg-gray-50">
                            <h3 className="text-[13px] font-semibold text-gray-900 mb-1">Export Data</h3>
                            <p className="text-[11px] text-gray-500 mb-4">Download a JSON copy of all your personal data for GDPR compliance.</p>
                            <Button onClick={exportData} className="flex items-center gap-1.5" variant="outline" size="sm" >
                                <Download size={12} /> Download Data
                            </Button>
                        </div>
                        
                        <div className="p-4 border border-red-200 rounded-lg bg-red-50">
                            <h3 className="text-[13px] font-semibold text-red-700 mb-1">Delete Account</h3>
                            <p className="text-[11px] text-red-500 mb-4">Permanently delete your account and remove all personal information.</p>
                            <Button onClick={deleteAccount} disabled={deleteAccountForm.processing} className="disabled:opacity-50" variant="destructive" size="sm" >
                                Delete Account
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

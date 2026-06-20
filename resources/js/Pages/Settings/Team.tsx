import React, { useState } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { cn } from '@/lib/utils';
import { UserPlus, Users } from 'lucide-react';

interface Member {
    id: string;
    name: string;
    email: string;
    roles: Array<{ id: string; name: string; slug: string }>;
    is_active: boolean;
}

interface Role {
    id: string;
    name: string;
    slug: string;
}

interface Props {
    members: Member[];
    roles: Role[];
}

export default function TeamSettings({ members, roles }: Props) {
    const [showInvite, setShowInvite] = useState(false);

    const inviteForm = useForm({
        name:    '',
        email:   '',
        role_id: roles[0]?.id ?? '',
    });

    function submitInvite(e: React.FormEvent) {
        e.preventDefault();
        inviteForm.post('/settings/team/invite', {
            onSuccess: () => {
                inviteForm.reset();
                setShowInvite(false);
            },
        });
    }

    return (
        <AppLayout title="Team">
            <Head title="Team" />

            <div className="flex items-center justify-between mb-6">
                <p className="text-sm text-gray-500">{members.length} team member{members.length !== 1 ? 's' : ''}</p>
                <button
                    onClick={() => setShowInvite(!showInvite)}
                    className="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700"
                >
                    <UserPlus size={15} /> Invite Member
                </button>
            </div>

            {showInvite && (
                <div className="bg-white rounded-xl border border-gray-200 p-5 mb-5">
                    <h3 className="text-sm font-semibold text-gray-900 mb-4">Invite New Member</h3>
                    <form onSubmit={submitInvite} className="grid grid-cols-1 md:grid-cols-4 gap-3">
                        <div>
                            <label className="block text-xs font-medium text-gray-600 mb-1">Name</label>
                            <input
                                type="text"
                                value={inviteForm.data.name}
                                onChange={e => inviteForm.setData('name', e.target.value)}
                                className="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                placeholder="Jane Smith"
                                required
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-600 mb-1">Email</label>
                            <input
                                type="email"
                                value={inviteForm.data.email}
                                onChange={e => inviteForm.setData('email', e.target.value)}
                                className="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                placeholder="jane@company.com"
                                required
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-600 mb-1">Role</label>
                            <select
                                value={inviteForm.data.role_id}
                                onChange={e => inviteForm.setData('role_id', e.target.value)}
                                className="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                                {roles.map(r => (
                                    <option key={r.id} value={r.id}>{r.name}</option>
                                ))}
                            </select>
                        </div>
                        <div className="flex items-end">
                            <button
                                type="submit"
                                disabled={inviteForm.processing}
                                className="w-full py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700 disabled:opacity-50"
                            >
                                Send Invite
                            </button>
                        </div>
                    </form>
                    {Object.values(inviteForm.errors).map((err, i) => (
                        <p key={i} className="text-red-500 text-xs mt-2">{err}</p>
                    ))}
                </div>
            )}

            <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                {members.length === 0 ? (
                    <div className="p-16 text-center">
                        <Users size={32} className="text-gray-300 mx-auto mb-3" />
                        <p className="text-gray-500 text-sm">No team members yet.</p>
                    </div>
                ) : (
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-gray-100 bg-gray-50">
                                <th className="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase">Name</th>
                                <th className="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase">Email</th>
                                <th className="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase">Roles</th>
                                <th className="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {members.map(member => (
                                <tr key={member.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-2">
                                            <div className="w-7 h-7 rounded-full bg-indigo-100 text-indigo-700 text-xs flex items-center justify-center font-semibold">
                                                {member.name[0]}
                                            </div>
                                            <span className="font-medium text-gray-900">{member.name}</span>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 text-gray-500">{member.email}</td>
                                    <td className="px-4 py-3">
                                        <select 
                                            value={member.roles[0]?.id ?? ''}
                                            onChange={(e) => {
                                                router.patch(`/settings/team/${member.id}/role`, { role_id: e.target.value }, { preserveScroll: true });
                                            }}
                                            className="px-2 py-1 text-xs border border-gray-200 rounded focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white"
                                        >
                                            <option value="" disabled>Select a role...</option>
                                            {roles.map(r => (
                                                <option key={r.id} value={r.id}>{r.name}</option>
                                            ))}
                                        </select>
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={cn('px-2 py-0.5 rounded-full text-xs font-medium', member.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700')}>
                                            {member.is_active ? 'Active' : 'Inactive'}
                                        </span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        </AppLayout>
    );
}

import React, { useState } from 'react';
import { Button } from '@/Components/ui/Button';
import { Breadcrumbs } from '@/Components/Shared/Breadcrumbs';
import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import { Flag, Plus, Trash2, Globe, Building } from 'lucide-react';
import Modal from '@/Components/ui/Modal';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/Components/ui/Table";

interface Organization {
    id: number | string;
    name: string;
}

interface FeatureFlag {
    id: number;
    feature: string;
    organization_id: number | string | null;
    is_enabled: boolean;
    organization: Organization | null;
}

interface Props {
    flags: FeatureFlag[];
    organizations: Organization[];
}

export default function FeatureFlags({ flags, organizations }: Props) {
    const [isCreating, setIsCreating] = useState(false);
    const [form, setForm] = useState({
        feature: '',
        organization_id: '',
        is_enabled: false,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        router.post('/admin/feature-flags', form, {
            onSuccess: () => {
                setIsCreating(false);
                setForm({ feature: '', organization_id: '', is_enabled: false });
            }
        });
    };

    const toggleFlag = (flag: FeatureFlag) => {
        router.post(`/admin/feature-flags/${flag.id}/toggle`);
    };

    const deleteFlag = (flag: FeatureFlag) => {
        if (confirm('Are you sure you want to delete this feature flag?')) {
            router.delete(`/admin/feature-flags/${flag.id}`);
        }
    };

    return (
        <AppLayout title="Feature Flags">
            <Head title="Feature Flags | Admin" />
            <div className="mb-6">
                <Breadcrumbs items={[
                    { label: 'Admin', href: '/admin' },
                    { label: 'Feature Flags | Admin' }
                ]} />
            </div>

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                    <div className="flex flex-col md:flex-row justify-between items-start md:items-center bg-white/5 backdrop-blur-xl border border-white/10 p-6 rounded-2xl shadow-xl gap-4">
                        <div>
                            <h2 className="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-blue-400 to-indigo-500 flex items-center gap-2">
                                <Flag className="w-6 h-6 text-indigo-500" />
                                Feature Flags
                            </h2>
                            <p className="mt-1 text-sm text-gray-400">
                                Manage application features globally or per organization.
                            </p>
                        </div>
                        <Button
                            onClick={() => setIsCreating(true)}
                            className="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-2 transition-colors"
                        >
                            <Plus className="w-4 h-4" />
                            Create Flag
                        </Button>
                    </div>

                    <div className="bg-white/5 backdrop-blur-xl border border-white/10 rounded-2xl shadow-xl overflow-hidden">
                        <div className="overflow-x-auto">
                            <Table className="w-full text-left border-collapse">
                                <TableHeader>
                                    <TableRow className="bg-white/5 border-b border-white/10 text-gray-300 text-sm">
                                        <TableHead className="p-4 font-medium">Feature</TableHead>
                                        <TableHead className="p-4 font-medium">Scope</TableHead>
                                        <TableHead className="p-4 font-medium">Status</TableHead>
                                        <TableHead className="p-4 font-medium text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody className="text-sm divide-y divide-white/5">
                                    {flags.map((flag) => (
                                        <TableRow key={flag.id} className="hover:bg-white/5 transition-colors">
                                            <TableCell className="p-4 whitespace-nowrap text-gray-200 font-medium">
                                                {flag.feature}
                                            </TableCell>
                                            <TableCell className="p-4 whitespace-nowrap text-gray-400">
                                                {flag.organization ? (
                                                    <div className="flex items-center gap-2 text-blue-400">
                                                        <Building className="w-4 h-4" />
                                                        {flag.organization.name}
                                                    </div>
                                                ) : (
                                                    <div className="flex items-center gap-2 text-emerald-400">
                                                        <Globe className="w-4 h-4" />
                                                        Global
                                                    </div>
                                                )}
                                            </TableCell>
                                            <TableCell className="p-4 whitespace-nowrap">
                                                <Button
                                                    onClick={() => toggleFlag(flag)}
                                                    className={`relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2 focus:ring-offset-[#0f172a] ${
                                                        flag.is_enabled ? 'bg-indigo-600' : 'bg-gray-700'
                                                    }`}
                                                >
                                                    <span
                                                        className={`pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${
                                                            flag.is_enabled ? 'translate-x-5' : 'translate-x-0'
                                                        }`}
                                                    />
                                                </Button>
                                            </TableCell>
                                            <TableCell className="p-4 whitespace-nowrap text-right">
                                                <Button
                                                    onClick={() => deleteFlag(flag)}
                                                    className="p-1.5 text-red-400 hover:text-red-300 bg-red-400/10 hover:bg-red-400/20 rounded-lg transition-colors inline-flex items-center"
                                                    title="Delete"
                                                >
                                                    <Trash2 className="w-4 h-4" />
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                    {flags.length === 0 && (
                                        <TableRow>
                                            <TableCell colSpan={4} className="p-8 text-center text-gray-400">
                                                No feature flags defined.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                    </div>
                </div>
            </div>

            <Modal show={isCreating} onClose={() => setIsCreating(false)} maxWidth="md">
                <form onSubmit={submit} className="p-6 bg-[#0f172a] text-gray-200">
                    <h2 className="text-lg font-bold mb-4 flex items-center gap-2">
                        <Flag className="w-5 h-5 text-indigo-400" />
                        New Feature Flag
                    </h2>
                    
                    <div className="space-y-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-300 mb-1">
                                Feature Name (Key)
                            </label>
                            <input
                                type="text"
                                required
                                value={form.feature}
                                onChange={(e) => setForm({ ...form, feature: e.target.value })}
                                placeholder="e.g. beta_dashboard"
                                className="w-full bg-white/5 border-white/10 rounded-lg text-sm text-gray-200 focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                        
                        <div>
                            <label className="block text-sm font-medium text-gray-300 mb-1">
                                Scope (Organization)
                            </label>
                            <select
                                value={form.organization_id}
                                onChange={(e) => setForm({ ...form, organization_id: e.target.value })}
                                className="w-full bg-white/5 border-white/10 rounded-lg text-sm text-gray-200 focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">Global (All Organizations)</option>
                                {organizations.map((org) => (
                                    <option key={org.id} value={org.id}>
                                        {org.name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="flex items-center gap-2 pt-2">
                            <input
                                type="checkbox"
                                id="is_enabled"
                                checked={form.is_enabled}
                                onChange={(e) => setForm({ ...form, is_enabled: e.target.checked })}
                                className="rounded border-white/10 bg-white/5 text-indigo-600 focus:ring-indigo-500 focus:ring-offset-[#0f172a]"
                            />
                            <label htmlFor="is_enabled" className="text-sm text-gray-300">
                                Enabled by default
                            </label>
                        </div>
                    </div>

                    <div className="mt-6 flex justify-end gap-3">
                        <Button
                            type="button"
                            onClick={() => setIsCreating(false)}
                            className="px-4 py-2 text-sm text-gray-400 hover:text-white"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            
                        >
                            Create
                        </Button>
                    </div>
                </form>
            </Modal>
        </AppLayout>
    );
}

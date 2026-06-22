import React, { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import { Plug, CheckCircle2, AlertTriangle, Settings, Plus } from 'lucide-react';
import Modal from '@/Components/ui/Modal';
import { Input, Label } from '@/Components/ui/Input';

interface Provider {
    id: string;
    name: string;
    slug: string;
    description: string | null;
    is_active: boolean;
    adapter_class: string | null;
}

interface Props {
    providers: Provider[];
}

export default function McpProviders({ providers }: Props) {
    const [isAddModalOpen, setIsAddModalOpen] = useState(false);
    const [isEditModalOpen, setIsEditModalOpen] = useState(false);
    const [currentProvider, setCurrentProvider] = useState<Provider | null>(null);

    const [form, setForm] = useState({
        name: '',
        slug: '',
        description: '',
        adapter_class: '',
        is_active: true,
    });

    const openAddModal = () => {
        setForm({ name: '', slug: '', description: '', adapter_class: '', is_active: true });
        setIsAddModalOpen(true);
    };

    const openEditModal = (provider: Provider) => {
        setCurrentProvider(provider);
        setForm({
            name: provider.name,
            slug: provider.slug,
            description: provider.description || '',
            adapter_class: provider.adapter_class || '',
            is_active: provider.is_active,
        });
        setIsEditModalOpen(true);
    };

    const handleAddSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.post('/admin/mcp/providers', form, {
            onSuccess: () => setIsAddModalOpen(false)
        });
    };

    const handleEditSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (currentProvider) {
            router.put(`/admin/mcp/providers/${currentProvider.id}`, form, {
                onSuccess: () => setIsEditModalOpen(false)
            });
        }
    };

    return (
        <AppLayout title="Provider Management">
            <Head title="Provider Management | Admin" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                    {/* Header Section */}
                    <div className="flex flex-col md:flex-row justify-between items-start md:items-center bg-white/5 backdrop-blur-xl border border-white/10 p-6 rounded-2xl shadow-xl gap-4">
                        <div>
                            <h2 className="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-blue-400 to-indigo-500 flex items-center gap-2">
                                <Plug className="w-6 h-6 text-blue-500" />
                                MCP Providers
                            </h2>
                            <p className="mt-1 text-sm text-gray-400">
                                Manage built-in and custom provider definitions.
                            </p>
                        </div>
                        <button
                            onClick={openAddModal}
                            className="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2"
                        >
                            <Plus className="w-4 h-4" />
                            Add Provider
                        </button>
                    </div>

                    <div className="bg-white/5 backdrop-blur-xl border border-white/10 rounded-2xl shadow-xl overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left border-collapse">
                                <thead>
                                    <tr className="bg-white/5 border-b border-white/10 text-gray-300 text-sm">
                                        <th className="p-4 font-medium">Provider Name</th>
                                        <th className="p-4 font-medium">Slug</th>
                                        <th className="p-4 font-medium">Adapter Class</th>
                                        <th className="p-4 font-medium">Status</th>
                                        <th className="p-4 font-medium text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="text-sm divide-y divide-white/5">
                                    {providers.map((provider) => (
                                        <tr key={provider.id} className="hover:bg-white/5 transition-colors">
                                            <td className="p-4">
                                                <div className="font-medium text-gray-200">{provider.name}</div>
                                                <div className="text-xs text-gray-400 mt-1">{provider.description}</div>
                                            </td>
                                            <td className="p-4 text-gray-400 font-mono text-xs">{provider.slug}</td>
                                            <td className="p-4 text-gray-400 font-mono text-xs truncate max-w-[200px]" title={provider.adapter_class || ''}>
                                                {provider.adapter_class || 'N/A (Custom)'}
                                            </td>
                                            <td className="p-4">
                                                {provider.is_active ? (
                                                    <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                                        <CheckCircle2 className="w-3.5 h-3.5" />
                                                        Active
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                                        <AlertTriangle className="w-3.5 h-3.5" />
                                                        Disabled
                                                    </span>
                                                )}
                                            </td>
                                            <td className="p-4 text-right space-x-2">
                                                <button
                                                    onClick={() => openEditModal(provider)}
                                                    className="p-1.5 text-gray-400 hover:text-white bg-white/5 hover:bg-white/10 rounded-lg transition-colors inline-flex items-center gap-2"
                                                >
                                                    <Settings className="w-4 h-4" />
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                    {providers.length === 0 && (
                                        <tr>
                                            <td colSpan={5} className="p-8 text-center text-gray-400">
                                                No providers found.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <Modal show={isAddModalOpen || isEditModalOpen} onClose={() => { setIsAddModalOpen(false); setIsEditModalOpen(false); }} maxWidth="md">
                <div className="p-6 bg-[#0f172a] text-gray-200">
                    <h2 className="text-lg font-bold mb-4">{isAddModalOpen ? 'Add Provider' : 'Edit Provider'}</h2>
                    <form onSubmit={isAddModalOpen ? handleAddSubmit : handleEditSubmit} className="space-y-4">
                        <div>
                            <Label className="text-gray-300">Name</Label>
                            <Input
                                value={form.name}
                                onChange={(e) => setForm({ ...form, name: e.target.value })}
                                className="w-full mt-1 bg-white/5 border-white/10 text-white"
                                required
                            />
                        </div>
                        <div>
                            <Label className="text-gray-300">Slug</Label>
                            <Input
                                value={form.slug}
                                onChange={(e) => setForm({ ...form, slug: e.target.value })}
                                className="w-full mt-1 bg-white/5 border-white/10 text-white"
                                required
                                disabled={isEditModalOpen}
                            />
                        </div>
                        <div>
                            <Label className="text-gray-300">Description</Label>
                            <textarea
                                value={form.description}
                                onChange={(e) => setForm({ ...form, description: e.target.value })}
                                className="w-full mt-1 bg-white/5 border-white/10 text-white rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                rows={3}
                            />
                        </div>
                        <div>
                            <Label className="text-gray-300">Adapter Class (Optional)</Label>
                            <Input
                                value={form.adapter_class}
                                onChange={(e) => setForm({ ...form, adapter_class: e.target.value })}
                                className="w-full mt-1 bg-white/5 border-white/10 text-white"
                                placeholder="App\Modules\MCP\Adapters\..."
                            />
                        </div>
                        <div className="flex items-center gap-2 mt-4">
                            <input
                                type="checkbox"
                                id="is_active"
                                checked={form.is_active}
                                onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
                                className="rounded border-white/10 bg-white/5 text-indigo-600 shadow-sm focus:ring-indigo-500"
                            />
                            <label htmlFor="is_active" className="text-sm text-gray-300">Active</label>
                        </div>
                        <div className="flex justify-end gap-3 mt-6">
                            <button
                                type="button"
                                onClick={() => { setIsAddModalOpen(false); setIsEditModalOpen(false); }}
                                className="px-4 py-2 text-sm text-gray-400 hover:text-white transition-colors"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                className="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium transition-colors"
                            >
                                {isAddModalOpen ? 'Add Provider' : 'Save Changes'}
                            </button>
                        </div>
                    </form>
                </div>
            </Modal>
        </AppLayout>
    );
}

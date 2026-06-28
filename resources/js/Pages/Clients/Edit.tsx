import React from 'react';
import { Button } from '@/Components/ui/Button';
import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useUnsavedChanges } from '@/hooks/useUnsavedChanges';
import { cn } from '@/lib/utils';
import type { Client } from '@/types';
import { ArrowLeft, Save } from 'lucide-react';

interface Props {
    client: Client;
}

const inputCls = 'w-full px-3.5 py-2.5 border border-gray-200 rounded-lg text-[13px] bg-gray-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all placeholder-gray-400';
const labelCls = 'block text-[12px] font-semibold text-gray-700 mb-1.5';

export default function ClientEdit({ client }: Props) {
    const form = useForm({
        name:     client.name,
        email:    client.email    ?? '',
        phone:    client.phone    ?? '',
        website:  client.website  ?? '',
        company:  client.company  ?? '',
        industry: client.industry ?? '',
        tier:     client.tier,
        status:   client.status,
        notes:    client.notes    ?? '',
    });

    useUnsavedChanges(form.isDirty);

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.patch(`/clients/${client.id}`);
    }

    return (
        <AppLayout title="Edit Client">
            <Head title={`Edit · ${client.name}`} />

            <div className="max-w-2xl mx-auto">
                <div className="flex items-center gap-3 mb-6">
                    <Link
                        href={`/clients/${client.id}`}
                        className="p-1.5 rounded-lg hover:bg-gray-100 text-gray-700 transition-colors"
                    >
                        <ArrowLeft size={20} />
                    </Link>
                    <div>
                        <h1 className="text-[15px] font-bold text-gray-900">Edit Client</h1>
                        <p className="text-[12px] text-gray-400 mt-0.5">{client.name}</p>
                    </div>
                </div>

                <div className="bg-white rounded-xl border border-gray-100 p-6 shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
                    <form onSubmit={submit} className="space-y-5">

                        {/* Name */}
                        <div>
                            <label className={labelCls}>Client Name <span className="text-red-400">*</span></label>
                            <input
                                type="text"
                                value={form.data.name}
                                onChange={e => form.setData('name', e.target.value)}
                                className={cn(inputCls, form.errors.name && 'border-red-300 bg-red-50')}
                                required
                                autoFocus
                            />
                            {form.errors.name && <p className="mt-1.5 text-[11px] text-red-500 font-medium">{form.errors.name}</p>}
                        </div>

                        <div>
                            <label className={labelCls}>Company <span className="text-red-400">*</span></label>
                            <input
                                type="text"
                                value={form.data.company}
                                onChange={e => form.setData('company', e.target.value)}
                                className={cn(inputCls, form.errors.company && 'border-red-300 bg-red-50')}
                                required
                            />
                            {form.errors.company && <p className="mt-1.5 text-[11px] text-red-500 font-medium">{form.errors.company}</p>}
                        </div>

                        {/* Email + Phone */}
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className={labelCls}>Email <span className="text-red-400">*</span></label>
                                <input
                                    type="email"
                                    value={form.data.email}
                                    onChange={e => form.setData('email', e.target.value)}
                                    className={cn(inputCls, form.errors.email && 'border-red-300 bg-red-50')}
                                    placeholder="contact@acme.com"
                                    required
                                />
                                {form.errors.email && <p className="mt-1.5 text-[11px] text-red-500 font-medium">{form.errors.email}</p>}
                            </div>
                            <div>
                                <label className={labelCls}>Phone</label>
                                <input
                                    type="text"
                                    value={form.data.phone}
                                    onChange={e => form.setData('phone', e.target.value)}
                                    className={inputCls}
                                    placeholder="+91 98765 43210"
                                />
                            </div>
                        </div>

                        {/* Website + Industry */}
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className={labelCls}>Website</label>
                                <input
                                    type="url"
                                    value={form.data.website}
                                    onChange={e => form.setData('website', e.target.value)}
                                    className={inputCls}
                                    placeholder="https://acme.com"
                                />
                            </div>
                            <div>
                                <label className={labelCls}>Industry</label>
                                <input
                                    type="text"
                                    value={form.data.industry}
                                    onChange={e => form.setData('industry', e.target.value)}
                                    className={inputCls}
                                    placeholder="E-commerce, SaaS, Healthcare…"
                                />
                            </div>
                        </div>

                        {/* Tier + Status */}
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className={labelCls}>Tier</label>
                                <select value={form.data.tier} onChange={e => form.setData('tier', e.target.value as any)} className={inputCls}>
                                    <option value="basic">Basic</option>
                                    <option value="standard">Standard</option>
                                    <option value="premium">Premium</option>
                                    <option value="enterprise">Enterprise</option>
                                </select>
                            </div>
                            <div>
                                <label className={labelCls}>Status</label>
                                <select value={form.data.status} onChange={e => form.setData('status', e.target.value as any)} className={inputCls}>
                                    <option value="active">Active</option>
                                    <option value="prospect">Prospect</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="churned">Churned</option>
                                </select>
                            </div>
                        </div>

                        {/* Notes */}
                        <div>
                            <label className={labelCls}>Notes</label>
                            <textarea
                                value={form.data.notes}
                                onChange={e => form.setData('notes', e.target.value)}
                                rows={3}
                                className={cn(inputCls, 'resize-none')}
                                placeholder="Any additional context…"
                            />
                        </div>

                        <div className="flex items-center gap-3 pt-2 border-t border-gray-100">
                            <Button
                                type="submit"
                                disabled={form.processing}
                                className="flex items-center gap-1.5 px-5 py-2.5 disabled:opacity-50" 
                            >
                                <Save size={16} />
                                {form.processing ? 'Saving…' : 'Save Changes'}
                            </Button>
                            <Link
                                href={`/clients/${client.id}`}
                                className="px-4 py-2.5 text-[13px] text-gray-500 hover:text-gray-700 rounded-lg hover:bg-gray-100 transition-colors"
                            >
                                Cancel
                            </Link>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}

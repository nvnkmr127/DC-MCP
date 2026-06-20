import React from 'react';
import { Head, useForm, router } from '@inertiajs/react';import AppLayout from '@/Layouts/AppLayout';

interface OrgData {
    id: string;
    name: string;
    slug: string;
    timezone: string;
    currency: string;
}

interface Props {
    organization: OrgData;
}

export default function OrganizationSettings({ organization }: Props) {
    const form = useForm({
        name:     organization.name,
        timezone: organization.timezone ?? 'Asia/Kolkata',
        currency: organization.currency ?? 'INR',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.patch('/settings/organization');
    }

    return (
        <AppLayout title="Organization Settings">
            <Head title="Organization Settings" />

            <div className="max-w-xl">
                <div className="bg-white rounded-xl border border-gray-200 p-6">
                    <h2 className="text-sm font-semibold text-gray-900 mb-1">Organization Details</h2>
                    <p className="text-xs text-gray-500 mb-4">Slug: <span className="font-mono bg-gray-100 px-1 rounded">{organization.slug}</span></p>

                    <form onSubmit={submit} className="space-y-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Organization Name</label>
                            <input
                                type="text"
                                value={form.data.name}
                                onChange={e => form.setData('name', e.target.value)}
                                className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                required
                            />
                            {form.errors.name && <p className="text-red-500 text-xs mt-1">{form.errors.name}</p>}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Timezone</label>
                            <select
                                value={form.data.timezone}
                                onChange={e => form.setData('timezone', e.target.value)}
                                className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                                <option value="Asia/Kolkata">Asia/Kolkata (IST)</option>
                                <option value="UTC">UTC</option>
                                <option value="America/New_York">America/New_York (ET)</option>
                                <option value="Europe/London">Europe/London (GMT)</option>
                                <option value="Asia/Singapore">Asia/Singapore (SGT)</option>
                                <option value="Asia/Dubai">Asia/Dubai (GST)</option>
                            </select>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Currency</label>
                            <select
                                value={form.data.currency}
                                onChange={e => form.setData('currency', e.target.value)}
                                className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                                <option value="INR">INR — Indian Rupee</option>
                                <option value="USD">USD — US Dollar</option>
                                <option value="EUR">EUR — Euro</option>
                                <option value="GBP">GBP — British Pound</option>
                                <option value="AED">AED — UAE Dirham</option>
                                <option value="SGD">SGD — Singapore Dollar</option>
                            </select>
                        </div>
                        <button
                            type="submit"
                            disabled={form.processing}
                            className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50"
                        >
                            Save Changes
                        </button>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}

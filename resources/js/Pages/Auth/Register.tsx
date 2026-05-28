import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AuthLayout from '@/Layouts/AuthLayout';

export default function Register() {
    const form = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        organization_name: '',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.post('/register');
    }

    return (
        <AuthLayout title="Create your account">
            <Head title="Register" />
            <form onSubmit={submit} className="space-y-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Organization Name</label>
                    <input
                        type="text"
                        value={form.data.organization_name}
                        onChange={e => form.setData('organization_name', e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        placeholder="Acme Digital"
                        required
                        autoFocus
                    />
                    {form.errors.organization_name && <p className="text-red-500 text-xs mt-1">{form.errors.organization_name}</p>}
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Your Name</label>
                    <input
                        type="text"
                        value={form.data.name}
                        onChange={e => form.setData('name', e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        placeholder="Jane Smith"
                        required
                    />
                    {form.errors.name && <p className="text-red-500 text-xs mt-1">{form.errors.name}</p>}
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input
                        type="email"
                        value={form.data.email}
                        onChange={e => form.setData('email', e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        placeholder="jane@acme.com"
                        required
                    />
                    {form.errors.email && <p className="text-red-500 text-xs mt-1">{form.errors.email}</p>}
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input
                        type="password"
                        value={form.data.password}
                        onChange={e => form.setData('password', e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        placeholder="Min 8 characters"
                        required
                    />
                    {form.errors.password && <p className="text-red-500 text-xs mt-1">{form.errors.password}</p>}
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                    <input
                        type="password"
                        value={form.data.password_confirmation}
                        onChange={e => form.setData('password_confirmation', e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        required
                    />
                </div>
                <button
                    type="submit"
                    disabled={form.processing}
                    className="w-full py-2.5 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                >
                    {form.processing ? 'Creating account…' : 'Create account'}
                </button>
                <p className="text-center text-sm text-gray-500">
                    Already have an account?{' '}
                    <Link href="/login" className="text-indigo-600 hover:underline font-medium">Sign in</Link>
                </p>
            </form>
        </AuthLayout>
    );
}

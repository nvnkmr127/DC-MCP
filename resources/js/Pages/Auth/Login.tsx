import React from 'react';
import { Button } from '@/Components/ui/Button';
import { useForm, Head, Link, router } from '@inertiajs/react';
import AuthLayout from '@/Layouts/AuthLayout';
import { cn } from '@/lib/utils';
import { Zap, Eye, EyeOff } from 'lucide-react';

interface DemoAccount {
    role: string;
    email: string;
    label: string;
    color: string;
}

interface Props {
    demo_accounts?: DemoAccount[];
}

export default function Login({ demo_accounts = [] }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        email:    '',
        password: '',
        remember: false,
    });
    const [showPw, setShowPw] = React.useState(false);

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post('/login');
    }

    function quickLogin(role: string) {
        router.get(`/dev/login/${role}`);
    }

    return (
        <AuthLayout>
            <Head title="Sign In" />

            <div className="mb-6">
                <h2 className="text-[20px] font-bold text-gray-900 leading-tight">Welcome back</h2>
                <p className="text-[13px] text-gray-400 mt-1">Sign in to your workspace</p>
            </div>

            <form onSubmit={submit} className="space-y-4">
                {/* Email */}
                <div>
                    <label className="block text-[12px] font-semibold text-gray-700 mb-1.5">
                        Email address
                    </label>
                    <input
                        type="email"
                        value={data.email}
                        onChange={e => setData('email', e.target.value)}
                        className={cn(
                            'w-full px-3.5 py-2.5 border rounded-lg text-[13px] bg-gray-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all placeholder-gray-400',
                            errors.email ? 'border-red-300 bg-red-50' : 'border-gray-200',
                        )}
                        placeholder="you@company.com"
                        autoComplete="email"
                        autoFocus
                        required
                    />
                    {errors.email && <p className="mt-1.5 text-[11px] text-red-500 font-medium">{errors.email}</p>}
                </div>

                {/* Password */}
                <div>
                    <div className="flex items-center justify-between mb-1.5">
                        <label className="text-[12px] font-semibold text-gray-700">Password</label>
                        <a href="#" className="text-[11px] text-indigo-600 hover:text-indigo-700 font-medium">Forgot password?</a>
                    </div>
                    <div className="relative">
                        <input
                            type={showPw ? 'text' : 'password'}
                            value={data.password}
                            onChange={e => setData('password', e.target.value)}
                            className={cn(
                                'w-full pl-3.5 pr-10 py-2.5 border rounded-lg text-[13px] bg-gray-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all',
                                errors.password ? 'border-red-300 bg-red-50' : 'border-gray-200',
                            )}
                            autoComplete="current-password"
                            required
                        />
                        <Button
                            type="button"
                            onClick={() => setShowPw(!showPw)}
                            className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                        >
                            {showPw ? <EyeOff size={15} /> : <Eye size={15} />}
                        </Button>
                    </div>
                    {errors.password && <p className="mt-1.5 text-[11px] text-red-500 font-medium">{errors.password}</p>}
                </div>

                {/* Remember */}
                <div className="flex items-center gap-2">
                    <input
                        id="remember"
                        type="checkbox"
                        checked={data.remember}
                        onChange={e => setData('remember', e.target.checked)}
                        className="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 cursor-pointer"
                    />
                    <label htmlFor="remember" className="text-[12px] text-gray-600 cursor-pointer select-none font-medium">
                        Keep me signed in for 30 days
                    </label>
                </div>

                {/* Submit */}
                <Button
                    type="submit"
                    disabled={processing}
                    className="w-full py-2.5 font-bold disabled:opacity-60 disabled:cursor-not-allowed" 
                >
                    {processing ? (
                        <span className="flex items-center justify-center gap-2">
                            <span className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                            Signing in…
                        </span>
                    ) : 'Sign in'}
                </Button>
            </form>

            <p className="mt-5 text-center text-[12px] text-gray-400">
                Don't have an account?{' '}
                <Link href="/register" className="text-indigo-600 hover:text-indigo-700 font-semibold">
                    Request access
                </Link>
            </p>

            {/* Dev quick login */}
            {demo_accounts.length > 0 && (
                <div className="mt-6 pt-5 border-t border-dashed border-gray-200">
                    <div className="flex items-center gap-1.5 mb-3">
                        <Zap size={12} className="text-yellow-500 shrink-0" />
                        <p className="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Dev Quick Login</p>
                    </div>
                    <div className="grid grid-cols-3 gap-2">
                        {demo_accounts.map(acc => (
                            <Button
                                key={acc.role}
                                type="button"
                                onClick={() => quickLogin(acc.role)}
                                className={cn(
                                    'px-2 py-2 text-white text-[11px] font-bold rounded-lg hover:opacity-90 active:scale-95 transition-all',
                                    acc.color,
                                )}
                            >
                                {acc.label}
                            </Button>
                        ))}
                    </div>
                    <p className="text-[11px] text-gray-400 mt-2.5 text-center">
                        Password: <span className="font-mono bg-gray-100 px-1.5 py-0.5 rounded text-gray-600 text-[10px]">Demo@1234</span>
                    </p>
                </div>
            )}
        </AuthLayout>
    );
}

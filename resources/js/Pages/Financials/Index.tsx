import React, { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import {
    TrendingUp, TrendingDown, DollarSign, ChevronLeft, ChevronRight,
    Plus, X, AlertCircle, CheckCircle2, Minus,
} from 'lucide-react';
import { cn } from '@/lib/utils';

interface PnlData {
    month_year: string;
    revenue: { mrr: number; invoices: number; total: number };
    costs: { payroll: number; expenses: number; vendors: number; total: number };
    gross_profit: number; net_profit: number; profit_margin: number;
}
interface ClientProfit {
    id: string; name: string; revenue: number; estimated_cost: number;
    profit: number; margin: number; hours_logged: number; health_status: string | null;
}
interface TrendPoint { month: string; label: string; revenue: number; costs: number; net_profit: number; }
interface CashForecast { month: string; label: string; projected_in: number; projected_out: number; net: number; }
interface Expense { id: string; title: string; category: string; amount: number; expense_date: string; vendor: string | null; is_recurring: boolean; }
interface Vendor { id: string; name: string; type: string; monthly_cost: number; status: string; }
interface Props {
    pnl: PnlData; clientProfit: ClientProfit[]; cashForecast: CashForecast[];
    trend: TrendPoint[]; expenses: Expense[]; vendors: Vendor[]; monthYear: string;
}

const fmt = (n: number) => '₹' + new Intl.NumberFormat('en-IN', { maximumFractionDigits: 0 }).format(n);

const CATEGORY_COLORS: Record<string, string> = {
    tools: 'bg-blue-100 text-blue-700', freelancer: 'bg-violet-100 text-violet-700',
    office: 'bg-teal-100 text-teal-700', ads: 'bg-orange-100 text-orange-700',
    travel: 'bg-sky-100 text-sky-700', hardware: 'bg-indigo-100 text-indigo-700',
    other: 'bg-gray-100 text-gray-600',
};

const HEALTH_DOT: Record<string, string> = {
    green: 'bg-emerald-400', yellow: 'bg-amber-400', red: 'bg-rose-400',
};

function MonthNav({ monthYear, onChange }: { monthYear: string; onChange: (m: string) => void }) {
    const navigate = (offset: number) => {
        const [y, m] = monthYear.split('-').map(Number);
        const d = new Date(y, m - 1 + offset, 1);
        onChange(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`);
    };
    const label = new Date(monthYear + '-01').toLocaleDateString('en-IN', { month: 'long', year: 'numeric' });
    return (
        <div className="flex items-center gap-2">
            <button onClick={() => navigate(-1)} className="p-1.5 rounded-lg border border-gray-200 hover:bg-gray-50"><ChevronLeft className="w-4 h-4 text-gray-600" /></button>
            <span className="text-sm font-semibold text-gray-700 min-w-32 text-center">{label}</span>
            <button onClick={() => navigate(1)} className="p-1.5 rounded-lg border border-gray-200 hover:bg-gray-50"><ChevronRight className="w-4 h-4 text-gray-600" /></button>
        </div>
    );
}

export default function FinancialsIndex({ pnl, clientProfit, cashForecast, trend, expenses, vendors, monthYear }: Props) {
    const [showAddExpense, setShowAddExpense] = useState(false);
    const [showAddVendor, setShowAddVendor] = useState(false);

    const expenseForm = useForm({ title: '', category: 'other', amount: '', expense_date: new Date().toISOString().split('T')[0], vendor: '', is_recurring: false, recurrence: 'one_time' });
    const vendorForm = useForm({ name: '', type: 'saas', monthly_cost: '', billing_cycle: 'monthly', website: '', notes: '' });

    const navigate = (m: string) => router.get('/financials', { month: m }, { preserveState: true });

    const trendMax = Math.max(...trend.map(t => t.revenue), 1);

    const expenseByCategory = expenses.reduce((acc, e) => {
        acc[e.category] = (acc[e.category] ?? 0) + e.amount;
        return acc;
    }, {} as Record<string, number>);

    return (
        <AppLayout>
            <Head title="P&L Dashboard" />
            <div className="max-w-7xl mx-auto px-4 py-6 space-y-6">

                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">P&L Dashboard</h1>
                        <p className="text-sm text-gray-500 mt-0.5">Revenue, costs, and profitability</p>
                    </div>
                    <MonthNav monthYear={monthYear} onChange={navigate} />
                </div>

                {/* P&L Summary */}
                <div className="grid grid-cols-3 gap-4">
                    {/* Revenue */}
                    <div className="bg-white rounded-xl border border-gray-200 p-5">
                        <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Revenue</p>
                        <p className="text-3xl font-bold text-gray-900">{fmt(pnl.revenue.total)}</p>
                        <div className="mt-3 space-y-1 text-sm">
                            <div className="flex justify-between text-gray-600"><span>MRR</span><span className="font-medium">{fmt(pnl.revenue.mrr)}</span></div>
                            <div className="flex justify-between text-gray-600"><span>Invoices paid</span><span className="font-medium">{fmt(pnl.revenue.invoices)}</span></div>
                        </div>
                    </div>
                    {/* Costs */}
                    <div className="bg-white rounded-xl border border-gray-200 p-5">
                        <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Total Costs</p>
                        <p className="text-3xl font-bold text-rose-600">{fmt(pnl.costs.total)}</p>
                        <div className="mt-3 space-y-1 text-sm">
                            <div className="flex justify-between text-gray-600"><span>Payroll</span><span className="font-medium">{fmt(pnl.costs.payroll)}</span></div>
                            <div className="flex justify-between text-gray-600"><span>Expenses</span><span className="font-medium">{fmt(pnl.costs.expenses)}</span></div>
                            <div className="flex justify-between text-gray-600"><span>Vendors/Tools</span><span className="font-medium">{fmt(pnl.costs.vendors)}</span></div>
                        </div>
                    </div>
                    {/* Net Profit */}
                    <div className={cn('rounded-xl border p-5', pnl.net_profit >= 0 ? 'bg-emerald-50 border-emerald-200' : 'bg-rose-50 border-rose-200')}>
                        <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Net Profit</p>
                        <p className={cn('text-3xl font-bold', pnl.net_profit >= 0 ? 'text-emerald-700' : 'text-rose-700')}>{fmt(pnl.net_profit)}</p>
                        <div className="mt-3">
                            <div className="flex justify-between text-sm text-gray-600 mb-1">
                                <span>Margin</span>
                                <span className="font-semibold">{pnl.profit_margin}%</span>
                            </div>
                            <div className="h-2 w-full bg-white/70 rounded-full overflow-hidden">
                                <div className={cn('h-full rounded-full', pnl.profit_margin >= 20 ? 'bg-emerald-500' : pnl.profit_margin >= 0 ? 'bg-amber-400' : 'bg-rose-500')}
                                    style={{ width: `${Math.min(100, Math.abs(pnl.profit_margin))}%` }} />
                            </div>
                        </div>
                    </div>
                </div>

                {/* 6-Month Trend */}
                <div className="bg-white rounded-xl border border-gray-200 p-5">
                    <h2 className="text-sm font-semibold text-gray-700 mb-4">6-Month Trend</h2>
                    <div className="flex items-end gap-3 h-32">
                        {trend.map(t => (
                            <div key={t.month} className="flex-1 flex flex-col items-center gap-1">
                                <div className="w-full flex flex-col justify-end gap-0.5" style={{ height: '100px' }}>
                                    <div className="w-full bg-emerald-400 rounded-t-sm opacity-80" style={{ height: `${(t.revenue / trendMax) * 90}px` }} title={`Revenue: ${fmt(t.revenue)}`} />
                                    <div className="w-full bg-rose-300 rounded-t-sm" style={{ height: `${(t.costs / trendMax) * 90}px`, marginTop: '-2px' }} title={`Costs: ${fmt(t.costs)}`} />
                                </div>
                                <span className="text-xs text-gray-400">{t.label.split(' ')[0]}</span>
                            </div>
                        ))}
                    </div>
                    <div className="flex items-center gap-4 mt-2 text-xs text-gray-500">
                        <span className="flex items-center gap-1"><span className="w-3 h-3 rounded-sm bg-emerald-400 inline-block" /> Revenue</span>
                        <span className="flex items-center gap-1"><span className="w-3 h-3 rounded-sm bg-rose-300 inline-block" /> Costs</span>
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-6">
                    {/* Client Profitability */}
                    <div className="bg-white rounded-xl border border-gray-200">
                        <div className="px-5 py-4 border-b border-gray-100">
                            <h2 className="text-sm font-semibold text-gray-700">Client Profitability</h2>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="bg-gray-50 text-xs text-gray-400 uppercase">
                                    <tr>{['Client', 'Revenue', 'Profit', 'Margin', 'Hours'].map(h => <th key={h} className="px-4 py-2 text-left font-medium">{h}</th>)}</tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {clientProfit.length === 0 && <tr><td colSpan={5} className="px-4 py-6 text-center text-gray-400">No data</td></tr>}
                                    {clientProfit.map(c => (
                                        <tr key={c.id} className="hover:bg-gray-50">
                                            <td className="px-4 py-2 font-medium text-gray-800">
                                                <div className="flex items-center gap-2">
                                                    {c.health_status && <span className={cn('w-2 h-2 rounded-full inline-block', HEALTH_DOT[c.health_status] ?? 'bg-gray-300')} />}
                                                    {c.name}
                                                </div>
                                            </td>
                                            <td className="px-4 py-2 text-gray-600">{fmt(c.revenue)}</td>
                                            <td className={cn('px-4 py-2 font-semibold', c.profit >= 0 ? 'text-emerald-600' : 'text-rose-600')}>{fmt(c.profit)}</td>
                                            <td className={cn('px-4 py-2', c.margin >= 20 ? 'text-emerald-600' : c.margin >= 0 ? 'text-amber-600' : 'text-rose-600')}>{c.margin}%</td>
                                            <td className="px-4 py-2 text-gray-500">{c.hours_logged}h</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {/* Cash Forecast */}
                    <div className="space-y-4">
                        <div className="bg-white rounded-xl border border-gray-200 p-5">
                            <h2 className="text-sm font-semibold text-gray-700 mb-3">90-Day Cash Forecast</h2>
                            <div className="space-y-3">
                                {cashForecast.map(f => (
                                    <div key={f.month} className="flex items-center justify-between p-3 rounded-lg bg-gray-50">
                                        <span className="text-sm font-medium text-gray-700">{f.label}</span>
                                        <div className="text-right">
                                            <p className="text-xs text-gray-400">In: {fmt(f.projected_in)} · Out: {fmt(f.projected_out)}</p>
                                            <p className={cn('text-sm font-bold', f.net >= 0 ? 'text-emerald-600' : 'text-rose-600')}>
                                                {f.net >= 0 ? '+' : ''}{fmt(f.net)}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* Vendor Costs */}
                        <div className="bg-white rounded-xl border border-gray-200 p-5">
                            <div className="flex items-center justify-between mb-3">
                                <h2 className="text-sm font-semibold text-gray-700">Tool & Vendor Costs</h2>
                                <button onClick={() => setShowAddVendor(true)} className="text-xs text-indigo-600 hover:text-indigo-800 font-medium flex items-center gap-1">
                                    <Plus className="w-3 h-3" /> Add
                                </button>
                            </div>
                            <div className="space-y-2">
                                {vendors.slice(0, 6).map(v => (
                                    <div key={v.id} className="flex justify-between items-center text-sm">
                                        <span className="text-gray-700 truncate flex-1">{v.name}</span>
                                        <span className="text-xs text-gray-400 capitalize mr-3">{v.type}</span>
                                        <span className="font-medium text-gray-800">{fmt(v.monthly_cost)}/mo</span>
                                    </div>
                                ))}
                                {vendors.length === 0 && <p className="text-xs text-gray-400">No vendor contracts yet.</p>}
                                {vendors.length > 6 && <p className="text-xs text-gray-400">+{vendors.length - 6} more</p>}
                                <div className="pt-2 border-t border-gray-100 flex justify-between text-sm">
                                    <span className="font-semibold text-gray-700">Total</span>
                                    <span className="font-bold text-gray-900">{fmt(vendors.reduce((s, v) => s + v.monthly_cost, 0))}/mo</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Expenses */}
                <div className="bg-white rounded-xl border border-gray-200">
                    <div className="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                        <h2 className="text-sm font-semibold text-gray-700">Expenses This Month</h2>
                        <button onClick={() => setShowAddExpense(true)} className="flex items-center gap-1.5 text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                            <Plus className="w-3.5 h-3.5" /> Add Expense
                        </button>
                    </div>
                    {/* Category summary */}
                    <div className="px-5 py-3 flex flex-wrap gap-2 border-b border-gray-100">
                        {Object.entries(expenseByCategory).map(([cat, total]) => (
                            <span key={cat} className={cn('px-2.5 py-1 rounded-full text-xs font-medium', CATEGORY_COLORS[cat] ?? CATEGORY_COLORS.other)}>
                                {cat}: {fmt(total)}
                            </span>
                        ))}
                        {Object.keys(expenseByCategory).length === 0 && <span className="text-xs text-gray-400">No expenses recorded.</span>}
                    </div>
                    <div className="divide-y divide-gray-100">
                        {expenses.map(e => (
                            <div key={e.id} className="px-5 py-3 flex items-center justify-between hover:bg-gray-50">
                                <div>
                                    <p className="text-sm font-medium text-gray-800">{e.title}</p>
                                    <p className="text-xs text-gray-400">{e.vendor ?? e.category} · {e.expense_date}</p>
                                </div>
                                <div className="flex items-center gap-3">
                                    {e.is_recurring && <span className="text-xs text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full">Recurring</span>}
                                    <span className="font-semibold text-gray-900">{fmt(e.amount)}</span>
                                </div>
                            </div>
                        ))}
                        {expenses.length === 0 && <p className="px-5 py-6 text-center text-gray-400 text-sm">No expenses this month.</p>}
                    </div>
                </div>
            </div>

            {/* Add Expense Modal */}
            {showAddExpense && (
                <div className="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
                    <div className="bg-white rounded-2xl shadow-xl w-full max-w-md">
                        <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                            <h2 className="text-base font-semibold">Add Expense</h2>
                            <button onClick={() => setShowAddExpense(false)}><X className="w-5 h-5 text-gray-400" /></button>
                        </div>
                        <form onSubmit={e => { e.preventDefault(); expenseForm.post('/expenses', { onSuccess: () => { setShowAddExpense(false); expenseForm.reset(); } }); }}
                            className="px-6 py-4 space-y-3">
                            <input type="text" placeholder="Title *" value={expenseForm.data.title} onChange={e => expenseForm.setData('title', e.target.value)}
                                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                            <div className="grid grid-cols-2 gap-3">
                                <select value={expenseForm.data.category} onChange={e => expenseForm.setData('category', e.target.value)}
                                    className="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                    {['tools','freelancer','office','ads','travel','hardware','other'].map(c => <option key={c} value={c} className="capitalize">{c.charAt(0).toUpperCase()+c.slice(1)}</option>)}
                                </select>
                                <input type="number" placeholder="Amount (₹)" value={expenseForm.data.amount} onChange={e => expenseForm.setData('amount', e.target.value)}
                                    className="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                            </div>
                            <input type="date" value={expenseForm.data.expense_date} onChange={e => expenseForm.setData('expense_date', e.target.value)}
                                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                            <input type="text" placeholder="Vendor (optional)" value={expenseForm.data.vendor} onChange={e => expenseForm.setData('vendor', e.target.value)}
                                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                            <label className="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                <input type="checkbox" checked={expenseForm.data.is_recurring} onChange={e => expenseForm.setData('is_recurring', e.target.checked)} className="rounded" />
                                Recurring expense
                            </label>
                            <div className="flex justify-end gap-3 pt-1">
                                <button type="button" onClick={() => setShowAddExpense(false)} className="px-4 py-2 text-sm text-gray-600">Cancel</button>
                                <button type="submit" disabled={expenseForm.processing} className="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                                    {expenseForm.processing ? 'Saving…' : 'Save Expense'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Add Vendor Modal */}
            {showAddVendor && (
                <div className="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
                    <div className="bg-white rounded-2xl shadow-xl w-full max-w-md">
                        <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                            <h2 className="text-base font-semibold">Add Vendor / Tool</h2>
                            <button onClick={() => setShowAddVendor(false)}><X className="w-5 h-5 text-gray-400" /></button>
                        </div>
                        <form onSubmit={e => { e.preventDefault(); vendorForm.post('/vendors', { onSuccess: () => { setShowAddVendor(false); vendorForm.reset(); } }); }}
                            className="px-6 py-4 space-y-3">
                            <input type="text" placeholder="Name (e.g. SEMrush, Canva) *" value={vendorForm.data.name} onChange={e => vendorForm.setData('name', e.target.value)}
                                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                            <div className="grid grid-cols-2 gap-3">
                                <select value={vendorForm.data.type} onChange={e => vendorForm.setData('type', e.target.value)}
                                    className="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                    {['freelancer','tool','saas','service','infrastructure','other'].map(t => <option key={t} value={t} className="capitalize">{t.toUpperCase()}</option>)}
                                </select>
                                <input type="number" placeholder="Monthly Cost (₹) *" value={vendorForm.data.monthly_cost} onChange={e => vendorForm.setData('monthly_cost', e.target.value)}
                                    className="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                            </div>
                            <select value={vendorForm.data.billing_cycle} onChange={e => vendorForm.setData('billing_cycle', e.target.value)}
                                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                <option value="monthly">Monthly</option>
                                <option value="annual">Annual</option>
                                <option value="one_time">One-time</option>
                            </select>
                            <input type="url" placeholder="Website (optional)" value={vendorForm.data.website} onChange={e => vendorForm.setData('website', e.target.value)}
                                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
                            <div className="flex justify-end gap-3 pt-1">
                                <button type="button" onClick={() => setShowAddVendor(false)} className="px-4 py-2 text-sm text-gray-600">Cancel</button>
                                <button type="submit" disabled={vendorForm.processing} className="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                                    {vendorForm.processing ? 'Saving…' : 'Add Vendor'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}

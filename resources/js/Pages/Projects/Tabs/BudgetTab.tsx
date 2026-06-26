import React from 'react';
import { Link } from '@inertiajs/react';
import { cn, formatCurrency, formatDate } from '@/lib/utils';
import { ChevronLeft, DollarSign, TrendingUp, TrendingDown, FileText, Activity } from 'lucide-react';

interface Props {
    project: {
        id: string;
        name: string;
        status: string;
        budget: number;
        budget_used: number;
        start_date: string | null;
        end_date: string | null;
        total_logged_hours: number;
    };
    financials: {
        revenue: number;
        invoiced_revenue: number;
        base_budget: number;
        total_expenses: number;
        total_ad_spend: number;
        labor_cost: number;
        total_costs: number;
        profit_margin: number;
        profit_margin_percent: number;
    };
    invoices: Array<{ id: string; invoice_number: string; amount: string; status: string; issue_date: string }>;
    expenses: Array<{ id: string; title: string; amount: string; category: string; expense_date: string }>;
    campaignBudgets: Array<{ id: string; channel: string; allocated_budget: string; spent_amount: string; month_year: string }>;
    retainers?: Array<{ id: string; name: string; monthly_value: string; status: string; billing_cycle: string; currency: string }>;
}

export default function BudgetTab({ project, financials, invoices, expenses, campaignBudgets, retainers = [] }: Props) {
    const isProfitable = financials.profit_margin >= 0;

    return (
        <div>

            {/* Main KPIs */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
                <div className="bg-white rounded-xl border border-gray-100 p-5 shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
                    <p className="text-[11px] text-gray-500 font-medium uppercase tracking-wide mb-1">Total Revenue</p>
                    <p className="text-2xl font-bold text-gray-900">{formatCurrency(financials.revenue)}</p>
                    <p className="text-[11px] text-gray-400 mt-0.5">
                        {financials.invoiced_revenue > 0 ? 'From issued invoices' : 'From fixed budget'}
                    </p>
                </div>
                
                <div className="bg-white rounded-xl border border-gray-100 p-5 shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
                    <p className="text-[11px] text-gray-500 font-medium uppercase tracking-wide mb-1">Total Costs</p>
                    <p className="text-2xl font-bold text-gray-900">{formatCurrency(financials.total_costs)}</p>
                    <p className="text-[11px] text-gray-400 mt-0.5">Ads, Expenses, and Labor</p>
                </div>

                <div className={cn('rounded-xl border p-5 shadow-[0_1px_3px_rgba(0,0,0,0.04)]', isProfitable ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200')}>
                    <p className={cn('text-[11px] font-medium uppercase tracking-wide mb-1', isProfitable ? 'text-green-600' : 'text-red-600')}>
                        Profit Margin
                    </p>
                    <div className="flex items-center gap-2">
                        <p className={cn('text-2xl font-bold', isProfitable ? 'text-green-700' : 'text-red-700')}>
                            {formatCurrency(financials.profit_margin)}
                        </p>
                        <span className={cn('text-[13px] font-semibold px-2 py-0.5 rounded-full', isProfitable ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700')}>
                            {financials.profit_margin_percent}%
                        </span>
                    </div>
                </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* Cost Breakdown */}
                <div className="space-y-6">
                    <div className="bg-white rounded-xl border border-gray-100 shadow-[0_1px_3px_rgba(0,0,0,0.04)] overflow-hidden">
                        <div className="p-4 border-b border-gray-50">
                            <h3 className="text-[13px] font-semibold text-gray-900">Cost Breakdown</h3>
                        </div>
                        <div className="p-0">
                            <table className="w-full text-sm text-left">
                                <tbody>
                                    <tr className="border-b border-gray-50">
                                        <td className="px-4 py-3 text-gray-600">Ad Spend (Campaigns)</td>
                                        <td className="px-4 py-3 text-right font-medium text-gray-900">{formatCurrency(financials.total_ad_spend)}</td>
                                    </tr>
                                    <tr className="border-b border-gray-50">
                                        <td className="px-4 py-3 text-gray-600">General Expenses</td>
                                        <td className="px-4 py-3 text-right font-medium text-gray-900">{formatCurrency(financials.total_expenses)}</td>
                                    </tr>
                                    <tr className="bg-gray-50/50">
                                        <td className="px-4 py-3 text-gray-600">
                                            Labor Cost
                                            <span className="block text-[11px] text-gray-400">{project.total_logged_hours} hours logged</span>
                                        </td>
                                        <td className="px-4 py-3 text-right font-medium text-gray-900">{formatCurrency(financials.labor_cost)}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {/* Ad Budgets List */}
                    <div className="bg-white rounded-xl border border-gray-100 shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
                        <div className="p-4 border-b border-gray-50 flex items-center justify-between">
                            <h3 className="text-[13px] font-semibold text-gray-900">Ad Budgets</h3>
                        </div>
                        {campaignBudgets.length === 0 ? (
                            <p className="text-sm text-gray-400 p-4">No ad budgets linked.</p>
                        ) : (
                            <div className="divide-y divide-gray-50">
                                {campaignBudgets.map(b => (
                                    <div key={b.id} className="p-4 flex items-center justify-between">
                                        <div>
                                            <p className="text-sm font-medium text-gray-900 capitalize">{b.channel.replace('_', ' ')}</p>
                                            <p className="text-[11px] text-gray-500">{b.month_year}</p>
                                        </div>
                                        <div className="text-right">
                                            <p className="text-sm font-medium text-gray-900">{formatCurrency(parseFloat(b.spent_amount))}</p>
                                            <p className="text-[11px] text-gray-500">of {formatCurrency(parseFloat(b.allocated_budget))}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>

                {/* Right Column: Revenue and Expenses */}
                <div className="space-y-6">
                    {/* Retainers List */}
                    <div className="bg-white rounded-xl border border-gray-100 shadow-[0_1px_3px_rgba(0,0,0,0.04)] mb-6">
                        <div className="p-4 border-b border-gray-50 flex items-center justify-between">
                            <h3 className="text-[13px] font-semibold text-gray-900">Client Retainers</h3>
                        </div>
                        {retainers.length === 0 ? (
                            <p className="text-sm text-gray-400 p-4">No active retainers for this client.</p>
                        ) : (
                            <div className="divide-y divide-gray-50">
                                {retainers.map(r => (
                                    <div key={r.id} className="p-4 flex items-center justify-between">
                                        <div>
                                            <p className="text-sm font-medium text-gray-900">{r.name}</p>
                                            <p className="text-[11px] text-gray-500 capitalize">{r.billing_cycle}</p>
                                        </div>
                                        <div className="text-right">
                                            <p className="text-sm font-medium text-gray-900">{formatCurrency(parseFloat(r.monthly_value))}</p>
                                            <span className={cn('text-[10px] font-medium px-2 py-0.5 rounded-full uppercase', 
                                                r.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'
                                            )}>
                                                {r.status}
                                            </span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Invoices List */}
                    <div className="bg-white rounded-xl border border-gray-100 shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
                        <div className="p-4 border-b border-gray-50">
                            <h3 className="text-[13px] font-semibold text-gray-900">Invoices</h3>
                        </div>
                        {invoices.length === 0 ? (
                            <p className="text-sm text-gray-400 p-4">No invoices linked.</p>
                        ) : (
                            <div className="divide-y divide-gray-50">
                                {invoices.map(inv => (
                                    <div key={inv.id} className="p-4 flex items-center justify-between">
                                        <div>
                                            <p className="text-sm font-medium text-gray-900">{inv.invoice_number}</p>
                                            <p className="text-[11px] text-gray-500">{inv.issue_date}</p>
                                        </div>
                                        <div className="text-right">
                                            <p className="text-sm font-medium text-gray-900">{formatCurrency(parseFloat(inv.amount))}</p>
                                            <span className={cn('text-[10px] font-medium px-2 py-0.5 rounded-full uppercase', 
                                                inv.status === 'paid' ? 'bg-green-100 text-green-700' : 
                                                inv.status === 'unpaid' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-700'
                                            )}>
                                                {inv.status}
                                            </span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Expenses List */}
                    <div className="bg-white rounded-xl border border-gray-100 shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
                        <div className="p-4 border-b border-gray-50">
                            <h3 className="text-[13px] font-semibold text-gray-900">General Expenses</h3>
                        </div>
                        {expenses.length === 0 ? (
                            <p className="text-sm text-gray-400 p-4">No expenses linked.</p>
                        ) : (
                            <div className="divide-y divide-gray-50">
                                {expenses.map(exp => (
                                    <div key={exp.id} className="p-4 flex items-center justify-between">
                                        <div>
                                            <p className="text-sm font-medium text-gray-900">{exp.title}</p>
                                            <p className="text-[11px] text-gray-500 capitalize">{exp.category} • {exp.expense_date}</p>
                                        </div>
                                        <div className="text-right">
                                            <p className="text-sm font-medium text-gray-900">{formatCurrency(parseFloat(exp.amount))}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}

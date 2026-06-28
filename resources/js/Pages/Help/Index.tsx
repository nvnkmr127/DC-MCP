import React, { useState } from 'react';
import { Button } from '@/Components/ui/Button';
import { Head } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { cn } from '@/lib/utils';
import {
    BookOpen, MessageSquare, Video, Zap, ChevronDown, ChevronUp,
    FolderOpen, Users, BarChart2, Clock, Target, Calendar,
} from 'lucide-react';

const FAQ = [
    {
        q: 'How do I add a new client?',
        a: 'Go to Clients → click "New Client". Fill in name, email, tier, and status. You can also set monthly retainer value for revenue tracking.',
    },
    {
        q: 'How do recurring tasks work?',
        a: 'Under Recurring Tasks, create a rule with a frequency (daily/weekly/monthly/quarterly). The system auto-spawns tasks on schedule into the assigned project. You can pause or resume rules at any time.',
    },
    {
        q: 'How do I track billable vs non-billable time?',
        a: 'Use the Timesheets page to log time. Each entry can be marked Billable or Non-billable. Use Start/Stop Timer for real-time tracking. The weekly grid shows your utilization at a glance.',
    },
    {
        q: 'How does the client portal work?',
        a: 'Go to Settings → Client Portal and invite a client contact by email. They receive a magic link. Once logged in, they can view shared projects/reports and submit requests that convert to tasks for your team.',
    },
    {
        q: 'How do I set up OKRs?',
        a: 'Go to Goals and create a goal for the current quarter. Add key results with a target value and unit (%, ₹, count, etc.). Update the current value inline — progress auto-calculates as the average KR completion.',
    },
    {
        q: 'What are MCP integrations?',
        a: 'MCP (Model Context Protocol) lets you connect external services like Gmail, Google Calendar, Notion, Meta Ads, and custom APIs. Go to Settings → Integrations to add connections and sync data into the AI assistant.',
    },
    {
        q: 'How do campaign budget burn alerts work?',
        a: 'The system checks hourly. When a client\'s ad budget crosses 70% or 90% spent, a notification is sent to CEO-role users and an alert banner appears on the Campaign Budgets page.',
    },
];

const SECTIONS = [
    { icon: FolderOpen, label: 'Projects & Tasks', desc: 'Kanban boards, task dependencies, time logging, recurring tasks.' },
    { icon: Users, label: 'Clients & CRM', desc: 'Client profiles, communication log, upsell tracking, health scores.' },
    { icon: BarChart2, label: 'Revenue & Finance', desc: 'Retainers, invoices, expenses, payroll, campaign budgets.' },
    { icon: Target, label: 'Goals & OKRs', desc: 'Quarterly goals, key results, org-level progress overview.' },
    { icon: Calendar, label: 'Content Calendar', desc: 'Schedule and track content across all channels with status workflow.' },
    { icon: Clock, label: 'Timesheets', desc: 'Weekly time grid, live timer, billable vs non-billable, utilization %.' },
];

function FaqItem({ q, a }: { q: string; a: string }) {
    const [open, setOpen] = useState(false);
    return (
        <div className="border-b border-gray-100 last:border-0">
            <Button onClick={() => setOpen(!open)}
                className="w-full flex items-center justify-between px-5 py-4 text-left hover:bg-gray-50 transition-colors">
                <span className="text-sm font-medium text-gray-900">{q}</span>
                {open ? <ChevronUp size={15} className="text-gray-400 shrink-0" /> : <ChevronDown size={15} className="text-gray-400 shrink-0" />}
            </Button>
            {open && <p className="px-5 pb-4 text-sm text-gray-600 leading-relaxed">{a}</p>}
        </div>
    );
}

export default function HelpIndex() {
    return (
        <AppLayout title="Help & Support">
            <Head title="Help & Support" />

            <div className="max-w-4xl space-y-8">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Help & Support</h1>
                    <p className="text-sm text-gray-500 mt-1">Everything you need to get the most out of DC-MCP</p>
                </div>

                {/* Quick links */}
                <div className="grid grid-cols-3 gap-3">
                    {[
                        { icon: BookOpen,     label: 'Documentation',  desc: 'Guides and reference' },
                        { icon: Video,        label: 'Video Tutorials', desc: 'Step-by-step walkthroughs' },
                        { icon: MessageSquare,label: 'Contact Support', desc: 'Talk to the team' },
                        { icon: Zap,          label: 'Quick Start',    desc: 'Get up and running fast' },
                    ].map(({ icon: Icon, label, desc }) => (
                        <div key={label} className="bg-white rounded-xl border border-gray-200 p-4 flex items-start gap-3 cursor-pointer hover:border-indigo-200 hover:shadow-sm transition-all group">
                            <div className="w-9 h-9 rounded-lg bg-indigo-50 flex items-center justify-center shrink-0 group-hover:bg-indigo-100 transition-colors">
                                <Icon size={16} className="text-indigo-600" />
                            </div>
                            <div>
                                <p className="text-sm font-semibold text-gray-900">{label}</p>
                                <p className="text-xs text-gray-400 mt-0.5">{desc}</p>
                            </div>
                        </div>
                    ))}
                </div>

                {/* Feature overview */}
                <div>
                    <h2 className="text-base font-semibold text-gray-900 mb-3">Feature Overview</h2>
                    <div className="grid grid-cols-2 gap-3">
                        {SECTIONS.map(({ icon: Icon, label, desc }) => (
                            <div key={label} className="bg-white rounded-xl border border-gray-200 p-4 flex items-start gap-3">
                                <div className="w-8 h-8 rounded-lg bg-gray-50 flex items-center justify-center shrink-0">
                                    <Icon size={15} className="text-gray-500" />
                                </div>
                                <div>
                                    <p className="text-sm font-medium text-gray-900">{label}</p>
                                    <p className="text-xs text-gray-500 mt-0.5">{desc}</p>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                {/* FAQ */}
                <div>
                    <h2 className="text-base font-semibold text-gray-900 mb-3">Frequently Asked Questions</h2>
                    <div className="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
                        {FAQ.map(({ q, a }) => <FaqItem key={q} q={q} a={a} />)}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

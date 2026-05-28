import React, { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { FileText, ArrowLeft, ArrowRight, Check, AlertTriangle, Calendar } from 'lucide-react';
import { cn } from '@/lib/utils';
import axios from 'axios';
import { toast } from 'sonner';

interface Project {
    id: string;
    name: string;
    client_id: string;
}

interface Client {
    id: string;
    name: string;
}

interface Props {
    projects: Project[];
    clients: Client[];
}

const TEMPLATES = [
    { id: 'seo_report', name: 'SEO Performance Report', desc: 'Organic traffic, technical health, keywords, and backlink audit stats.' },
    { id: 'ads_report', name: 'Paid Ads Performance', desc: 'Meta Ads and search engine ad spends, conversions, and ROAS audit.' },
    { id: 'social_report', name: 'Social Media Growth', desc: 'Engagement rates, follower counts, and top performing content.' },
    { id: 'sprint_report', name: 'Development Sprint Review', desc: 'Sprint goals, team velocity, task completions, and bottlenecks.' },
    { id: 'full_service', name: 'Full Service Report', desc: 'Unified multi-channel report combining SEO, paid media, and social.' }
];

export default function ReportsCreate({ projects, clients }: Props) {
    const [step, setStep] = useState(1);
    const [scheduleReport, setScheduleReport] = useState(false);

    const form = useForm({
        title: '',
        template: 'seo_report',
        type: 'custom',
        project_id: '',
        client_id: '',
        date_from: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
        date_to: new Date().toISOString().split('T')[0],
        recipients: [] as string[],
        // Schedule options
        frequency: 'weekly',
        send_day: 1,
    });

    const [recipientInput, setRecipientInput] = useState('');

    const handleAddRecipient = (e: React.FormEvent) => {
        e.preventDefault();
        if (recipientInput && !form.data.recipients.includes(recipientInput)) {
            form.setData('recipients', [...form.data.recipients, recipientInput]);
            setRecipientInput('');
        }
    };

    const handleRemoveRecipient = (email: string) => {
        form.setData('recipients', form.data.recipients.filter(r => r !== email));
    };

    const handleProjectChange = (id: string) => {
        form.setData('project_id', id);
        const proj = projects.find(p => p.id === id);
        if (proj && proj.client_id) {
            form.setData('client_id', proj.client_id);
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        // Standard PDF generation request
        axios.post('/api/v1/reports', {
            title: form.data.title || `Report - ${form.data.template.toUpperCase()} - ${new Date().toISOString().split('T')[0]}`,
            template: form.data.template,
            type: form.data.type,
            project_id: form.data.project_id || null,
            client_id: form.data.client_id || null,
            date_from: form.data.date_from,
            date_to: form.data.date_to,
            recipients: form.data.recipients,
        })
        .then(() => {
            // Check if we should also store a recurring schedule
            if (scheduleReport) {
                return axios.post('/api/v1/report-schedules', {
                    title: form.data.title || `Scheduled ${form.data.template.toUpperCase()}`,
                    template: form.data.template,
                    type: form.data.type,
                    project_id: form.data.project_id || null,
                    client_id: form.data.client_id || null,
                    frequency: form.data.frequency,
                    send_day: form.data.send_day,
                    recipients: form.data.recipients,
                });
            }
        })
        .then(() => {
            toast.success('Report generation started!');
            router.visit('/reports');
        })
        .catch(err => {
            toast.error(err.response?.data?.message ?? 'Failed to submit report configuration.');
        });
    };

    return (
        <AppLayout title="Create Report">
            <Head title="Create Report" />

            {/* Back action */}
            <div className="mb-6">
                <Link
                    href="/reports"
                    className="inline-flex items-center gap-1.5 text-xs font-bold text-gray-500 hover:text-gray-900"
                >
                    <ArrowLeft size={13} /> Back to reports
                </Link>
            </div>

            {/* Wizard Container */}
            <div className="max-w-2xl bg-white border border-gray-100 rounded-2xl shadow-[0_1px_3px_rgba(0,0,0,0.02)] overflow-hidden">
                
                {/* Steps Header */}
                <div className="bg-gray-50/50 border-b border-gray-100 px-6 py-4 flex justify-between items-center">
                    <h3 className="text-sm font-bold text-gray-900">
                        {step === 1 && "Choose Report Template"}
                        {step === 2 && "Configure Target & Dates"}
                        {step === 3 && "Recipients & Automation"}
                    </h3>
                    <span className="text-[10px] uppercase font-bold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-md">
                        Step {step} of 3
                    </span>
                </div>

                {/* Form Wrapper */}
                <form onSubmit={handleSubmit} className="p-6 space-y-6">

                    {/* STEP 1: CHOOSE TEMPLATE */}
                    {step === 1 && (
                        <div className="space-y-3">
                            {TEMPLATES.map(t => (
                                <label
                                    key={t.id}
                                    onClick={() => form.setData('template', t.id)}
                                    className={cn(
                                        "flex gap-4 p-4 border rounded-2xl cursor-pointer hover:bg-gray-50/50 transition-all",
                                        form.data.template === t.id
                                            ? "border-indigo-600 bg-indigo-50/10 shadow-[inset_0_1px_2px_rgba(99,102,241,0.02)]"
                                            : "border-gray-150"
                                    )}
                                >
                                    <input
                                        type="radio"
                                        name="template"
                                        checked={form.data.template === t.id}
                                        onChange={() => {}}
                                        className="sr-only"
                                    />
                                    <div className={cn(
                                        "w-8 h-8 rounded-xl flex items-center justify-center shrink-0",
                                        form.data.template === t.id ? "bg-indigo-50 text-indigo-600" : "bg-gray-50 text-gray-400"
                                    )}>
                                        <FileText size={15} />
                                    </div>
                                    <div>
                                        <h4 className="text-xs font-bold text-gray-900">{t.name}</h4>
                                        <p className="text-[11px] text-gray-400 mt-1">{t.desc}</p>
                                    </div>
                                    {form.data.template === t.id && (
                                        <Check size={14} className="text-indigo-600 ml-auto shrink-0 self-center" />
                                    )}
                                </label>
                            ))}
                        </div>
                    )}

                    {/* STEP 2: CONFIGURE TARGETS & DATE RANGE */}
                    {step === 2 && (
                        <div className="space-y-4">
                            <div>
                                <label className="block text-xs font-bold text-gray-600 mb-1">Report Title</label>
                                <input
                                    type="text"
                                    value={form.data.title}
                                    onChange={e => form.setData('title', e.target.value)}
                                    placeholder="e.g. Q3 Organic SEO Performance Report"
                                    className="w-full px-3.5 py-2.5 border border-gray-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-gray-50 focus:bg-white"
                                />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-bold text-gray-600 mb-1">Link to Project (Optional)</label>
                                    <select
                                        value={form.data.project_id}
                                        onChange={e => handleProjectChange(e.target.value)}
                                        className="w-full px-3.5 py-2.5 border border-gray-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-gray-50 focus:bg-white"
                                    >
                                        <option value="">No Project Mapping</option>
                                        {projects.map(p => (
                                            <option key={p.id} value={p.id}>{p.name}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-gray-600 mb-1">Link to Client (Optional)</label>
                                    <select
                                        value={form.data.client_id}
                                        onChange={e => form.setData('client_id', e.target.value)}
                                        className="w-full px-3.5 py-2.5 border border-gray-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-gray-50 focus:bg-white"
                                    >
                                        <option value="">No Client Mapping</option>
                                        {clients.map(c => (
                                            <option key={c.id} value={c.id}>{c.name}</option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-bold text-gray-600 mb-1">Start Date</label>
                                    <input
                                        type="date"
                                        required
                                        value={form.data.date_from}
                                        onChange={e => form.setData('date_from', e.target.value)}
                                        className="w-full px-3.5 py-2.5 border border-gray-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-gray-50"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-gray-600 mb-1">End Date</label>
                                    <input
                                        type="date"
                                        required
                                        value={form.data.date_to}
                                        onChange={e => form.setData('date_to', e.target.value)}
                                        className="w-full px-3.5 py-2.5 border border-gray-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-gray-50"
                                    />
                                </div>
                            </div>
                        </div>
                    )}

                    {/* STEP 3: RECIPIENTS & AUTOMATION SCHEDULE */}
                    {step === 3 && (
                        <div className="space-y-6">
                            
                            {/* Recipients List */}
                            <div>
                                <label className="block text-xs font-bold text-gray-600 mb-1">Email Recipients</label>
                                <div className="flex gap-2">
                                    <input
                                        type="email"
                                        value={recipientInput}
                                        onChange={e => setRecipientInput(e.target.value)}
                                        placeholder="stakeholder@company.com"
                                        className="flex-1 px-3.5 py-2 border border-gray-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-gray-50 focus:bg-white"
                                    />
                                    <button
                                        type="button"
                                        onClick={handleAddRecipient}
                                        className="px-4 py-2 bg-indigo-50 text-indigo-600 text-xs font-semibold rounded-xl hover:bg-indigo-100 transition-colors border border-indigo-100"
                                    >
                                        Add
                                    </button>
                                </div>

                                {form.data.recipients.length > 0 && (
                                    <div className="flex flex-wrap gap-2 mt-3">
                                        {form.data.recipients.map(email => (
                                            <span key={email} className="inline-flex items-center gap-1 bg-gray-50 border border-gray-200 text-gray-600 px-2 py-0.5 rounded-lg text-[10px] font-semibold">
                                                {email}
                                                <button type="button" onClick={() => handleRemoveRecipient(email)} className="text-red-500 hover:text-red-700 ml-1">
                                                    ×
                                                </button>
                                            </span>
                                        ))}
                                    </div>
                                )}
                            </div>

                            {/* Scheduling Box */}
                            <div className="border border-gray-100 rounded-2xl p-4 bg-gray-50/50 space-y-4">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <Calendar size={14} className="text-indigo-500" />
                                        <div>
                                            <h4 className="text-xs font-bold text-gray-900">Recurring Automation</h4>
                                            <p className="text-[10px] text-gray-400">Run and send this report repeatedly.</p>
                                        </div>
                                    </div>
                                    <input
                                        type="checkbox"
                                        checked={scheduleReport}
                                        onChange={e => setScheduleReport(e.target.checked)}
                                        className="w-4 h-4 rounded text-indigo-600 border-gray-300 focus:ring-indigo-500"
                                    />
                                </div>

                                {scheduleReport && (
                                    <div className="grid grid-cols-2 gap-4 pt-2 border-t border-gray-150">
                                        <div>
                                            <label className="block text-[10px] font-bold text-gray-500 mb-1">Frequency</label>
                                            <select
                                                value={form.data.frequency}
                                                onChange={e => form.setData('frequency', e.target.value)}
                                                className="w-full px-3.5 py-2 border border-gray-200 rounded-xl text-xs bg-white"
                                            >
                                                <option value="weekly">Weekly</option>
                                                <option value="monthly">Monthly</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label className="block text-[10px] font-bold text-gray-500 mb-1">
                                                {form.data.frequency === 'weekly' ? 'Weekday (Monday = 1)' : 'Day of Month'}
                                            </label>
                                            <input
                                                type="number"
                                                required
                                                min={1}
                                                max={form.data.frequency === 'weekly' ? 7 : 31}
                                                value={form.data.send_day}
                                                onChange={e => form.setData('send_day', parseInt(e.target.value))}
                                                className="w-full px-3.5 py-2 border border-gray-200 rounded-xl text-xs bg-white"
                                            />
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    )}

                    {/* Step Navigation */}
                    <div className="flex justify-between items-center border-t border-gray-100 pt-5">
                        {step > 1 ? (
                            <button
                                type="button"
                                onClick={() => setStep(step - 1)}
                                className="px-4 py-2 border border-gray-200 rounded-xl text-xs font-semibold text-gray-600 hover:bg-gray-50"
                            >
                                Back
                            </button>
                        ) : (
                            <div />
                        )}

                        {step < 3 ? (
                            <button
                                type="button"
                                onClick={() => setStep(step + 1)}
                                className="flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl transition-all shadow-md"
                            >
                                Continue <ArrowRight size={13} />
                            </button>
                        ) : (
                            <button
                                type="submit"
                                className="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl transition-all shadow-md"
                            >
                                Generate & Schedule Report
                            </button>
                        )}
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}

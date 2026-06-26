import React, { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { cn } from '@/lib/utils';
import { ArrowLeft, Save, ChevronRight, ChevronLeft, Check, Plus, Trash2 } from 'lucide-react';
import { Button } from '@/Components/ui/Button';
import { Input, Label, TextArea } from '@/Components/ui/Input';

const inputCls = 'w-full px-3.5 py-2.5 border border-gray-200 rounded-lg text-[13px] bg-gray-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all placeholder-gray-400';
const labelCls = 'block text-[12px] font-semibold text-gray-700 mb-1.5';

export default function OnboardWizard() {
    const [step, setStep] = useState(1);

    const form = useForm({
        client: {
            name: '',
            company: '',
            email: '',
            phone: '',
            website: '',
            tier: 'enterprise',
            status: 'active'
        },
        sow: {
            title: '',
            description: '',
            start_date: '',
            end_date: '',
            deliverables: [] as any[]
        },
        proposal: {
            title: '',
            valid_until: '',
            notes: '',
            line_items: [] as any[]
        },
        onboarding: {
            target_go_live: '',
            notes: ''
        }
    });

    const nextStep = () => setStep(s => Math.min(s + 1, 4));
    const prevStep = () => setStep(s => Math.max(s - 1, 1));

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/clients/onboard');
    };

    const addDeliverable = () => {
        const current = [...form.data.sow.deliverables];
        current.push({ title: '', service_type: 'consulting', frequency: 'one_time', quantity: 1 });
        form.setData('sow', { ...form.data.sow, deliverables: current });
    };

    const addLineItem = () => {
        const current = [...form.data.proposal.line_items];
        current.push({ service: '', description: '', unit_price: 0, quantity: 1, frequency: 'one_time' });
        form.setData('proposal', { ...form.data.proposal, line_items: current });
    };

    const removeDeliverable = (idx: number) => {
        const current = [...form.data.sow.deliverables];
        current.splice(idx, 1);
        form.setData('sow', { ...form.data.sow, deliverables: current });
    };

    const removeLineItem = (idx: number) => {
        const current = [...form.data.proposal.line_items];
        current.splice(idx, 1);
        form.setData('proposal', { ...form.data.proposal, line_items: current });
    };

    return (
        <AppLayout title="Onboard New Client">
            <Head title="Onboard New Client" />

            <div className="max-w-4xl mx-auto pb-12">
                <div className="flex items-center justify-between mb-8">
                    <div className="flex items-center gap-3">
                        <Link href="/clients" className="p-1.5 rounded-lg hover:bg-gray-100 text-gray-700 transition-colors">
                            <ArrowLeft size={17} />
                        </Link>
                        <h1 className="text-[18px] font-bold text-gray-900">Client Onboarding Wizard</h1>
                    </div>
                </div>

                {/* Stepper */}
                <div className="flex items-center justify-between mb-8 px-4">
                    {[
                        { id: 1, label: 'Client Details' },
                        { id: 2, label: 'Statement of Work' },
                        { id: 3, label: 'Proposal & Pricing' },
                        { id: 4, label: 'Onboarding Setup' }
                    ].map((s) => (
                        <div key={s.id} className="flex flex-col items-center flex-1 relative">
                            {s.id !== 1 && (
                                <div className={cn("absolute top-4 -left-1/2 w-full h-[2px]", step >= s.id ? "bg-indigo-600" : "bg-gray-200")} />
                            )}
                            <div className={cn("w-8 h-8 rounded-full flex items-center justify-center text-sm font-semibold relative z-10", 
                                step > s.id ? "bg-indigo-600 text-white" : step === s.id ? "bg-indigo-600 text-white ring-4 ring-indigo-100" : "bg-gray-100 text-gray-400"
                            )}>
                                {step > s.id ? <Check size={16} /> : s.id}
                            </div>
                            <span className={cn("mt-2 text-xs font-medium", step >= s.id ? "text-indigo-900" : "text-gray-500")}>{s.label}</span>
                        </div>
                    ))}
                </div>

                <div className="bg-white rounded-xl border border-gray-100 p-8 shadow-sm">
                    <form onSubmit={submit} className="space-y-6">
                        
                        {/* Step 1: Client Information */}
                        {step === 1 && (
                            <div className="space-y-5 animate-in fade-in slide-in-from-right-4 duration-300">
                                <h2 className="text-lg font-semibold text-gray-900 border-b pb-2">Client Details</h2>
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <Label>Client Name *</Label>
                                        <Input required value={form.data.client.name} onChange={e => form.setData('client', { ...form.data.client, name: e.target.value })} placeholder="Acme Corp" />
                                    </div>
                                    <div>
                                        <Label>Company *</Label>
                                        <Input required value={form.data.client.company} onChange={e => form.setData('client', { ...form.data.client, company: e.target.value })} placeholder="Acme Inc." />
                                    </div>
                                    <div>
                                        <Label>Email *</Label>
                                        <Input type="email" required value={form.data.client.email} onChange={e => form.setData('client', { ...form.data.client, email: e.target.value })} placeholder="contact@acme.com" />
                                    </div>
                                    <div>
                                        <Label>Phone</Label>
                                        <Input value={form.data.client.phone} onChange={e => form.setData('client', { ...form.data.client, phone: e.target.value })} placeholder="+1 234 567 890" />
                                    </div>
                                    <div>
                                        <Label>Tier</Label>
                                        <select className={inputCls} value={form.data.client.tier} onChange={e => form.setData('client', { ...form.data.client, tier: e.target.value as any })}>
                                            <option value="enterprise">Enterprise</option>
                                            <option value="mid-market">Mid-Market</option>
                                            <option value="smb">SMB</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Step 2: SOW */}
                        {step === 2 && (
                            <div className="space-y-5 animate-in fade-in slide-in-from-right-4 duration-300">
                                <h2 className="text-lg font-semibold text-gray-900 border-b pb-2">Statement of Work</h2>
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="col-span-2">
                                        <Label>SOW Title *</Label>
                                        <Input required value={form.data.sow.title} onChange={e => form.setData('sow', { ...form.data.sow, title: e.target.value })} placeholder="e.g. Q3 Marketing Retainer" />
                                    </div>
                                    <div>
                                        <Label>Start Date</Label>
                                        <Input type="date" value={form.data.sow.start_date} onChange={e => form.setData('sow', { ...form.data.sow, start_date: e.target.value })} />
                                    </div>
                                    <div>
                                        <Label>End Date</Label>
                                        <Input type="date" value={form.data.sow.end_date} onChange={e => form.setData('sow', { ...form.data.sow, end_date: e.target.value })} />
                                    </div>
                                </div>
                                
                                <div className="mt-6">
                                    <div className="flex justify-between items-center mb-3">
                                        <Label className="mb-0">Deliverables</Label>
                                        <Button type="button" size="sm" variant="outline" onClick={addDeliverable}>
                                            <Plus size={14} className="mr-1" /> Add Deliverable
                                        </Button>
                                    </div>
                                    <div className="space-y-3">
                                        {form.data.sow.deliverables.map((d, i) => (
                                            <div key={i} className="flex gap-2 items-center bg-gray-50 p-2 rounded-lg border border-gray-200">
                                                <Input className="flex-1" placeholder="Deliverable Title" value={d.title} onChange={e => {
                                                    const nd = [...form.data.sow.deliverables]; nd[i].title = e.target.value; form.setData('sow', { ...form.data.sow, deliverables: nd });
                                                }} />
                                                <select className={cn(inputCls, 'w-32 py-2')} value={d.service_type} onChange={e => {
                                                    const nd = [...form.data.sow.deliverables]; nd[i].service_type = e.target.value; form.setData('sow', { ...form.data.sow, deliverables: nd });
                                                }}>
                                                    <option value="consulting">Consulting</option>
                                                    <option value="development">Development</option>
                                                    <option value="design">Design</option>
                                                    <option value="other">Other</option>
                                                </select>
                                                <select className={cn(inputCls, 'w-32 py-2')} value={d.frequency} onChange={e => {
                                                    const nd = [...form.data.sow.deliverables]; nd[i].frequency = e.target.value; form.setData('sow', { ...form.data.sow, deliverables: nd });
                                                }}>
                                                    <option value="one_time">One Time</option>
                                                    <option value="weekly">Weekly</option>
                                                    <option value="monthly">Monthly</option>
                                                </select>
                                                <Button type="button" variant="ghost" size="sm" onClick={() => removeDeliverable(i)} className="text-red-500 hover:text-red-600 hover:bg-red-50">
                                                    <Trash2 size={14} />
                                                </Button>
                                            </div>
                                        ))}
                                        {form.data.sow.deliverables.length === 0 && (
                                            <div className="text-center p-4 border border-dashed rounded text-sm text-gray-500">No deliverables added.</div>
                                        )}
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Step 3: Proposal */}
                        {step === 3 && (
                            <div className="space-y-5 animate-in fade-in slide-in-from-right-4 duration-300">
                                <h2 className="text-lg font-semibold text-gray-900 border-b pb-2">Proposal Details</h2>
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <Label>Proposal Title *</Label>
                                        <Input required value={form.data.proposal.title} onChange={e => form.setData('proposal', { ...form.data.proposal, title: e.target.value })} placeholder="e.g. Acme Website Redesign" />
                                    </div>
                                    <div>
                                        <Label>Valid Until</Label>
                                        <Input type="date" value={form.data.proposal.valid_until} onChange={e => form.setData('proposal', { ...form.data.proposal, valid_until: e.target.value })} />
                                    </div>
                                </div>

                                <div className="mt-6">
                                    <div className="flex justify-between items-center mb-3">
                                        <Label className="mb-0">Pricing & Line Items</Label>
                                        <Button type="button" size="sm" variant="outline" onClick={addLineItem}>
                                            <Plus size={14} className="mr-1" /> Add Line Item
                                        </Button>
                                    </div>
                                    <div className="space-y-3">
                                        {form.data.proposal.line_items.map((li, i) => (
                                            <div key={i} className="flex gap-2 items-center bg-gray-50 p-2 rounded-lg border border-gray-200">
                                                <Input className="flex-[2]" placeholder="Service Name" value={li.service} onChange={e => {
                                                    const nl = [...form.data.proposal.line_items]; nl[i].service = e.target.value; form.setData('proposal', { ...form.data.proposal, line_items: nl });
                                                }} />
                                                <Input type="number" className="flex-1" placeholder="Price" value={li.unit_price} onChange={e => {
                                                    const nl = [...form.data.proposal.line_items]; nl[i].unit_price = e.target.value; form.setData('proposal', { ...form.data.proposal, line_items: nl });
                                                }} />
                                                <Input type="number" className="w-20" placeholder="Qty" value={li.quantity} onChange={e => {
                                                    const nl = [...form.data.proposal.line_items]; nl[i].quantity = e.target.value; form.setData('proposal', { ...form.data.proposal, line_items: nl });
                                                }} />
                                                <select className={cn(inputCls, 'w-32 py-2')} value={li.frequency} onChange={e => {
                                                    const nl = [...form.data.proposal.line_items]; nl[i].frequency = e.target.value; form.setData('proposal', { ...form.data.proposal, line_items: nl });
                                                }}>
                                                    <option value="one_time">One Time</option>
                                                    <option value="monthly">Monthly</option>
                                                    <option value="annual">Annual</option>
                                                </select>
                                                <Button type="button" variant="ghost" size="sm" onClick={() => removeLineItem(i)} className="text-red-500 hover:text-red-600 hover:bg-red-50">
                                                    <Trash2 size={14} />
                                                </Button>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Step 4: Onboarding */}
                        {step === 4 && (
                            <div className="space-y-5 animate-in fade-in slide-in-from-right-4 duration-300">
                                <h2 className="text-lg font-semibold text-gray-900 border-b pb-2">Onboarding Setup</h2>
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <Label>Target Go-Live Date</Label>
                                        <Input type="date" value={form.data.onboarding.target_go_live} onChange={e => form.setData('onboarding', { ...form.data.onboarding, target_go_live: e.target.value })} />
                                    </div>
                                    <div className="col-span-2">
                                        <Label>Internal Setup Notes</Label>
                                        <TextArea rows={3} value={form.data.onboarding.notes} onChange={e => form.setData('onboarding', { ...form.data.onboarding, notes: e.target.value })} placeholder="Any specific notes for the team taking over this account..." />
                                    </div>
                                </div>

                                <div className="bg-indigo-50 text-indigo-800 p-4 rounded-lg mt-6 text-sm border border-indigo-100 flex gap-3">
                                    <div className="mt-0.5"><Check className="text-indigo-600" size={16}/></div>
                                    <div>
                                        <strong>Ready to complete onboarding!</strong><br/>
                                        Submitting this wizard will automatically create the Client record, generate the SOW, build the Proposal, and initialize the Onboarding Checklist for the team.
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Navigation */}
                        <div className="flex items-center justify-between pt-6 border-t border-gray-100 mt-8">
                            {step > 1 ? (
                                <Button type="button" variant="outline" onClick={prevStep}>
                                    <ChevronLeft size={16} className="mr-1" /> Back
                                </Button>
                            ) : (
                                <div></div>
                            )}

                            {step < 4 ? (
                                <Button type="button" className="bg-indigo-600 hover:bg-indigo-700" onClick={nextStep}>
                                    Next Step <ChevronRight size={16} className="ml-1" />
                                </Button>
                            ) : (
                                <Button type="submit" loading={form.processing} className="bg-green-600 hover:bg-green-700 text-white">
                                    Complete Onboarding <Save size={16} className="ml-2" />
                                </Button>
                            )}
                        </div>

                    </form>
                </div>
            </div>
        </AppLayout>
    );
}

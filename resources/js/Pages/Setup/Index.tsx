import React, { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { ChevronRight, ArrowRight, CheckCircle, Server, Plus, Trash2, Shield } from 'lucide-react';

interface Role {
    id: string;
    name: string;
    slug: string;
}

interface Props {
    organization: {
        name: string;
        timezone: string;
        currency: string;
    };
    roles: Role[];
}

export default function SetupIndex({ organization, roles }: Props) {
    const [step, setStep] = useState(1);

    const { data, setData, post, processing, errors } = useForm({
        organization: {
            name: organization.name || '',
            timezone: organization.timezone || 'UTC',
            currency: organization.currency || 'USD',
        },
        team: [] as { name: string; email: string; role_id: string }[],
        project: {
            name: '',
            budget: '',
        },
    });

    const nextStep = () => setStep((s) => Math.min(s + 1, 4));
    const prevStep = () => setStep((s) => Math.max(s - 1, 1));

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/setup');
    };

    const addTeamMember = () => {
        setData('team', [...data.team, { name: '', email: '', role_id: roles[0]?.id || '' }]);
    };

    const removeTeamMember = (index: number) => {
        const newTeam = [...data.team];
        newTeam.splice(index, 1);
        setData('team', newTeam);
    };

    const updateTeamMember = (index: number, field: string, value: string) => {
        const newTeam = [...data.team];
        newTeam[index] = { ...newTeam[index], [field]: value };
        setData('team', newTeam);
    };

    return (
        <div className="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
            <Head title="Organization Setup" />

            <div className="sm:mx-auto sm:w-full sm:max-w-3xl">
                <div className="text-center">
                    <Shield className="mx-auto h-12 w-12 text-indigo-600" />
                    <h2 className="mt-6 text-3xl font-extrabold text-gray-900">
                        Welcome to DigiCloudify
                    </h2>
                    <p className="mt-2 text-sm text-gray-600">
                        Let's get your organization set up so you can start collaborating.
                    </p>
                </div>

                {/* Progress Bar */}
                <div className="mt-8">
                    <div className="relative">
                        <div className="overflow-hidden h-2 mb-4 text-xs flex rounded bg-gray-200">
                            <div
                                style={{ width: `${(step / 4) * 100}%` }}
                                className="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-indigo-600 transition-all duration-500"
                            ></div>
                        </div>
                        <div className="flex justify-between text-xs font-medium text-gray-500 px-1">
                            <span className={step >= 1 ? 'text-indigo-600' : ''}>Organization</span>
                            <span className={step >= 2 ? 'text-indigo-600' : ''}>Team</span>
                            <span className={step >= 3 ? 'text-indigo-600' : ''}>First Project</span>
                            <span className={step >= 4 ? 'text-indigo-600' : ''}>Connect</span>
                        </div>
                    </div>
                </div>

                <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-3xl">
                    <div className="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
                        <form onSubmit={submit} className="space-y-6">
                            {/* STEP 1: Organization */}
                            {step === 1 && (
                                <div className="space-y-6 animate-in fade-in slide-in-from-bottom-4">
                                    <div>
                                        <h3 className="text-lg leading-6 font-medium text-gray-900">Organization Details</h3>
                                        <p className="mt-1 text-sm text-gray-500">
                                            Confirm your organization's basic information.
                                        </p>
                                    </div>

                                    <div className="space-y-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Organization Name</label>
                                            <input
                                                type="text"
                                                value={data.organization.name}
                                                onChange={(e) => setData('organization', { ...data.organization, name: e.target.value })}
                                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                required
                                            />
                                            {errors['organization.name'] && <p className="mt-1 text-sm text-red-600">{errors['organization.name']}</p>}
                                        </div>
                                        <div className="grid grid-cols-2 gap-4">
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700">Timezone</label>
                                                <input
                                                    type="text"
                                                    value={data.organization.timezone}
                                                    onChange={(e) => setData('organization', { ...data.organization, timezone: e.target.value })}
                                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                    required
                                                />
                                            </div>
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700">Currency</label>
                                                <input
                                                    type="text"
                                                    value={data.organization.currency}
                                                    onChange={(e) => setData('organization', { ...data.organization, currency: e.target.value })}
                                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                    required
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            )}

                            {/* STEP 2: Team */}
                            {step === 2 && (
                                <div className="space-y-6 animate-in fade-in slide-in-from-bottom-4">
                                    <div>
                                        <h3 className="text-lg leading-6 font-medium text-gray-900">Invite Your Team</h3>
                                        <p className="mt-1 text-sm text-gray-500">
                                            Bring your team members onboard right away. You can always do this later.
                                        </p>
                                    </div>

                                    <div className="space-y-4">
                                        {data.team.map((member, idx) => (
                                            <div key={idx} className="flex items-center gap-3 bg-gray-50 p-3 rounded-md">
                                                <input
                                                    type="text"
                                                    placeholder="Name"
                                                    value={member.name}
                                                    onChange={(e) => updateTeamMember(idx, 'name', e.target.value)}
                                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                />
                                                <input
                                                    type="email"
                                                    placeholder="Email"
                                                    value={member.email}
                                                    onChange={(e) => updateTeamMember(idx, 'email', e.target.value)}
                                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                />
                                                <select
                                                    value={member.role_id}
                                                    onChange={(e) => updateTeamMember(idx, 'role_id', e.target.value)}
                                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                >
                                                    {roles.map((r) => (
                                                        <option key={r.id} value={r.id}>{r.name}</option>
                                                    ))}
                                                </select>
                                                <button
                                                    type="button"
                                                    onClick={() => removeTeamMember(idx)}
                                                    className="p-2 text-gray-400 hover:text-red-500"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </button>
                                            </div>
                                        ))}
                                        
                                        <button
                                            type="button"
                                            onClick={addTeamMember}
                                            className="inline-flex items-center px-3 py-1.5 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                                        >
                                            <Plus className="h-4 w-4 mr-1.5" />
                                            Add Team Member
                                        </button>
                                    </div>
                                </div>
                            )}

                            {/* STEP 3: Project */}
                            {step === 3 && (
                                <div className="space-y-6 animate-in fade-in slide-in-from-bottom-4">
                                    <div>
                                        <h3 className="text-lg leading-6 font-medium text-gray-900">Create First Project</h3>
                                        <p className="mt-1 text-sm text-gray-500">
                                            Set up a project to organize your tasks and milestones.
                                        </p>
                                    </div>

                                    <div className="space-y-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Project Name (Optional)</label>
                                            <input
                                                type="text"
                                                placeholder="e.g. Q3 Website Redesign"
                                                value={data.project.name}
                                                onChange={(e) => setData('project', { ...data.project, name: e.target.value })}
                                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                            />
                                        </div>
                                        {data.project.name && (
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700">Budget (Optional)</label>
                                                <div className="mt-1 relative rounded-md shadow-sm">
                                                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                        <span className="text-gray-500 sm:text-sm">$</span>
                                                    </div>
                                                    <input
                                                        type="number"
                                                        value={data.project.budget}
                                                        onChange={(e) => setData('project', { ...data.project, budget: e.target.value })}
                                                        className="block w-full pl-7 rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                    />
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            )}

                            {/* STEP 4: MCP Provider */}
                            {step === 4 && (
                                <div className="space-y-6 animate-in fade-in slide-in-from-bottom-4">
                                    <div>
                                        <h3 className="text-lg leading-6 font-medium text-gray-900">Connect MCP Provider</h3>
                                        <p className="mt-1 text-sm text-gray-500">
                                            DigiCloudify connects with your favorite tools using the Model Context Protocol. You can connect tools later from settings.
                                        </p>
                                    </div>

                                    <div className="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
                                        <Server className="mx-auto h-8 w-8 text-gray-400" />
                                        <h4 className="mt-2 text-sm font-medium text-gray-900">Available in Settings</h4>
                                        <p className="mt-1 text-xs text-gray-500">
                                            Once setup is complete, head over to <strong>Settings &gt; MCP</strong> to connect critical integrations like Google, Meta, and Zoho for daily use.
                                        </p>
                                    </div>
                                </div>
                            )}

                            <div className="flex justify-between pt-6 border-t border-gray-200">
                                {step > 1 ? (
                                    <button
                                        type="button"
                                        onClick={prevStep}
                                        className="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                    >
                                        Back
                                    </button>
                                ) : (
                                    <div></div>
                                )}
                                
                                {step < 4 ? (
                                    <button
                                        type="button"
                                        onClick={nextStep}
                                        className="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                    >
                                        Next Step
                                        <ChevronRight className="ml-2 -mr-1 h-4 w-4" />
                                    </button>
                                ) : (
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                    >
                                        {processing ? 'Saving...' : 'Complete Setup'}
                                        <CheckCircle className="ml-2 -mr-1 h-4 w-4" />
                                    </button>
                                )}
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    );
}

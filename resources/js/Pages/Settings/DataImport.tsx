import React, { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Upload, Download, FileSpreadsheet, AlertCircle } from 'lucide-react';
import { Button } from '@/Components/ui/button';

export default function DataImport() {
    const [entityType, setEntityType] = useState('projects');

    const { data, setData, post, processing, errors, recentlySuccessful } = useForm({
        entity_type: 'projects',
        csv_file: null as File | null,
    });

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (e.target.files && e.target.files[0]) {
            setData('csv_file', e.target.files[0]);
        }
    };

    const handleTypeChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        const type = e.target.value;
        setEntityType(type);
        setData('entity_type', type);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('web.settings.import.upload'));
    };

    return (
        <AppLayout title="Data Import">
            <Head title="Data Import" />

            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h2 className="text-lg font-bold text-gray-900">Data Import</h2>
                    <p className="text-sm text-gray-500 mt-1">
                        Migrate data from other tools (e.g., Jira, Trello, FreshBooks) via CSV.
                    </p>
                </div>
            </div>

            <div className="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm max-w-3xl">
                <div className="p-6">
                    <form onSubmit={submit} className="space-y-6">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                1. Select Entity Type
                            </label>
                            <select
                                value={entityType}
                                onChange={handleTypeChange}
                                className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            >
                                <option value="projects">Projects</option>
                                <option value="tasks">Tasks</option>
                                <option value="clients">Clients</option>
                            </select>
                            {errors.entity_type && (
                                <p className="mt-1 text-sm text-red-600">{errors.entity_type}</p>
                            )}
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                2. Download Template
                            </label>
                            <p className="text-sm text-gray-500 mb-2">
                                Download the CSV template for the selected entity to ensure your columns are mapped correctly.
                            </p>
                            <a
                                href={`/settings/import/template?entity_type=${entityType}`}
                                className="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                            >
                                <Download className="h-4 w-4 mr-2" />
                                Download {entityType.charAt(0).toUpperCase() + entityType.slice(1)} Template
                            </a>
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                3. Upload Filled CSV
                            </label>
                            <div className="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                                <div className="space-y-1 text-center">
                                    <FileSpreadsheet className="mx-auto h-12 w-12 text-gray-400" />
                                    <div className="flex text-sm text-gray-600 justify-center">
                                        <label
                                            htmlFor="file-upload"
                                            className="relative cursor-pointer bg-white rounded-md font-medium text-indigo-600 hover:text-indigo-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-indigo-500"
                                        >
                                            <span>Upload a file</span>
                                            <input
                                                id="file-upload"
                                                name="file-upload"
                                                type="file"
                                                className="sr-only"
                                                accept=".csv"
                                                onChange={handleFileChange}
                                            />
                                        </label>
                                        <p className="pl-1">or drag and drop</p>
                                    </div>
                                    <p className="text-xs text-gray-500">CSV up to 5MB</p>
                                </div>
                            </div>
                            {data.csv_file && (
                                <p className="mt-2 text-sm text-gray-600">
                                    Selected file: <span className="font-medium text-gray-900">{data.csv_file.name}</span>
                                </p>
                            )}
                            {errors.csv_file && (
                                <p className="mt-1 text-sm text-red-600">{errors.csv_file}</p>
                            )}
                        </div>

                        <div className="flex items-center justify-end border-t border-gray-200 pt-6">
                            <Button
                                type="submit"
                                disabled={processing || !data.csv_file}
                                className="inline-flex items-center"
                            >
                                {processing ? 'Importing...' : 'Start Import'}
                                <Upload className="ml-2 h-4 w-4" />
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}

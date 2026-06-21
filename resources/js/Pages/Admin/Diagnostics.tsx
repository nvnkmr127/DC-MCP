import React from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';
import { 
  Activity, 
  ServerCrash, 
  Database,
  ArrowUpRight,
  MonitorPlay,
  AlertTriangle
} from 'lucide-react';

interface ErrorLog {
    id: number;
    provider: string;
    connection_name: string;
    error_message: string;
    created_at: string;
}

interface Props {
    queues: {
        default: number;
        high: number;
        low: number;
    };
    activeSyncs: number;
    recentErrors: ErrorLog[];
}

export default function Diagnostics({ queues, activeSyncs, recentErrors }: Props) {
    return (
        <AppLayout title="System Diagnostics">
            <Head title="System Diagnostics | Admin" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                    
                    {/* Header Section */}
                    <div className="flex justify-between items-center bg-white/5 backdrop-blur-xl border border-white/10 p-6 rounded-2xl shadow-xl">
                        <div>
                            <h2 className="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-blue-400 to-indigo-500">
                                System Diagnostics
                            </h2>
                            <p className="mt-1 text-sm text-gray-400">
                                Real-time observability for queues, sync workers, and system errors.
                            </p>
                        </div>
                        <div className="flex gap-4">
                            <a 
                                href="/horizon" 
                                target="_blank"
                                className="inline-flex items-center px-4 py-2 bg-gradient-to-r from-purple-600 to-indigo-600 border border-transparent rounded-xl font-semibold text-xs text-white uppercase tracking-widest hover:from-purple-500 hover:to-indigo-500 transition-all duration-200 shadow-lg shadow-indigo-500/30"
                            >
                                <Database className="w-4 h-4 mr-2" />
                                Laravel Horizon
                                <ArrowUpRight className="w-3 h-3 ml-2" />
                            </a>
                            <a 
                                href="/pulse" 
                                target="_blank"
                                className="inline-flex items-center px-4 py-2 bg-gradient-to-r from-emerald-600 to-teal-600 border border-transparent rounded-xl font-semibold text-xs text-white uppercase tracking-widest hover:from-emerald-500 hover:to-teal-500 transition-all duration-200 shadow-lg shadow-teal-500/30"
                            >
                                <Activity className="w-4 h-4 mr-2" />
                                Laravel Pulse
                                <ArrowUpRight className="w-3 h-3 ml-2" />
                            </a>
                        </div>
                    </div>

                    {/* Quick Stats Metrics */}
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm relative overflow-hidden group">
                            <div className="absolute inset-0 bg-gradient-to-br from-blue-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                            <div className="flex items-center justify-between">
                                <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400">Active Sync Workers</h3>
                                <MonitorPlay className="w-5 h-5 text-blue-500" />
                            </div>
                            <p className="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{activeSyncs}</p>
                            <p className="mt-1 text-xs text-green-500">Currently processing</p>
                        </div>

                        <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm relative overflow-hidden group">
                            <div className="absolute inset-0 bg-gradient-to-br from-indigo-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                            <div className="flex items-center justify-between">
                                <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400">High Queue Depth</h3>
                                <Activity className="w-5 h-5 text-indigo-500" />
                            </div>
                            <p className="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{queues.high}</p>
                            <p className="mt-1 text-xs text-gray-500">Jobs pending</p>
                        </div>

                        <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm relative overflow-hidden group">
                            <div className="absolute inset-0 bg-gradient-to-br from-gray-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                            <div className="flex items-center justify-between">
                                <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400">Default Queue Depth</h3>
                                <Activity className="w-5 h-5 text-gray-500" />
                            </div>
                            <p className="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{queues.default}</p>
                            <p className="mt-1 text-xs text-gray-500">Jobs pending</p>
                        </div>

                        <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm relative overflow-hidden group">
                            <div className="absolute inset-0 bg-gradient-to-br from-rose-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                            <div className="flex items-center justify-between">
                                <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400">Low Queue Depth</h3>
                                <Activity className="w-5 h-5 text-rose-400" />
                            </div>
                            <p className="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{queues.low}</p>
                            <p className="mt-1 text-xs text-gray-500">Jobs pending</p>
                        </div>
                    </div>

                    {/* Recent Errors Table */}
                    <div className="bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-700 rounded-2xl shadow-sm overflow-hidden">
                        <div className="px-6 py-5 border-b border-gray-100 dark:border-slate-700 flex items-center gap-3">
                            <div className="p-2 bg-red-500/10 rounded-lg">
                                <ServerCrash className="w-5 h-5 text-red-500" />
                            </div>
                            <h3 className="text-lg font-semibold text-gray-900 dark:text-white">Recent Sync Failures</h3>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
                                <thead className="bg-gray-50 dark:bg-slate-900/50">
                                    <tr>
                                        <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Provider</th>
                                        <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Connection</th>
                                        <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Error Details</th>
                                        <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-slate-700">
                                    {recentErrors.length === 0 ? (
                                        <tr>
                                            <td colSpan={4} className="px-6 py-12 text-center">
                                                <div className="flex flex-col items-center justify-center text-gray-400">
                                                    <AlertTriangle className="w-8 h-8 mb-3 text-emerald-500" />
                                                    <p className="text-emerald-500 font-medium">No recent errors detected</p>
                                                    <p className="text-sm mt-1">The system is operating normally.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    ) : (
                                        recentErrors.map((error) => (
                                            <tr key={error.id} className="hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors">
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <span className="px-2.5 py-1 text-xs font-medium bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300 rounded-md">
                                                        {error.provider}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                                    {error.connection_name}
                                                </td>
                                                <td className="px-6 py-4 text-sm text-red-500 max-w-xl truncate font-mono bg-red-50/50 dark:bg-red-500/5">
                                                    {error.error_message}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {error.created_at}
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

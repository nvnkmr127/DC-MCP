import React from 'react';
import { Button } from '@/Components/ui/Button';
import { Breadcrumbs } from '@/Components/Shared/Breadcrumbs';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Trash2, RotateCcw, AlertCircle, FolderKanban, CheckSquare } from 'lucide-react';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/Components/ui/Table";

interface TrashItem {
    id: string;
    title: string;
    type: 'task' | 'project';
    deleted_at: string;
}

interface Props {
    items: TrashItem[];
    retentionDays: number;
}

export default function Trash({ items, retentionDays }: Props) {
    const handleRestore = (item: TrashItem) => {
        router.post(`/settings/trash/${item.type}/${item.id}/restore`);
    };

    return (
        <AppLayout title="Trash & Recovery">
            <Head title="Trash" />
            <div className="mb-6">
                <Breadcrumbs items={[
                    { label: 'Settings', href: '/settings' },
                    { label: 'Trash' }
                ]} />
            </div>

            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h2 className="text-lg font-bold text-gray-900">Trash Bin</h2>
                    <p className="text-sm text-gray-500 mt-1">Recover recently deleted items. Items are permanently deleted after {retentionDays} days.</p>
                </div>
            </div>

            <div className="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
                {items.length === 0 ? (
                    <div className="p-16 text-center flex flex-col items-center">
                        <div className="w-12 h-12 rounded-full bg-gray-50 flex items-center justify-center mb-3">
                            <Trash2 size={24} className="text-gray-300" />
                        </div>
                        <p className="text-sm font-medium text-gray-900">Your trash is empty</p>
                        <p className="text-sm text-gray-500 mt-1">Deleted tasks and projects will appear here.</p>
                    </div>
                ) : (
                    <Table className="w-full text-sm">
                        <TableHeader>
                            <TableRow className="bg-gray-50 border-b border-gray-200">
                                <TableHead className="px-4 py-3 text-left font-semibold text-gray-900">Item</TableHead>
                                <TableHead className="px-4 py-3 text-left font-semibold text-gray-900">Type</TableHead>
                                <TableHead className="px-4 py-3 text-left font-semibold text-gray-900">Deleted</TableHead>
                                <TableHead className="px-4 py-3 text-right font-semibold text-gray-900">Action</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody className="divide-y divide-gray-100">
                            {items.map((item) => (
                                <TableRow key={`${item.type}-${item.id}`} className="hover:bg-gray-50/50 transition-colors">
                                    <TableCell className="px-4 py-3">
                                        <div className="font-medium text-gray-900">{item.title}</div>
                                        <div className="text-xs text-gray-500 font-mono mt-0.5">{item.id.substring(0, 8)}</div>
                                    </TableCell>
                                    <TableCell className="px-4 py-3">
                                        <div className="flex items-center gap-1.5 text-gray-600 capitalize">
                                            {item.type === 'task' ? <CheckSquare size={16} className="text-indigo-500" /> : <FolderKanban size={16} className="text-emerald-500" />}
                                            {item.type}
                                        </div>
                                    </TableCell>
                                    <TableCell className="px-4 py-3 text-gray-500">
                                        {item.deleted_at}
                                    </TableCell>
                                    <TableCell className="px-4 py-3 text-right">
                                        <Button
                                            onClick={() => handleRestore(item)}
                                            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition-colors"
                                        >
                                            <RotateCcw size={16} /> Restore
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </div>
        </AppLayout>
    );
}

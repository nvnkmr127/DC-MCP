import React, { useState } from "react"
import { Head, router, useForm } from "@inertiajs/react"
import AppLayout from "@/Layouts/AppLayout"
import { DataTable } from "@/Components/ui/DataTable"
import { ColumnDef } from "@tanstack/react-table"
import { Badge } from "@/Components/ui/Badge"
import { Button } from "@/Components/ui/Button"
import { Shield, X, Check } from "lucide-react"

type Role = {
  id: string
  name: string
  description: string
  is_system: boolean
  permissions: Record<string, string[]> | null
  users_count: number
}

const RESOURCES = [
  { key: 'tasks', label: 'Tasks' },
  { key: 'projects', label: 'Projects' },
  { key: 'invoices', label: 'Invoices' },
  { key: 'clients', label: 'Clients' },
  { key: 'users', label: 'Team Members' },
  { key: 'settings', label: 'Workspace Settings' },
];

const ACTIONS = ['view', 'create', 'update', 'delete'];

function Modal({ isOpen, onClose, title, children, maxWidth = "max-w-lg" }: { isOpen: boolean, onClose: () => void, title: string, children: React.ReactNode, maxWidth?: string }) {
    if (!isOpen) return null;
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" onClick={onClose} />
            <div className={`relative bg-white rounded-xl shadow-2xl w-full ${maxWidth} flex flex-col max-h-[90vh]`}>
                <div className="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                    <h3 className="font-semibold text-slate-800">{title}</h3>
                    <button onClick={onClose} className="p-1 text-slate-400 hover:text-slate-600 rounded-lg">
                        <X size={18} />
                    </button>
                </div>
                <div className="p-5 overflow-y-auto">
                    {children}
                </div>
            </div>
        </div>
    );
}

export default function Roles({ roles }: { roles: Role[] }) {
    const [createModalOpen, setCreateModalOpen] = useState(false);
    const [editRole, setEditRole] = useState<Role | null>(null);

    const createForm = useForm({
        name: '',
        description: '',
    });

    const editForm = useForm({
        permissions: {} as Record<string, string[]>,
    });

    function submitCreate(e: React.FormEvent) {
        e.preventDefault();
        createForm.post('/settings/roles', {
            onSuccess: () => {
                setCreateModalOpen(false);
                createForm.reset();
            }
        });
    }

    function openEditModal(role: Role) {
        setEditRole(role);
        editForm.setData('permissions', role.permissions || {});
    }

    function submitEdit(e: React.FormEvent) {
        e.preventDefault();
        if (!editRole) return;
        editForm.patch(`/settings/roles/${editRole.id}`, {
            onSuccess: () => setEditRole(null)
        });
    }

    function togglePermission(resource: string, action: string) {
        const currentPerms = editForm.data.permissions || {};
        const resourcePerms = currentPerms[resource] || [];
        
        let newResourcePerms;
        if (resourcePerms.includes(action)) {
            newResourcePerms = resourcePerms.filter(a => a !== action);
        } else {
            newResourcePerms = [...resourcePerms, action];
        }

        editForm.setData('permissions', {
            ...currentPerms,
            [resource]: newResourcePerms
        });
    }

    const columns: ColumnDef<Role>[] = [
      {
        accessorKey: "name",
        header: "Role Name",
        cell: ({ row }) => (
          <div className="flex items-center gap-2">
            <span className="font-semibold text-slate-700 capitalize">{row.original.name.replace('_', ' ')}</span>
            {row.original.is_system && (
                <span className="bg-indigo-50 text-indigo-700 text-[10px] font-bold px-2 py-0.5 rounded uppercase">System</span>
            )}
          </div>
        ),
      },
      {
        accessorKey: "description",
        header: "Description",
        cell: ({ row }) => <span className="text-slate-500 text-sm">{row.original.description || '—'}</span>
      },
      {
        accessorKey: "users_count",
        header: "Active Users",
        cell: ({ row }) => (
          <Badge variant="gray">{row.original.users_count} users</Badge>
        ),
      },
      {
        id: "actions",
        header: "",
        cell: ({ row }) => (
          <div className="flex justify-end gap-2">
            <Button variant="outline" size="sm" onClick={() => openEditModal(row.original)}>
              Edit Permissions
            </Button>
          </div>
        ),
      },
    ];

    return (
        <AppLayout title="Roles & Permissions">
          <Head title="Roles & Permissions" />
            <div className="mb-6">
                <Breadcrumbs items={[
                    { label: 'Settings', href: '/settings' },
                    { label: 'Roles & Permissions' }
                ]} />
            </div>

          <div className="max-w-7xl mx-auto py-8 sm:px-6 lg:px-8">
            <div className="mb-8 flex items-center justify-between">
              <div>
                <h2 className="text-2xl font-bold text-slate-900 flex items-center gap-2">
                  <Shield className="text-indigo-600" /> Roles & Permissions
                </h2>
                <p className="mt-1 text-sm text-slate-500">
                  Manage system roles and configure fine-grained permissions for your workspace.
                </p>
              </div>
              <Button onClick={() => setCreateModalOpen(true)}>Create Custom Role</Button>
            </div>

            <div className="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
              <DataTable 
                columns={columns} 
                data={roles} 
                searchKey="name" 
              />
            </div>
          </div>

          {/* CREATE ROLE MODAL */}
          <Modal isOpen={createModalOpen} onClose={() => setCreateModalOpen(false)} title="Create Custom Role">
              <form onSubmit={submitCreate} className="space-y-4">
                  <div>
                      <label className="block text-sm font-medium text-slate-700 mb-1">Role Name</label>
                      <input 
                          type="text" 
                          required
                          value={createForm.data.name}
                          onChange={e => createForm.setData('name', e.target.value)}
                          className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                          placeholder="e.g. Guest Contractor"
                      />
                  </div>
                  <div>
                      <label className="block text-sm font-medium text-slate-700 mb-1">Description (Optional)</label>
                      <textarea 
                          value={createForm.data.description}
                          onChange={e => createForm.setData('description', e.target.value)}
                          className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none resize-none"
                          rows={3}
                          placeholder="What is this role responsible for?"
                      />
                  </div>
                  <div className="flex justify-end gap-3 pt-4 border-t border-slate-100">
                      <Button type="button" variant="outline" onClick={() => setCreateModalOpen(false)}>Cancel</Button>
                      <Button type="submit" disabled={createForm.processing}>Create Role</Button>
                  </div>
              </form>
          </Modal>

          {/* EDIT PERMISSIONS MODAL */}
          <Modal isOpen={!!editRole} onClose={() => setEditRole(null)} title={`Edit Permissions: ${editRole?.name}`} maxWidth="max-w-4xl">
              <form onSubmit={submitEdit} className="space-y-6">
                  {editRole?.is_system && (
                      <div className="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-lg text-sm mb-4">
                          <strong>Note:</strong> You are editing a system-defined role. Modifying core permissions may affect standard workflows.
                      </div>
                  )}

                  <div className="overflow-x-auto border border-slate-200 rounded-lg">
                      <table className="w-full text-sm text-left">
                          <thead className="bg-slate-50 text-slate-600 font-semibold border-b border-slate-200">
                              <tr>
                                  <th className="px-4 py-3">Resource</th>
                                  {ACTIONS.map(action => (
                                      <th key={action} className="px-4 py-3 text-center capitalize">{action}</th>
                                  ))}
                              </tr>
                          </thead>
                          <tbody className="divide-y divide-slate-100">
                              {RESOURCES.map(resource => {
                                  const resPerms = editForm.data.permissions?.[resource.key] || [];
                                  return (
                                      <tr key={resource.key} className="hover:bg-slate-50/50">
                                          <td className="px-4 py-3 font-medium text-slate-700">{resource.label}</td>
                                          {ACTIONS.map(action => {
                                              const isChecked = resPerms.includes(action) || resPerms.includes('*');
                                              return (
                                                  <td key={action} className="px-4 py-3 text-center">
                                                      <label className="inline-flex items-center justify-center cursor-pointer">
                                                          <input 
                                                              type="checkbox" 
                                                              className="sr-only peer"
                                                              checked={isChecked}
                                                              onChange={() => togglePermission(resource.key, action)}
                                                          />
                                                          <div className={`w-5 h-5 rounded border flex items-center justify-center transition-colors ${isChecked ? 'bg-indigo-600 border-indigo-600 text-white' : 'border-slate-300 bg-white hover:border-indigo-400'}`}>
                                                              {isChecked && <Check size={14} strokeWidth={3} />}
                                                          </div>
                                                      </label>
                                                  </td>
                                              );
                                          })}
                                      </tr>
                                  );
                              })}
                          </tbody>
                      </table>
                  </div>

                  <div className="flex justify-end gap-3 pt-4 border-t border-slate-100">
                      <Button type="button" variant="outline" onClick={() => setEditRole(null)}>Cancel</Button>
                      <Button type="submit" disabled={editForm.processing}>Save Permissions</Button>
                  </div>
              </form>
          </Modal>

        </AppLayout>
    )
}

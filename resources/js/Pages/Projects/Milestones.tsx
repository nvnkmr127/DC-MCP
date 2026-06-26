import React, { useState } from "react"
import { Head, router } from "@inertiajs/react"
import AppLayout from "@/Layouts/AppLayout"
import { DataTable } from "@/Components/ui/DataTable"
import { ColumnDef } from "@tanstack/react-table"
import { Button } from "@/Components/ui/Button"
import { Badge } from "@/Components/ui/Badge"
import { EmptyState } from "@/Components/Shared/EmptyState"
import { Flag, Target } from "lucide-react"
import Modal from "@/Components/ui/Modal"

type Goal = {
  id: string
  title: string
}

type Milestone = {
  id: string
  title: string
  description?: string
  due_date?: string
  status: string
  goal_id?: string
  goal?: Goal
}

export default function Milestones({ project, milestones, goals }: { project: any, milestones: Milestone[], goals: Goal[] }) {
  const [isModalOpen, setIsModalOpen] = useState(false)
  const [editingMilestone, setEditingMilestone] = useState<Milestone | null>(null)
  
  const [form, setForm] = useState({
    name: '',
    description: '',
    due_date: '',
    status: 'pending',
    goal_id: ''
  })

  const openAddModal = () => {
    setEditingMilestone(null)
    setForm({ name: '', description: '', due_date: '', status: 'pending', goal_id: '' })
    setIsModalOpen(true)
  }

  const openEditModal = (milestone: any) => {
    setEditingMilestone(milestone)
    setForm({
      name: milestone.name,
      description: milestone.description || '',
      due_date: milestone.due_date ? milestone.due_date.split('T')[0] : '',
      status: milestone.status,
      goal_id: milestone.goal_id || ''
    })
    setIsModalOpen(true)
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    
    if (editingMilestone) {
      router.put(`/projects/${project.id}/milestones/${editingMilestone.id}`, form, {
        onSuccess: () => setIsModalOpen(false)
      })
    } else {
      router.post(`/projects/${project.id}/milestones`, form, {
        onSuccess: () => setIsModalOpen(false)
      })
    }
  }

  const columns: ColumnDef<any>[] = [
    {
      accessorKey: "name",
      header: "Milestone",
      cell: ({ row }) => (
        <div className="flex flex-col">
          <span className="font-medium text-slate-900 dark:text-slate-100">{row.original.name}</span>
          {row.original.description && (
            <span className="text-sm text-slate-500 line-clamp-1">{row.original.description}</span>
          )}
        </div>
      ),
    },
    {
      accessorKey: "goal",
      header: "Linked OKR Goal",
      cell: ({ row }) => (
        row.original.goal ? (
          <Badge variant="outline" className="flex items-center gap-1.5 w-max">
            <Target className="w-3 h-3 text-indigo-500" />
            {row.original.goal.title}
          </Badge>
        ) : (
          <span className="text-sm text-slate-400 italic">None</span>
        )
      ),
    },
    {
      accessorKey: "due_date",
      header: "Due Date",
      cell: ({ row }) => (
        <span className="text-sm text-slate-600">
          {row.original.due_date ? new Date(row.original.due_date).toLocaleDateString() : '-'}
        </span>
      )
    },
    {
      accessorKey: "status",
      header: "Status",
      cell: ({ row }) => {
        const s = row.original.status
        let color = "bg-slate-100 text-slate-800"
        if (s === 'in_progress') color = "bg-blue-100 text-blue-800"
        if (s === 'completed') color = "bg-green-100 text-green-800"
        
        return (
          <span className={`px-2 py-1 text-xs rounded-full font-medium ${color}`}>
            {s.replace('_', ' ').toUpperCase()}
          </span>
        )
      },
    },
    {
      id: "actions",
      header: "",
      cell: ({ row }) => (
        <div className="flex justify-end space-x-2">
          <Button variant="outline" size="sm" onClick={() => openEditModal(row.original)}>Edit</Button>
        </div>
      ),
    },
  ]

  return (
    <AppLayout title={`${project.name} - Milestones`}>
      <Head title="Milestones" />

      <div className="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
        <div className="mb-8 flex items-center justify-between">
          <div>
            <h2 className="text-2xl font-bold text-slate-900 dark:text-white">
              Project Milestones
            </h2>
            <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
              Track major deliverables and phases for {project.name}.
            </p>
          </div>
          <Button onClick={openAddModal}>Add Milestone</Button>
        </div>

        {milestones.length > 0 ? (
          <div className="bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
            <DataTable 
              columns={columns} 
              data={milestones} 
              searchKey="name" 
            />
          </div>
        ) : (
          <EmptyState
            icon={Flag}
            title="No milestones yet"
            description="Create your first milestone to start tracking major phases of this project."
            actionLabel="Create Milestone"
            onAction={openAddModal}
          />
        )}
      </div>

      <Modal show={isModalOpen} onClose={() => setIsModalOpen(false)} maxWidth="md">
        <div className="p-6 bg-white dark:bg-slate-900">
          <h2 className="text-lg font-bold text-slate-900 dark:text-white mb-4">
            {editingMilestone ? 'Edit Milestone' : 'Add Milestone'}
          </h2>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Name</label>
              <input 
                value={form.name} 
                onChange={e => setForm({...form, name: e.target.value})} 
                className="w-full border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" 
                required 
              />
            </div>
            
            <div>
              <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Description</label>
              <textarea 
                value={form.description} 
                onChange={e => setForm({...form, description: e.target.value})} 
                className="w-full border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" 
                rows={3} 
              />
            </div>
            
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Due Date</label>
                <input 
                  type="date"
                  value={form.due_date} 
                  onChange={e => setForm({...form, due_date: e.target.value})} 
                  className="w-full border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" 
                />
              </div>
              
              <div>
                <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Status</label>
                <select 
                  value={form.status} 
                  onChange={e => setForm({...form, status: e.target.value})} 
                  className="w-full border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                >
                  <option value="pending">Pending</option>
                  <option value="in_progress">In Progress</option>
                  <option value="completed">Completed</option>
                </select>
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                Link to OKR Goal (Optional)
              </label>
              <select 
                value={form.goal_id} 
                onChange={e => setForm({...form, goal_id: e.target.value})} 
                className="w-full border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
              >
                <option value="">-- No Goal --</option>
                {goals?.map(g => (
                  <option key={g.id} value={g.id}>{g.title}</option>
                ))}
              </select>
              <p className="mt-1 text-xs text-slate-500">
                Connect this milestone to a high-level organizational goal to track alignment.
              </p>
            </div>
            
            <div className="flex justify-end gap-3 mt-6">
              <Button type="button" variant="outline" onClick={() => setIsModalOpen(false)}>Cancel</Button>
              <Button type="submit">{editingMilestone ? 'Save Changes' : 'Create Milestone'}</Button>
            </div>
          </form>
        </div>
      </Modal>
    </AppLayout>
  )
}

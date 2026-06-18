import React from "react"
import { Head } from "@inertiajs/react"
import AppLayout from "@/Layouts/AppLayout"
import { DataTable } from "@/Components/ui/DataTable"
import { ColumnDef } from "@tanstack/react-table"
import { Button } from "@/Components/ui/Button"
import { Badge } from "@/Components/ui/Badge"
import { EmptyState } from "@/Components/Shared/EmptyState"
import { Flag } from "lucide-react"

type Milestone = {
  id: string
  title: string
  description?: string
  due_date?: string
  status: string
}

const columns: ColumnDef<Milestone>[] = [
  {
    accessorKey: "title",
    header: "Milestone",
    cell: ({ row }) => (
      <div className="flex flex-col">
        <span className="font-medium text-slate-900 dark:text-slate-100">{row.original.title}</span>
        {row.original.description && (
          <span className="text-sm text-slate-500 line-clamp-1">{row.original.description}</span>
        )}
      </div>
    ),
  },
  {
    accessorKey: "due_date",
    header: "Due Date",
  },
  {
    accessorKey: "status",
    header: "Status",
    cell: ({ row }) => (
      <Badge variant={row.original.status === 'completed' ? 'default' : 'secondary'}>
        {row.original.status.replace('_', ' ')}
      </Badge>
    ),
  },
  {
    id: "actions",
    header: "",
    cell: ({ row }) => (
      <div className="flex justify-end space-x-2">
        <Button variant="outline" size="sm">Edit</Button>
      </div>
    ),
  },
]

export default function Milestones({ project, milestones }: { project: any, milestones: Milestone[] }) {
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
          <Button>Add Milestone</Button>
        </div>

        {milestones.length > 0 ? (
          <div className="bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
            <DataTable 
              columns={columns} 
              data={milestones} 
              searchKey="title" 
            />
          </div>
        ) : (
          <EmptyState
            icon={Flag}
            title="No milestones yet"
            description="Create your first milestone to start tracking major phases of this project."
            actionLabel="Create Milestone"
            onAction={() => console.log('Open create milestone modal')}
          />
        )}
      </div>
    </AppLayout>
  )
}

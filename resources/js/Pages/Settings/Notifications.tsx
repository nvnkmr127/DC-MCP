import React from "react"
import { Head, useForm } from "@inertiajs/react"
import AppLayout from "@/Layouts/AppLayout"
import { Switch } from "@/Components/ui/Switch"
import { Button } from "@/Components/ui/Button"

export default function Notifications({ auth }: { auth: any }) {
  const { data, setData, patch, processing, recentlySuccessful } = useForm({
    preferences: auth.user.notification_preferences || {
      email_tasks: true,
      push_tasks: false,
      email_projects: true,
      push_projects: true,
    },
  })

  const handleToggle = (key: string) => {
    setData("preferences", { ...data.preferences, [key]: !data.preferences[key] })
  }

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    patch(route("web.settings.notifications.update"))
  }

  return (
    <AppLayout title="Notification Settings">
      <Head title="Notifications" />
            <div className="mb-6">
                <Breadcrumbs items={[
                    { label: 'Settings', href: '/settings' },
                    { label: 'Notifications' }
                ]} />
            </div>

      <div className="max-w-4xl mx-auto py-10 sm:px-6 lg:px-8">
        <div className="md:grid md:grid-cols-3 md:gap-6">
          <div className="md:col-span-1">
            <div className="px-4 sm:px-0">
              <h3 className="text-lg font-medium text-slate-900 dark:text-slate-100">
                Notification Preferences
              </h3>
              <p className="mt-1 text-sm text-slate-600 dark:text-slate-400">
                Decide which communications you'd like to receive and how.
              </p>
            </div>
          </div>

          <div className="mt-5 md:mt-0 md:col-span-2">
            <form onSubmit={submit}>
              <div className="shadow sm:rounded-md sm:overflow-hidden dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                <div className="px-4 py-5 space-y-6 sm:p-6">
                  
                  <div className="flex items-center justify-between">
                    <div>
                      <h4 className="text-sm font-medium text-slate-900 dark:text-slate-100">Email: Task Assignments</h4>
                      <p className="text-sm text-slate-500 dark:text-slate-400">Receive an email when a task is assigned to you.</p>
                    </div>
                    <Switch 
                      checked={data.preferences.email_tasks} 
                      onCheckedChange={() => handleToggle('email_tasks')}
                    />
                  </div>

                  <div className="flex items-center justify-between">
                    <div>
                      <h4 className="text-sm font-medium text-slate-900 dark:text-slate-100">Push: Task Assignments</h4>
                      <p className="text-sm text-slate-500 dark:text-slate-400">Receive an in-app push notification for tasks.</p>
                    </div>
                    <Switch 
                      checked={data.preferences.push_tasks} 
                      onCheckedChange={() => handleToggle('push_tasks')}
                    />
                  </div>

                  <div className="flex items-center justify-between">
                    <div>
                      <h4 className="text-sm font-medium text-slate-900 dark:text-slate-100">Email: Project Updates</h4>
                      <p className="text-sm text-slate-500 dark:text-slate-400">Receive emails for major project milestones.</p>
                    </div>
                    <Switch 
                      checked={data.preferences.email_projects} 
                      onCheckedChange={() => handleToggle('email_projects')}
                    />
                  </div>

                  <div className="flex items-center justify-between">
                    <div>
                      <h4 className="text-sm font-medium text-slate-900 dark:text-slate-100">Push: Project Updates</h4>
                      <p className="text-sm text-slate-500 dark:text-slate-400">Receive in-app notifications for project updates.</p>
                    </div>
                    <Switch 
                      checked={data.preferences.push_projects} 
                      onCheckedChange={() => handleToggle('push_projects')}
                    />
                  </div>

                </div>
                <div className="px-4 py-3 bg-slate-50 dark:bg-slate-900/50 text-right sm:px-6 border-t border-slate-200 dark:border-slate-800 flex items-center justify-end">
                  {recentlySuccessful && <span className="text-sm text-green-600 mr-3">Saved.</span>}
                  <Button type="submit" disabled={processing}>
                    Save Preferences
                  </Button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
    </AppLayout>
  )
}

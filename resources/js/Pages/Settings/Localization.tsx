import React from "react"
import { Head, useForm } from "@inertiajs/react"
import AppLayout from "@/Layouts/AppLayout"
import { Button } from "@/Components/ui/Button"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/Components/ui/Select"

export default function Localization({ auth }: { auth: any }) {
  const { data, setData, post, processing, recentlySuccessful } = useForm({
    timezone: auth.user.timezone || "UTC",
    date_format: auth.user.date_format || "Y-m-d",
    currency: auth.user.currency || "USD",
  })

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    post(route("web.settings.profile.update"))
  }

  return (
    <AppLayout title="Localization Settings">
      <Head title="Localization" />

      <div className="max-w-4xl mx-auto py-10 sm:px-6 lg:px-8">
        <div className="md:grid md:grid-cols-3 md:gap-6">
          <div className="md:col-span-1">
            <div className="px-4 sm:px-0">
              <h3 className="text-lg font-medium text-slate-900 dark:text-slate-100">
                Localization Preferences
              </h3>
              <p className="mt-1 text-sm text-slate-600 dark:text-slate-400">
                Manage your timezone, date formatting, and preferred currency.
              </p>
            </div>
          </div>

          <div className="mt-5 md:mt-0 md:col-span-2">
            <form onSubmit={submit}>
              <div className="shadow sm:rounded-md sm:overflow-hidden dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                <div className="px-4 py-5 space-y-6 sm:p-6">
                  
                  <div className="grid grid-cols-1 gap-6">
                    <div>
                      <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                        Timezone
                      </label>
                      <Select 
                        value={data.timezone} 
                        onValueChange={(value) => setData("timezone", value)}
                      >
                        <SelectTrigger className="w-full">
                          <SelectValue placeholder="Select timezone" />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="UTC">UTC (Coordinated Universal Time)</SelectItem>
                          <SelectItem value="America/New_York">America/New_York (EST/EDT)</SelectItem>
                          <SelectItem value="America/Los_Angeles">America/Los_Angeles (PST/PDT)</SelectItem>
                          <SelectItem value="Europe/London">Europe/London (GMT/BST)</SelectItem>
                          <SelectItem value="Asia/Tokyo">Asia/Tokyo (JST)</SelectItem>
                          <SelectItem value="Australia/Sydney">Australia/Sydney (AEST/AEDT)</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>

                    <div>
                      <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                        Date Format
                      </label>
                      <Select 
                        value={data.date_format} 
                        onValueChange={(value) => setData("date_format", value)}
                      >
                        <SelectTrigger className="w-full">
                          <SelectValue placeholder="Select date format" />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="Y-m-d">YYYY-MM-DD (e.g., 2026-12-31)</SelectItem>
                          <SelectItem value="d/m/Y">DD/MM/YYYY (e.g., 31/12/2026)</SelectItem>
                          <SelectItem value="m/d/Y">MM/DD/YYYY (e.g., 12/31/2026)</SelectItem>
                          <SelectItem value="M j, Y">MMM D, YYYY (e.g., Dec 31, 2026)</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>

                    <div>
                      <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                        Currency
                      </label>
                      <Select 
                        value={data.currency} 
                        onValueChange={(value) => setData("currency", value)}
                      >
                        <SelectTrigger className="w-full">
                          <SelectValue placeholder="Select currency" />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="USD">USD ($)</SelectItem>
                          <SelectItem value="EUR">EUR (€)</SelectItem>
                          <SelectItem value="GBP">GBP (£)</SelectItem>
                          <SelectItem value="JPY">JPY (¥)</SelectItem>
                          <SelectItem value="AUD">AUD ($)</SelectItem>
                          <SelectItem value="CAD">CAD ($)</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>

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

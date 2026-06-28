import React, { useState } from "react"
import { Head, useForm } from "@inertiajs/react"
import { Breadcrumbs } from "@/Components/Shared/Breadcrumbs";
declare const route: any;
import AppLayout from "@/Layouts/AppLayout"
import { Button } from "@/Components/ui/Button"
import { Input } from "@/Components/ui/Input"

interface Props {
  enabled: boolean
  qrCodeSvg: string | null
  recoveryCodes: string[] | null
}

export default function TwoFactor({ enabled, qrCodeSvg, recoveryCodes }: Props) {
  const [showConfirm, setShowConfirm] = useState(false)
  const [showDisable, setShowDisable] = useState(false)

  const enableForm = useForm()
  const confirmForm = useForm({ code: "" })
  const disableForm = useForm({ password: "" })

  const handleEnable = () => {
    enableForm.post(route("web.settings.two-factor.enable"), {
      preserveScroll: true,
      onSuccess: () => setShowConfirm(true),
    })
  }

  const handleConfirm = (e: React.FormEvent) => {
    e.preventDefault()
    confirmForm.post(route("web.settings.two-factor.confirm"), {
      preserveScroll: true,
      onSuccess: () => {
        setShowConfirm(false)
        confirmForm.reset()
      },
    })
  }

  const handleDisable = (e: React.FormEvent) => {
    e.preventDefault()
    disableForm.post(route("web.settings.two-factor.disable"), {
      preserveScroll: true,
      onSuccess: () => {
        setShowDisable(false)
        disableForm.reset()
      },
    })
  }

  return (
    <AppLayout title="Two-Factor Authentication">
      <Head title="Two-Factor Authentication" />
            <div className="mb-6">
                <Breadcrumbs items={[
                    { label: 'Settings', href: '/settings' },
                    { label: 'Two-Factor Authentication' }
                ]} />
            </div>

      <div className="max-w-4xl mx-auto py-10 sm:px-6 lg:px-8">
        <div className="md:grid md:grid-cols-3 md:gap-6">
          <div className="md:col-span-1">
            <div className="px-4 sm:px-0">
              <h3 className="text-lg font-medium text-slate-900 dark:text-slate-100">
                Two-Factor Authentication
              </h3>
              <p className="mt-1 text-sm text-slate-600 dark:text-slate-400">
                Add additional security to your account using two-factor authentication.
              </p>
            </div>
          </div>

          <div className="mt-5 md:mt-0 md:col-span-2">
            <div className="bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
              <h3 className="text-lg font-medium text-slate-900 dark:text-white">
                {enabled ? "You have enabled two-factor authentication." : 
                 qrCodeSvg ? "Finish enabling two-factor authentication." : 
                 "You have not enabled two-factor authentication."}
              </h3>

              <div className="mt-3 text-sm text-slate-600 dark:text-slate-400">
                <p>
                  When two-factor authentication is enabled, you will be prompted for a secure, random token during authentication. You may retrieve this token from your phone's Google Authenticator application.
                </p>
              </div>

              {/* Step 1: Show Enable Button if not enabled and no QR yet */}
              {!enabled && !qrCodeSvg && (
                <div className="mt-5">
                  <Button onClick={handleEnable} disabled={enableForm.processing}>
                    Enable
                  </Button>
                </div>
              )}

              {/* Step 2: Show QR Code & Recovery Codes for confirmation */}
              {!enabled && qrCodeSvg && (
                <div className="mt-6 border-t border-slate-200 dark:border-slate-800 pt-6">
                  <p className="text-sm font-semibold text-slate-900 dark:text-white mb-4">
                    To finish enabling two-factor authentication, scan the following QR code using your phone's authenticator application and provide the generated OTP code.
                  </p>

                  <div className="mt-4 p-2 inline-block bg-white border rounded-lg shadow-sm" dangerouslySetInnerHTML={{ __html: qrCodeSvg }} />

                  {recoveryCodes && recoveryCodes.length > 0 && (
                    <div className="mt-6">
                      <p className="text-sm font-semibold text-slate-900 dark:text-white mb-2">Store these recovery codes in a secure password manager. They can be used to recover access to your account if your two-factor authentication device is lost.</p>
                      <div className="bg-slate-50 dark:bg-slate-950 rounded-lg p-4 font-mono text-sm">
                        {recoveryCodes.map(code => (
                          <div key={code}>{code}</div>
                        ))}
                      </div>
                    </div>
                  )}

                  <form onSubmit={handleConfirm} className="mt-6 flex flex-col items-start gap-4">
                    <div>
                      <Input
                        id="code"
                        placeholder="Code"
                        value={confirmForm.data.code}
                        onChange={(e) => confirmForm.setData("code", e.target.value)}
                        autoFocus
                      />
                      {confirmForm.errors.code && (
                        <p className="mt-2 text-sm text-red-600">{confirmForm.errors.code}</p>
                      )}
                    </div>
                    <Button type="submit" disabled={confirmForm.processing}>
                      Confirm
                    </Button>
                  </form>
                </div>
              )}

              {/* Step 3: Show Disable Option when enabled */}
              {enabled && (
                <div className="mt-5">
                  {!showDisable ? (
                    <Button variant="destructive" onClick={() => setShowDisable(true)}>
                      Disable Two-Factor
                    </Button>
                  ) : (
                    <form onSubmit={handleDisable} className="flex flex-col items-start gap-4 mt-4 p-4 border border-red-200 bg-red-50 dark:bg-red-950/20 rounded-lg">
                      <p className="text-sm text-red-800 dark:text-red-400 font-medium">Please enter your password to disable 2FA.</p>
                      <div>
                        <Input
                          type="password"
                          placeholder="Password"
                          value={disableForm.data.password}
                          onChange={(e) => disableForm.setData("password", e.target.value)}
                        />
                        {disableForm.errors.password && (
                          <p className="mt-2 text-sm text-red-600">{disableForm.errors.password}</p>
                        )}
                      </div>
                      <div className="flex gap-2">
                        <Button type="button" variant="outline" onClick={() => { setShowDisable(false); disableForm.reset(); }}>Cancel</Button>
                        <Button type="submit" variant="destructive" disabled={disableForm.processing}>Confirm Disable</Button>
                      </div>
                    </form>
                  )}
                </div>
              )}

            </div>
          </div>
        </div>
      </div>
    </AppLayout>
  )
}

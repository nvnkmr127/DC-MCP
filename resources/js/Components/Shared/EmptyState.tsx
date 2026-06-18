import React from "react"
import { LucideIcon } from "lucide-react"
import { Button } from "../ui/Button"
import { cn } from "@/lib/utils"

interface EmptyStateProps {
  icon: LucideIcon
  title: string
  description?: string
  actionLabel?: string
  onAction?: () => void
  className?: string
}

export function EmptyState({
  icon: Icon,
  title,
  description,
  actionLabel,
  onAction,
  className,
}: EmptyStateProps) {
  return (
    <div
      className={cn(
        "flex min-h-[400px] flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50 p-8 text-center animate-in fade-in-50 dark:border-slate-800 dark:bg-slate-900/50",
        className
      )}
    >
      <div className="flex h-20 w-20 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800">
        <Icon className="h-10 w-10 text-slate-500 dark:text-slate-400" />
      </div>
      <h3 className="mt-6 text-xl font-semibold text-slate-900 dark:text-slate-50">
        {title}
      </h3>
      {description && (
        <p className="mt-2 max-w-sm text-center text-sm text-slate-500 dark:text-slate-400">
          {description}
        </p>
      )}
      {actionLabel && onAction && (
        <div className="mt-6">
          <Button onClick={onAction}>
            {actionLabel}
          </Button>
        </div>
      )}
    </div>
  )
}

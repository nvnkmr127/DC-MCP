import React, { createContext, useCallback, useContext, useMemo, useRef, useState } from 'react';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/Components/ui/Dialog';
import { Button } from '@/Components/ui/Button';

type ConfirmVariant = 'default' | 'destructive';

export type ConfirmOptions = {
    title: string;
    description?: string;
    confirmText?: string;
    cancelText?: string;
    variant?: ConfirmVariant;
    onConfirm?: () => Promise<void> | void;
};

type ConfirmFn = (opts: ConfirmOptions) => Promise<boolean>;

const ConfirmContext = createContext<ConfirmFn | null>(null);

export function ConfirmProvider({ children }: { children: React.ReactNode }) {
    const resolverRef = useRef<((value: boolean) => void) | null>(null);

    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [options, setOptions] = useState<ConfirmOptions | null>(null);

    const close = useCallback(() => {
        setOpen(false);
        setLoading(false);
        setError(null);
        setOptions(null);
    }, []);

    const confirm: ConfirmFn = useCallback(async (opts: ConfirmOptions) => {
        setOptions({
            confirmText: 'Confirm',
            cancelText: 'Cancel',
            variant: 'default',
            ...opts,
        });
        setError(null);
        setLoading(false);
        setOpen(true);

        return new Promise<boolean>((resolve) => {
            resolverRef.current = resolve;
        });
    }, []);

    const ctxValue = useMemo(() => confirm, [confirm]);

    const title = options?.title ?? '';
    const description = options?.description;
    const confirmText = options?.confirmText ?? 'Confirm';
    const cancelText = options?.cancelText ?? 'Cancel';
    const variant: ConfirmVariant = options?.variant ?? 'default';

    return (
        <ConfirmContext.Provider value={ctxValue}>
            {children}
            <Dialog
                open={open}
                onOpenChange={(next) => {
                    if (loading) return;
                    if (!next) {
                        resolverRef.current?.(false);
                        resolverRef.current = null;
                        close();
                    }
                }}
            >
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        {description ? <DialogDescription>{description}</DialogDescription> : null}
                    </DialogHeader>

                    {error ? (
                        <div className="mb-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700">
                            {error}
                        </div>
                    ) : null}

                    <div className="flex items-center justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={loading}
                            onClick={() => {
                                resolverRef.current?.(false);
                                resolverRef.current = null;
                                close();
                            }}
                        >
                            {cancelText}
                        </Button>
                        <Button
                            type="button"
                            variant={variant === 'destructive' ? 'destructive' : 'default'}
                            loading={loading}
                            onClick={async () => {
                                try {
                                    setLoading(true);
                                    setError(null);
                                    await options?.onConfirm?.();
                                    resolverRef.current?.(true);
                                    resolverRef.current = null;
                                    close();
                                } catch (e: any) {
                                    const msg =
                                        typeof e === 'string'
                                            ? e
                                            : e?.message
                                                ? String(e.message)
                                                : 'Action failed. Please try again.';
                                    setError(msg);
                                    setLoading(false);
                                }
                            }}
                        >
                            {confirmText}
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>
        </ConfirmContext.Provider>
    );
}

export function useConfirm(): ConfirmFn {
    const ctx = useContext(ConfirmContext);
    if (!ctx) {
        throw new Error('useConfirm must be used within ConfirmProvider');
    }
    return ctx;
}


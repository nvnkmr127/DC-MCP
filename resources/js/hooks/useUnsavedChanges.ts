import { useEffect } from 'react';
import { router } from '@inertiajs/react';

export function useUnsavedChanges(isDirty: boolean, message: string = 'You have unsaved changes. Are you sure you want to leave?') {
    useEffect(() => {
        if (!isDirty) return;

        // Prevent browser tab closing or refreshing
        const handleBeforeUnload = (e: BeforeUnloadEvent) => {
            e.preventDefault();
            e.returnValue = message;
            return message;
        };

        window.addEventListener('beforeunload', handleBeforeUnload);

        // Prevent Inertia internal navigation
        const unsubscribe = router.on('before', (event) => {
            // Only intercept GET requests so that we don't block actual form submissions (POST/PUT/DELETE)
            if (event.detail.visit.method === 'get') {
                if (!window.confirm(message)) {
                    event.preventDefault();
                }
            }
        });

        return () => {
            window.removeEventListener('beforeunload', handleBeforeUnload);
            unsubscribe();
        };
    }, [isDirty, message]);
}

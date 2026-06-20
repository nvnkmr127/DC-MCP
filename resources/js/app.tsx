import './bootstrap';
import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { Toaster } from '@/Components/ui/Toaster';

const queryClient = new QueryClient({
    defaultOptions: {
        queries: { staleTime: 30_000, retry: 1 },
    },
});

const appName = (window as any).__APP_NAME__ ?? 'Digicloudify';

declare global {
    interface Window {
        unreadNotificationCount?: number;
    }
}

createInertiaApp({
    title: (title) => {
        const prefix = window.unreadNotificationCount && window.unreadNotificationCount > 0 
            ? `(${window.unreadNotificationCount}) ` 
            : '';
        return `${prefix}${title ? `${title} — ${appName}` : appName}`;
    },
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob('./Pages/**/*.tsx'),
        ) as any,
    setup({ el, App, props }) {
        const root = createRoot(el);
        root.render(
            <QueryClientProvider client={queryClient}>
                <App {...props} />
                <Toaster position="top-right" richColors closeButton />
            </QueryClientProvider>,
        );
    },
    progress: {
        color: '#6366f1',
        showSpinner: true,
    },
});

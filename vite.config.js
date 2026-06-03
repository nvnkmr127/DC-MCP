import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: ['resources/views/**'],
        }),
        react(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
    build: {
        // Never ship source maps to production — they expose the full component tree and business logic.
        // Enable locally with VITE_SOURCEMAP=true if needed for debugging.
        sourcemap: process.env.VITE_SOURCEMAP === 'true',
        chunkSizeWarningLimit: 1000,
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});

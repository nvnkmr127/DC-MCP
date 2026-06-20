import axios from 'axios';
import { toast } from 'sonner';

axios.defaults.withCredentials = true;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Global error handling for API requests
axios.interceptors.response.use(
    (response) => response,
    (error) => {
        const status = error.response?.status;
        const data = error.response?.data;
        
        // Skip handling for 422 Validation Errors, let forms handle them
        if (status !== 422) {
            if (status === 401) {
                toast.error('Session expired. Please log in again.');
            } else if (status === 403) {
                toast.error('You do not have permission to perform this action.');
            } else if (status === 500) {
                toast.error('A server error occurred. Please try again later.');
            } else {
                toast.error(data?.message || 'An unexpected error occurred.');
            }
        }
        
        return Promise.reject(error);
    }
);

(window as any).axios = axios;

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

(window as any).Pusher = Pusher;

(window as any).Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});

import '../css/app.css';
import '@shopify/polaris/build/esm/styles.css';

import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { initializeTheme } from './hooks/use-appearance';
import { AppProvider } from '@shopify/polaris';
import enTranslations from '@shopify/polaris/locales/en.json';
import axios from 'axios';
import Swal from 'sweetalert2';

// Make axios available globally for Inertia
window.axios = axios;

// Fallback: If the backend drops the X-Inertia header but returns a valid Inertia JSON payload,
// we intercept the response and manually restore the header to prevent the Inertia modal error.
window.axios.interceptors.response.use((response) => {
    if (response.data && typeof response.data === 'object' && response.data.component && response.data.props) {
        response.headers['x-inertia'] = 'true';
    }
    return response;
});

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.jsx`, import.meta.glob('./pages/**/*.jsx')),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <AppProvider i18n={enTranslations}>
                <App {...props} />
            </AppProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// Global Inertia Progress & Error Handling
router.on('start', (event) => {
    // Only show loading modal for data mutation requests (POST, PUT, DELETE)
    if (event.detail.visit.method !== 'get') {
        Swal.fire({
            title: 'Processing...',
            text: 'Please wait while we process your request.',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
    }
});

router.on('success', (event) => {
    if (event.detail.visit.method !== 'get') {
        Swal.close();
        
        // Handle flash messages globally
        const flash = event.detail.page.props.flash || {};
        if (flash.success) {
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: flash.success,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
            });
        } else if (flash.error) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: flash.error,
            });
        }
    }
});

router.on('error', (errors) => {
    // Show validation or backend errors
    const firstError = Object.values(errors || {})[0] || 'An unexpected error occurred. Please try again.';
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: firstError,
    });
});

router.on('finish', (event) => {
    // Failsafe to close loading spinner if it wasn't caught by success/error
    if (event.detail.visit.method !== 'get' && Swal.isVisible() && Swal.isLoading()) {
        Swal.close();
    }
});

// This will set light / dark mode on load...
initializeTheme();

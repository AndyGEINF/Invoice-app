import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';

import AppLayout from '@/layouts/app-layout';

const appName = import.meta.env.VITE_APP_NAME || 'INVOICE';

createInertiaApp({
    // El plugin @inertiajs/vite resuelve las páginas de ./pages (resources/js/pages).
    pages: './pages',
    // Todas las páginas comparten la estructura con cabecera, navegación y avisos.
    layout: () => AppLayout,
    title: (title) => (title ? `${title} · ${appName}` : appName),
    strictMode: true,
    progress: {
        color: '#2563eb',
    },
});

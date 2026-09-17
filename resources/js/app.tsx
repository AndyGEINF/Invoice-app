import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';

const appName = import.meta.env.VITE_APP_NAME || 'INVOICE';

createInertiaApp({
    // El plugin @inertiajs/vite resuelve las páginas de ./pages (resources/js/pages).
    pages: './pages',
    title: (title) => (title ? `${title} · ${appName}` : appName),
    strictMode: true,
    progress: {
        color: '#2563eb',
    },
});

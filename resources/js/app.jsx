import './bootstrap';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    // A title already naming the app (the landing page's SEO title) is used as it is.
    title: (title) => (title ? (title.includes(appName) ? title : `${title} — ${appName}`) : appName),
    resolve: (name) => {
        // Lazy (not eager): each page becomes its own chunk, so heavy
        // page-specific dependencies (e.g. html5-qrcode, only used by
        // Staff/Dashboard) aren't bundled into every other page's load.
        const pages = import.meta.glob('./Pages/**/*.jsx');
        return pages[`./Pages/${name}.jsx`]();
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
});

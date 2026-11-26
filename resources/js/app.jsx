// SPDX-License-Identifier: Apache-2.0
import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { translator } from './i18n';

createInertiaApp({
    title: (title) => (title ? `${title} · ODAC` : translator(document.documentElement.lang).t('meta.title_default')),
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true });
        return pages[`./Pages/${name}.jsx`];
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: { color: '#2dd4bf' },
});

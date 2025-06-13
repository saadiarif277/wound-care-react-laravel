import '../css/app.css';
import axios from 'axios';
import { getCsrfToken, setupCsrfTokenRefresh } from '@/utils/csrf';

import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { AIOverlay, FloatingAIButton } from './Components/GhostAiUi';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Set global CSRF header for all axios (and Inertia) requests
axios.defaults.headers.common['X-CSRF-TOKEN'] = getCsrfToken();
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Keep token fresh for long sessions
setupCsrfTokenRefresh();

createInertiaApp({
  title: title => `${title} - ${appName}`,
  resolve: name =>
    resolvePageComponent(
      `./Pages/${name}.tsx`,
      import.meta.glob('./Pages/**/*.tsx')
    ),
  setup({ el, App, props }) {
    const root = createRoot(el);
    root.render(<App {...props} />);
  },
  progress: {
    color: '#4B5563'
  }
});

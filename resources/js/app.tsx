import '../css/app.css';
import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { setupAxios } from './lib/axios-setup';
import { initializeSanctum } from './lib/sanctum';
import { ThemeProvider } from './contexts/ThemeContext';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Initialize Sanctum for API authentication
initializeSanctum().catch(console.error);

// Set up axios for API calls
setupAxios();

createInertiaApp({
  title: title => `${title} - ${appName}`,
  resolve: name =>
    resolvePageComponent(
      `./Pages/${name}.tsx`,
      import.meta.glob('./Pages/**/*.tsx')
    ),
  setup({ el, App, props }) {
    const root = createRoot(el);
    root.render(
      <ThemeProvider>
        <App {...props} />
      </ThemeProvider>
    );
  },
  progress: {
    color: '#4B5563'
  }
});

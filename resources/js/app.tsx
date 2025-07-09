import '../css/app.css';
import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { setupAxios } from './lib/axios-setup';
import { ThemeProvider } from './contexts/ThemeContext';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Set up axios for API calls
setupAxios();

// Global root storage for HMR compatibility
const rootMap = new Map();

createInertiaApp({
  title: title => `${title} - ${appName}`,
  resolve: name =>
    resolvePageComponent(
      `./Pages/${name}.tsx`,
      import.meta.glob('./Pages/**/*.tsx')
    ),
  setup({ el, App, props }) {
    // Create root only once per container, reuse during HMR
    let root = rootMap.get(el);
    if (!root) {
      root = createRoot(el);
      rootMap.set(el, root);
    }
    
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

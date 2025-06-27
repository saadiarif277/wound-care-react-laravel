import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [laravel(['resources/js/app.tsx', 'resources/css/app.css']), react()],
  define: {
    'process.env': {},
    'process': {
      env: {}
    }
  },
  optimizeDeps: {
    include: ['@superinterface/react', '@tanstack/react-query', '@radix-ui/themes']
  }
});

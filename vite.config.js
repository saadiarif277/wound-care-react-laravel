import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/js/app.tsx', 'resources/css/app.css'],
      refresh: true,
      detectTls: 'msc-wound-portal.test', // Tell Laravel plugin about your domain
    }),
    react()
  ],
  server: {
    https: false, // Explicitly disable HTTPS for Vite
    host: 'localhost',
    port: 5173,
    strictPort: true,
    headers: {
      'X-Content-Type-Options': 'nosniff',
      'X-Frame-Options': 'SAMEORIGIN',
      'X-XSS-Protection': '1; mode=block',
      'Cache-Control': 'no-cache, no-store, must-revalidate',
      'Pragma': 'no-cache',
      'Expires': '0'
    },
    hmr: {
      protocol: 'ws', // Use WebSocket instead of WSS
      host: 'localhost',
      port: 5173,
    },
    cors: {
      origin: ['https://msc-wound-portal.test', 'http://localhost:5173'],
      credentials: true
    }
  }
});

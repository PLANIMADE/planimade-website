import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

/**
 * Das Dashboard wird als eigenständige Single-Page-App gebaut und landet
 * später im Ordner `/admin/` auf dem Webspace.
 */
export default defineConfig({
  base: '/admin/',
  plugins: [react(), tailwindcss()],
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    target: 'es2022',
  },
  server: {
    port: 5173,
    proxy: {
      '/api': { target: 'http://127.0.0.1:8787', changeOrigin: true },
      '/uploads': { target: 'http://127.0.0.1:8787', changeOrigin: true },
    },
  },
});

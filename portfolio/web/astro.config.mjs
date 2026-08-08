// @ts-check
import { defineConfig } from 'astro/config';
import tailwindcss from '@tailwindcss/vite';

/**
 * Statischer Build – das Ergebnis in `dist/` wird per FTP auf all-inkl geladen.
 * Inhalte kommen zur Laufzeit aus der PHP-API, damit Änderungen im Dashboard
 * sofort live sind und kein neuer Build nötig ist.
 */
export default defineConfig({
  site: process.env.SITE_URL || 'https://dominic-majewski.de',
  output: 'static',
  build: { format: 'directory', inlineStylesheets: 'auto' },
  devToolbar: { enabled: false },
  vite: {
    plugins: [tailwindcss()],
    build: {
      // Kurze, stabile Dateinamen – angenehmer beim FTP-Abgleich.
      assetsInlineLimit: 2048,
    },
    server: {
      // Im Dev-Betrieb zeigt /api und /uploads auf den lokalen PHP-Server,
      // damit im Browser alles gleiche Herkunft (und damit Cookie-fähig) ist.
      proxy: {
        '/api': { target: 'http://127.0.0.1:8787', changeOrigin: true },
        '/uploads': { target: 'http://127.0.0.1:8787', changeOrigin: true },
      },
    },
  },
});

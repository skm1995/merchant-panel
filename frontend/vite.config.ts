/// <reference types="vitest/config" />
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';

/**
 * In development Caddy serves the app on one origin: /api/ goes to PHP-FPM and everything
 * else to this dev server, so the session cookie and CSRF protection work without CORS.
 */
export default defineConfig({
  plugins: [react()],
  server: { port: 5173, strictPort: true },
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./src/test/setup.ts'],
    include: ['src/**/*.test.{ts,tsx}'],
    css: true,
    // bin/ci fails below these minimums; they trail the measured coverage by about two
    // points, so raise them when coverage grows. The HTML report lands in coverage/.
    coverage: {
      provider: 'v8',
      include: ['src/**/*.{ts,tsx}'],
      exclude: ['src/**/*.test.{ts,tsx}', 'src/test/**', 'src/api/schema.ts'],
      reporter: ['text-summary', 'html', 'json-summary'],
      thresholds: { lines: 94, statements: 94, functions: 98, branches: 81 },
    },
  },
});

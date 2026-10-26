/// <reference types="vitest/config" />
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';

const API_PREFIX = '/api';
const API_PROXY_TARGET_VARIABLE = 'API_PROXY_TARGET';
const TEST_MODE = 'test';

/**
 * The development server forwards API calls to the PHP backend, so the browser sees one
 * origin and the session cookie and CSRF protection work without CORS.
 */
function apiProxyTarget(): string {
  const target = process.env[API_PROXY_TARGET_VARIABLE];

  if (target === undefined || target.length === 0) {
    throw new Error(`Required environment variable "${API_PROXY_TARGET_VARIABLE}" is not set.`);
  }

  return target;
}

export default defineConfig(({ command, mode }) => ({
  plugins: [react()],
  server:
    command === 'serve' && mode !== TEST_MODE
      ? { port: 5173, strictPort: true, proxy: { [API_PREFIX]: apiProxyTarget() } }
      : {},
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./src/test/setup.ts'],
    css: true,
  },
}));

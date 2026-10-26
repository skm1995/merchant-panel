import { defineConfig, devices } from '@playwright/test';

const BASE_URL_VARIABLE = 'E2E_BASE_URL';

function baseUrl(): string {
  const value = process.env[BASE_URL_VARIABLE];

  if (value === undefined || value.length === 0) {
    throw new Error(`Required environment variable "${BASE_URL_VARIABLE}" is not set.`);
  }

  return value;
}

/**
 * End-to-end tests drive a real browser against the test stack behind Caddy: the built
 * React app, the PHP API, the worker, and MailHog.
 */
export default defineConfig({
  testDir: './e2e',
  fullyParallel: true,
  forbidOnly: true,
  retries: 0,
  reporter: 'list',
  use: {
    baseURL: baseUrl(),
    trace: 'retain-on-failure',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});

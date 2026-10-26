import { expect, type APIRequestContext, type Page } from '@playwright/test';

const MAILHOG_URL_VARIABLE = 'E2E_MAILHOG_URL';
const PASSWORD = 'e2e-merchant-password';
const TOKEN_PATTERN = /confirm\?token=([a-f0-9]{64})/;
const MAIL_TIMEOUT_MILLISECONDS = 15000;

interface MailhogMessage {
  ID: string;
  Content: { Body: string };
}

function mailhogUrl(): string {
  const value = process.env[MAILHOG_URL_VARIABLE];

  if (value === undefined || value.length === 0) {
    throw new Error(`Required environment variable "${MAILHOG_URL_VARIABLE}" is not set.`);
  }

  return value;
}

export function uniqueEmail(prefix: string): string {
  return `${prefix}-${crypto.randomUUID()}@example.com`;
}

export { PASSWORD };

/**
 * Waits for the confirmation email sent through the outbox, worker, and MailHog, deletes it,
 * and returns the confirmation link path with its token.
 */
export async function confirmationLink(request: APIRequestContext, email: string): Promise<string> {
  let token: string | null = null;

  await expect
    .poll(
      async () => {
        const response = await request.get(`${mailhogUrl()}/api/v2/search`, {
          params: { kind: 'to', query: email },
        });
        const { items } = (await response.json()) as { items: MailhogMessage[] };
        const message = items[0];

        if (message === undefined) {
          return null;
        }

        const body = message.Content.Body.replace(/=\r?\n/g, '').replace(/=3D/g, '=');
        token = TOKEN_PATTERN.exec(body)?.[1] ?? null;
        await request.delete(`${mailhogUrl()}/api/v1/messages/${message.ID}`);

        return token;
      },
      { timeout: MAIL_TIMEOUT_MILLISECONDS },
    )
    .not.toBeNull();

  return `/confirm?token=${String(token)}`;
}

export async function signIn(page: Page, email: string): Promise<void> {
  await page.goto('/login');
  await page.getByLabel('Email').fill(email);
  await page.getByLabel('Password').fill(PASSWORD);
  await page.getByRole('button', { name: 'Sign in' }).click();
  await expect(page.getByText(`Signed in as ${email}`)).toBeVisible();
}

/**
 * Registers and confirms a merchant through the API, as a shortcut for tests that start
 * signed in.
 */
export async function createMerchant(request: APIRequestContext, email: string): Promise<void> {
  const session = (await (await request.get('/api/v1/session')).json()) as { csrf_token: string };
  const headers = { 'X-CSRF-Token': session.csrf_token };

  expect(
    (
      await request.post('/api/v1/registrations', { headers, data: { email, password: PASSWORD } })
    ).status(),
  ).toBe(202);
  const token = new URL(await confirmationLink(request, email), 'http://e2e').searchParams.get(
    'token',
  );
  expect(
    (await request.post('/api/v1/email-confirmations', { headers, data: { token } })).status(),
  ).toBe(204);
}

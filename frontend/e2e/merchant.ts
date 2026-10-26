import { expect, type APIRequestContext, type Page } from '@playwright/test';

const MAILPIT_URL_VARIABLE = 'E2E_MAILPIT_URL';
const PASSWORD = 'e2e-merchant-password';
const TOKEN_PATTERN = /confirm\?token=([a-f0-9]{64})/;
const MAIL_TIMEOUT_MILLISECONDS = 15000;

interface MailpitSearch {
  messages: { ID: string }[];
}

interface MailpitMessage {
  Text: string;
}

function mailpitUrl(): string {
  const value = process.env[MAILPIT_URL_VARIABLE];

  if (value === undefined || value.length === 0) {
    throw new Error(`Required environment variable "${MAILPIT_URL_VARIABLE}" is not set.`);
  }

  return value;
}

export function uniqueEmail(prefix: string): string {
  return `${prefix}-${crypto.randomUUID()}@example.com`;
}

export { PASSWORD };

/**
 * Waits for the confirmation email sent through the outbox, worker, and Mailpit, deletes it,
 * and returns the confirmation link path with its token.
 */
export async function confirmationLink(request: APIRequestContext, email: string): Promise<string> {
  let token: string | null = null;

  await expect
    .poll(
      async () => {
        const search = await request.get(`${mailpitUrl()}/api/v1/search`, {
          params: { query: `to:"${email}"` },
        });
        const message = ((await search.json()) as MailpitSearch).messages[0];

        if (message === undefined) {
          return null;
        }

        const { Text } = (await (
          await request.get(`${mailpitUrl()}/api/v1/message/${message.ID}`)
        ).json()) as MailpitMessage;
        token = TOKEN_PATTERN.exec(Text)?.[1] ?? null;
        await request.delete(`${mailpitUrl()}/api/v1/messages`, { data: { IDs: [message.ID] } });

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

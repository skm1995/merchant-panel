import { expect, type APIRequestContext, type Page } from '@playwright/test';
import { requiredVariable } from './environment';

const MAILPIT_URL_VARIABLE = 'E2E_MAILPIT_URL';
const MERCHANT_EMAIL_VARIABLE = 'DEMO_MERCHANT_EMAIL';
const MERCHANT_PASSWORD_VARIABLE = 'DEMO_MERCHANT_PASSWORD';
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
  return requiredVariable(MAILPIT_URL_VARIABLE);
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

export interface Credentials {
  email: string;
  password: string;
}

/** The shared demo merchant account, which bin/ci recreates before the end-to-end tests run. */
export function demoMerchant(): Credentials {
  return {
    email: requiredVariable(MERCHANT_EMAIL_VARIABLE),
    password: requiredVariable(MERCHANT_PASSWORD_VARIABLE),
  };
}

export async function signIn(page: Page, { email, password }: Credentials): Promise<void> {
  await page.goto('/login');
  await page.getByLabel('Email').fill(email);
  await page.getByLabel('Password').fill(password);
  await page.getByRole('button', { name: 'Sign in' }).click();
  await expect(page).not.toHaveURL(/\/login$/);
}

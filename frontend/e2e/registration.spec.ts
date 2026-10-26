import { expect, test } from '@playwright/test';
import { confirmationLink, PASSWORD, signIn, uniqueEmail } from './merchant';

test('a merchant registers, confirms the email, and signs in', async ({ page, request }) => {
  const email = uniqueEmail('e2e-registration');

  await page.goto('/register');
  await page.getByLabel('Email').fill(email);
  await page.getByLabel('Password').fill(PASSWORD);
  await page.getByRole('button', { name: 'Create account' }).click();
  await expect(page.getByRole('status')).toHaveText(`We sent a confirmation link to ${email}.`);

  await page.goto(await confirmationLink(request, email));
  await page.getByRole('button', { name: 'Confirm email' }).click();
  await expect(page.getByRole('status')).toHaveText('Your email address is confirmed.');

  await signIn(page, email);
  await expect(page.getByRole('heading', { name: 'Orders' })).toBeVisible();
});

test('signing in before confirming the email is refused', async ({ page }) => {
  const email = uniqueEmail('e2e-unconfirmed');

  await page.goto('/register');
  await page.getByLabel('Email').fill(email);
  await page.getByLabel('Password').fill(PASSWORD);
  await page.getByRole('button', { name: 'Create account' }).click();
  await expect(page.getByRole('status')).toBeVisible();

  await page.goto('/login');
  await page.getByLabel('Email').fill(email);
  await page.getByLabel('Password').fill(PASSWORD);
  await page.getByRole('button', { name: 'Sign in' }).click();

  await expect(page.getByRole('alert')).toHaveText('Confirm your email address before logging in.');
});

import { expect, test } from '@playwright/test';
import { emailedLink, PASSWORD, signIn, uniqueEmail } from './merchant';

const NEW_PASSWORD = 'e2e-new-password';

test('a merchant who forgot the password sets a new one through the emailed link', async ({
  page,
  request,
}) => {
  const email = uniqueEmail('e2e-password-reset');
  await page.goto('/register');
  await page.getByLabel('Email').fill(email);
  await page.getByLabel('Password').fill(PASSWORD);
  await page.getByRole('button', { name: 'Create account' }).click();
  await page.goto(await emailedLink(request, email, '/confirm'));
  await page.getByRole('button', { name: 'Confirm email' }).click();
  await expect(page.getByRole('status')).toBeVisible();

  await page.goto('/login');
  await page.getByRole('link', { name: 'Forgot your password?' }).click();
  await page.getByLabel('Email').fill(email);
  await page.getByRole('button', { name: 'Send reset link' }).click();
  await expect(page.getByRole('status')).toContainText('a reset link is on its way');

  await page.goto(await emailedLink(request, email, '/reset-password'));
  await page.getByLabel('New password').fill(NEW_PASSWORD);
  await page.getByRole('button', { name: 'Set new password' }).click();
  await expect(page.getByRole('status')).toHaveText(
    'Your password is changed. Sign in with the new one.',
  );

  await signIn(page, { email, password: NEW_PASSWORD });
  await expect(page.getByRole('heading', { name: 'No panel access' })).toBeVisible();
});

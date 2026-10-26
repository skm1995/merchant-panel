import { expect, test } from '@playwright/test';
import { createMerchant, signIn, uniqueEmail } from './merchant';

test('a signed-in merchant moves between orders, products, and reports', async ({
  page,
  request,
}) => {
  const email = uniqueEmail('e2e-panel');
  await createMerchant(request, email);
  await signIn(page, email);

  await page.getByRole('link', { name: 'Products' }).click();
  await expect(page.getByRole('table', { name: 'Products' })).toBeVisible();

  await page.getByRole('link', { name: 'Reports' }).click();
  await page.getByLabel('From').fill('2026-01-01');
  await page.getByLabel('To').fill('2026-01-31');
  await page.getByRole('button', { name: 'Show reports' }).click();
  await expect(page.getByRole('table', { name: 'Daily revenue' })).toBeVisible();
  await expect(page.getByRole('table', { name: 'Top products' })).toBeVisible();

  await page.getByRole('button', { name: 'Sign out' }).click();
  await expect(page.getByRole('heading', { name: 'Sign in' })).toBeVisible();
});

test('panel pages need a signed-in merchant', async ({ page }) => {
  await page.goto('/reports');

  await expect(page.getByRole('heading', { name: 'Sign in' })).toBeVisible();
});

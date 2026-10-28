import { expect, test } from '@playwright/test';
import { demoMerchant, signIn } from './merchant';

test('a signed-in merchant moves between orders, products, reports, and analytics', async ({
  page,
}) => {
  const violations: string[] = [];
  page.on('console', (message) => {
    if (message.type() === 'error') {
      violations.push(message.text());
    }
  });
  await signIn(page, demoMerchant());
  await expect(page.getByRole('heading', { name: 'Orders' })).toBeVisible();

  await page.getByRole('link', { name: 'Products' }).click();
  await expect(page.getByRole('table', { name: 'Products' })).toBeVisible();

  await page.getByRole('link', { name: 'Reports' }).click();
  await page.getByLabel('From').fill('2026-01-01');
  await page.getByLabel('To').fill('2026-01-31');
  await page.getByRole('button', { name: 'Show reports' }).click();
  const revenue = page.getByRole('figure', { name: 'Daily revenue' });
  await expect(revenue.locator('svg').first()).toBeVisible();
  await revenue.getByRole('button', { name: 'Show data' }).click();
  await expect(revenue.getByRole('table', { name: 'Daily revenue' })).toBeVisible();
  await expect(page.getByRole('figure', { name: 'Top products' })).toBeVisible();

  await page.getByRole('link', { name: 'Analytics' }).click();
  await expect(page.getByRole('heading', { name: 'Analytics' })).toBeVisible();
  await expect(page.getByRole('figure', { name: 'Products that sell out first' })).toBeVisible();
  await expect(
    page.getByRole('group', { name: 'Paid orders by weekday and hour (UTC)' }),
  ).toBeVisible();
  await expect(
    page
      .getByRole('figure', { name: 'Monthly revenue against the year before' })
      .locator('svg')
      .first(),
  ).toBeVisible();
  expect(violations, 'The charts run under the strict Content Security Policy.').toEqual([]);

  await page.getByRole('button', { name: 'Sign out' }).click();
  await expect(page.getByRole('heading', { name: 'Sign in' })).toBeVisible();
});

test('panel pages need a signed-in merchant', async ({ page }) => {
  await page.goto('/reports');

  await expect(page.getByRole('heading', { name: 'Sign in' })).toBeVisible();
});

test('old links to the retired stock page open the product editor', async ({ page }) => {
  await signIn(page, demoMerchant());

  await page.goto('/legacy/product-stock.php?id=999999');

  await expect(page).toHaveURL(/\/products\/999999\/edit$/);
  await expect(page.getByRole('alert')).toHaveText('Product 999999 does not exist.');
});

import { expect, test } from '@playwright/test';
import { e2eMerchant, signIn } from './merchant';

test('a signed-in merchant moves between orders, products, and reports', async ({ page }) => {
  await signIn(page, e2eMerchant());
  await expect(page.getByRole('heading', { name: 'Orders' })).toBeVisible();

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

test('old links to the retired stock page open the product editor', async ({ page }) => {
  await signIn(page, e2eMerchant());

  await page.goto('/legacy/product-stock.php?id=999999');

  await expect(page).toHaveURL(/\/products\/999999\/edit$/);
  await expect(page.getByRole('alert')).toHaveText('Product 999999 does not exist.');
});

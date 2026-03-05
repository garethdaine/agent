import { test as setup, expect } from '@playwright/test';

setup('authenticate', async ({ page }) => {
  await page.goto('/login');
  await page.locator('#email').fill(process.env.TEST_USER_EMAIL || 'test@example.com');
  await page.locator('#password').fill(process.env.TEST_USER_PASSWORD || 'password');
  await page.getByRole('button', { name: /sign in/i }).click();
  await page.waitForURL(/\/(dashboard|onboarding)/, { timeout: 15000 });
  if (page.url().includes('/onboarding')) {
    await page.getByRole('button', { name: /skip for now/i }).click();
    await expect(page).toHaveURL(/\/dashboard/);
  }
  await page.context().storageState({ path: 'tests/e2e/.auth/user.json' });
});

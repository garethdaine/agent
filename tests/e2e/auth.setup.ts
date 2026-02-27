import { test as setup, expect } from '@playwright/test';

setup('authenticate', async ({ page }) => {
  await page.goto('/login');
  await page.fill('input[type="email"]', process.env.TEST_USER_EMAIL || 'test@example.com');
  await page.fill('input[type="password"]', process.env.TEST_USER_PASSWORD || 'password');
  await page.click('button[type="submit"]');
  await expect(page).toHaveURL('/dashboard');
  await page.context().storageState({ path: 'tests/e2e/.auth/user.json' });
});

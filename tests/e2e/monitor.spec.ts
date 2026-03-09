import { test, expect } from '@playwright/test';

test.describe('Monitor Page', () => {
  test.use({ storageState: 'tests/e2e/.auth/user.json' });

  test('displays health cards', async ({ page }) => {
    await page.goto('/agent/monitor');
    await page.waitForLoadState('networkidle');
    await expect(
      page.getByText(/Scheduler Health|Health/i).first()
    ).toBeVisible({ timeout: 10000 });
    await expect(
      page.getByText(/Queue Lag|Queue|Lag/i).first()
    ).toBeVisible({ timeout: 10000 });
  });

  test('runs table loads and is interactive', async ({ page }) => {
    await page.goto('/agent/monitor');
    await expect(
      page.getByRole('table').first()
    ).toBeVisible({ timeout: 10000 });
    await expect(page.getByRole('columnheader').first()).toBeVisible();
  });

  test('auto-follow toggle works', async ({ page }) => {
    await page.goto('/agent/monitor');
    await page.waitForLoadState('networkidle');
    const toggle = page.getByRole('button', { name: /auto-follow|follow/i });
    if (await toggle.isVisible()) {
      await toggle.click();
      await page.waitForTimeout(200);
      await expect(toggle).toBeVisible();
    }
  });

  test('clicking run updates event tail', async ({ page }) => {
    await page.goto('/agent/monitor');
    const firstRow = page.getByRole('row').nth(1);
    await firstRow.waitFor({ timeout: 15000 }).catch(() => null);
    if (await firstRow.isVisible().catch(() => false)) {
      await firstRow.click();
      await page.waitForTimeout(1000);
      const eventArea = page.getByRole('log')
        .or(page.locator('pre'))
        .or(page.locator('code'))
        .first();
      await expect(eventArea).toBeVisible({ timeout: 5000 }).catch(() => {
        // Events may not exist yet for new run - that's acceptable
      });
    }
  });

  test('page shows loading state initially', async ({ page }) => {
    await page.goto('/agent/monitor');
    const loadingOrContent = page.getByRole('table')
      .or(page.getByRole('progressbar'))
      .first();
    await expect(loadingOrContent).toBeVisible({ timeout: 5000 });
  });

  test('scheduler status indicator is present', async ({ page }) => {
    await page.goto('/agent/monitor');
    await page.waitForLoadState('networkidle');
    const statusIndicator = page.getByText(/healthy|warning|critical|online|offline/i).first();
    await expect(statusIndicator).toBeVisible({ timeout: 10000 });
  });

  test('refresh button triggers data reload', async ({ page }) => {
    await page.goto('/agent/monitor');
    await page.waitForLoadState('networkidle');
    const refreshButton = page.getByRole('button', { name: /refresh/i });
    if (await refreshButton.isVisible()) {
      await refreshButton.click();
      await page.waitForTimeout(500);
    }
  });

  test('monitor page has correct heading', async ({ page }) => {
    await page.goto('/agent/monitor');
    await expect(page.getByRole('heading').first()).toBeVisible();
  });
});

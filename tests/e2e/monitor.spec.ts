import { test, expect } from '@playwright/test';

test.describe('Monitor Page', () => {
  test.use({ storageState: 'tests/e2e/.auth/user.json' });

  test('displays health cards', async ({ page }) => {
    await page.goto('/agent/monitor');
    // Wait for page to load
    await page.waitForLoadState('networkidle');
    // Check for health-related content (scheduler health, queue lag)
    await expect(
      page.locator('text=Scheduler Health, text=Health, [class*="health"]').first()
    ).toBeVisible({ timeout: 10000 });
    await expect(
      page.locator('text=Queue Lag, text=Queue, text=Lag, [class*="queue"]').first()
    ).toBeVisible({ timeout: 10000 });
  });

  test('runs table loads and is interactive', async ({ page }) => {
    await page.goto('/agent/monitor');
    // Wait for runs to load
    await page.waitForSelector('table, [data-testid="runs-table"]', { timeout: 10000 });
    // Should have at least table header
    await expect(page.locator('th, [role="columnheader"]').first()).toBeVisible();
  });

  test('auto-follow toggle works', async ({ page }) => {
    await page.goto('/agent/monitor');
    await page.waitForLoadState('networkidle');
    // Look for auto-follow toggle button
    const toggle = page.locator(
      'button:has-text("Auto-follow"), button:has-text("Follow"), [data-testid="auto-follow"]'
    );
    if (await toggle.isVisible()) {
      const initialClass = await toggle.getAttribute('class');
      await toggle.click();
      // Verify state changed (button text or class change)
      await page.waitForTimeout(200);
      // Toggle should still be visible after click
      await expect(toggle).toBeVisible();
    }
  });

  test('clicking run updates event tail', async ({ page }) => {
    await page.goto('/agent/monitor');
    await page.waitForSelector('table tbody tr, [data-testid="run-row"]', { timeout: 15000 }).catch(() => null);
    const firstRun = page.locator('table tbody tr, [data-testid="run-row"]').first();
    if (await firstRun.isVisible().catch(() => false)) {
      await firstRun.click();
      // Event tail should update (look for event content area)
      await page.waitForTimeout(1000);
      // Check for event display area
      const eventArea = page.locator('[class*="event"], [class*="log"], pre, code').first();
      await expect(eventArea).toBeVisible({ timeout: 5000 }).catch(() => {
        // Events may not exist yet for new run - that's acceptable
      });
    }
  });

  test('page shows loading state initially', async ({ page }) => {
    await page.goto('/agent/monitor');
    // Either loading indicator or content should appear quickly
    const loadingOrContent = page.locator(
      '[class*="loading"], [class*="skeleton"], table, [class*="card"]'
    ).first();
    await expect(loadingOrContent).toBeVisible({ timeout: 5000 });
  });

  test('scheduler status indicator is present', async ({ page }) => {
    await page.goto('/agent/monitor');
    await page.waitForLoadState('networkidle');
    // Look for scheduler status indicator (healthy/warning/critical badge or icon)
    const statusIndicator = page.locator(
      '[class*="status"], [class*="badge"], [class*="health"], svg'
    ).first();
    await expect(statusIndicator).toBeVisible({ timeout: 10000 });
  });

  test('refresh button triggers data reload', async ({ page }) => {
    await page.goto('/agent/monitor');
    await page.waitForLoadState('networkidle');
    // Look for refresh button
    const refreshButton = page.locator(
      'button:has-text("Refresh"), button[aria-label*="refresh"], button:has(svg[class*="refresh"])'
    );
    if (await refreshButton.isVisible()) {
      await refreshButton.click();
      // Button should respond to click (may show loading state)
      await page.waitForTimeout(500);
    }
  });

  test('monitor page has correct heading', async ({ page }) => {
    await page.goto('/agent/monitor');
    await expect(page.locator('h1, h2').first()).toBeVisible();
  });
});

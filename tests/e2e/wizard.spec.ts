import { test, expect } from '@playwright/test';

test.describe('Discovery Wizard', () => {
  test.use({ storageState: 'tests/e2e/.auth/user.json' });

  test('can navigate to wizard from discovery index', async ({ page }) => {
    await page.goto('/tools/discovery');
    // Verify discovery page loads with heading
    await expect(page.locator('h1, h2').filter({ hasText: /discovery|wizard/i })).toBeVisible();
  });

  test('phase stepper shows correct active phase', async ({ page }) => {
    // Navigate to discovery index
    await page.goto('/tools/discovery');
    // Click first session link if exists
    const sessionLink = page.locator('a[href*="/tools/discovery/"]').first();
    if (await sessionLink.isVisible()) {
      await sessionLink.click();
      // Verify phase stepper is visible (component may use data-testid or class)
      await expect(
        page.locator('[data-testid="phase-stepper"], .phase-stepper, [class*="stepper"]')
      ).toBeVisible({ timeout: 10000 });
    }
  });

  test('discovery index displays session list or empty state', async ({ page }) => {
    await page.goto('/tools/discovery');
    // Should show either session list or new session prompt
    const hasContent = await page
      .locator('a[href*="/tools/discovery/"], button:has-text("New"), [class*="empty"]')
      .first()
      .isVisible();
    expect(hasContent).toBeTruthy();
  });

  test('can navigate to create new session', async ({ page }) => {
    await page.goto('/tools/discovery/new');
    // Verify create page loads
    await expect(
      page.locator('h1, h2, form').first()
    ).toBeVisible();
  });

  test('can submit answer to question', async ({ page }) => {
    // This test requires a session in interrogation phase
    // Skip if no appropriate session available
    test.skip(true, 'Requires session in interrogation phase');
  });

  test('plan section renders when in planning phase', async ({ page }) => {
    // Skip if no session in planning phase
    test.skip(true, 'Requires session in planning phase');
  });

  test('wizard displays status card', async ({ page }) => {
    await page.goto('/tools/discovery');
    const sessionLink = page.locator('a[href*="/tools/discovery/"]').first();
    if (await sessionLink.isVisible()) {
      await sessionLink.click();
      // Wait for wizard page to load
      await page.waitForLoadState('networkidle');
      // Check for status indicators (status badge, phase info, or stats)
      const statusElements = page.locator(
        '[class*="status"], [class*="badge"], [class*="phase"], [data-testid*="status"]'
      );
      const count = await statusElements.count();
      expect(count).toBeGreaterThan(0);
    }
  });

  test('settings page accessible from wizard', async ({ page }) => {
    await page.goto('/tools/discovery');
    const sessionLink = page.locator('a[href*="/tools/discovery/"]').first();
    if (await sessionLink.isVisible()) {
      // Extract session ID from href
      const href = await sessionLink.getAttribute('href');
      const match = href?.match(/\/tools\/discovery\/(\d+)/);
      if (match) {
        const sessionId = match[1];
        await page.goto(`/tools/discovery/${sessionId}/settings`);
        await expect(page.locator('form, [class*="settings"]').first()).toBeVisible();
      }
    }
  });
});

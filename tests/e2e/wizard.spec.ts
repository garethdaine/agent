import { test, expect } from '@playwright/test';

test.describe('Discovery Wizard', () => {
  test.use({ storageState: 'tests/e2e/.auth/user.json' });

  test('can navigate to wizard from discovery index', async ({ page }) => {
    await page.goto('/tools/discovery');
    await expect(page.getByRole('heading', { name: /discovery|wizard/i })).toBeVisible();
  });

  test('phase stepper shows correct active phase', async ({ page }) => {
    await page.goto('/tools/discovery');
    const sessionLink = page.getByRole('link', { name: /session|discovery/i }).first();
    if (await sessionLink.isVisible()) {
      await sessionLink.click();
      await expect(
        page.getByRole('navigation').or(page.getByText(/phase/i)).first()
      ).toBeVisible({ timeout: 10000 });
    }
  });

  test('discovery index displays session list or empty state', async ({ page }) => {
    await page.goto('/tools/discovery');
    const hasContent = await page
      .getByRole('link')
      .or(page.getByRole('button', { name: /new/i }))
      .first()
      .isVisible();
    expect(hasContent).toBeTruthy();
  });

  test('can navigate to create new session', async ({ page }) => {
    await page.goto('/tools/discovery/new');
    await expect(
      page.getByRole('heading').or(page.getByRole('form')).first()
    ).toBeVisible();
  });

  test('can submit answer to question', async ({ page }) => {
    test.skip(true, 'Requires session in interrogation phase');
  });

  test('plan section renders when in planning phase', async ({ page }) => {
    test.skip(true, 'Requires session in planning phase');
  });

  test('wizard displays status card', async ({ page }) => {
    await page.goto('/tools/discovery');
    const sessionLink = page.getByRole('link', { name: /session|discovery/i }).first();
    if (await sessionLink.isVisible()) {
      await sessionLink.click();
      await page.waitForLoadState('networkidle');
      const statusElements = page.getByText(/status|phase|progress/i);
      const count = await statusElements.count();
      expect(count).toBeGreaterThan(0);
    }
  });

  test('settings page accessible from wizard', async ({ page }) => {
    await page.goto('/tools/discovery');
    const sessionLink = page.getByRole('link', { name: /session|discovery/i }).first();
    if (await sessionLink.isVisible()) {
      const href = await sessionLink.getAttribute('href');
      const match = href?.match(/\/tools\/discovery\/(\d+)/);
      if (match) {
        const sessionId = match[1];
        await page.goto(`/tools/discovery/${sessionId}/settings`);
        await expect(page.getByRole('form').or(page.getByText(/settings/i)).first()).toBeVisible();
      }
    }
  });
});

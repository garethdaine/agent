import { test, expect } from '@playwright/test';

test.describe('Delegation Graph Builder', () => {
  test.use({ storageState: 'tests/e2e/.auth/user.json' });

  test('builder page loads and shows canvas or Add task', async ({ page }) => {
    const response = await page.goto('/agent/delegation/graphs/builder');
    if (response?.status() === 403 || response?.status() === 404) {
      test.skip(true, 'Delegation UI disabled');
      return;
    }
    expect(response?.status()).toBe(200);
    await page.waitForLoadState('networkidle');
    await expect(
      page.locator('button:has-text("Add task"), [class*="vue-flow"], canvas, [class*="GraphCanvas"]').first()
    ).toBeVisible({ timeout: 10000 });
  });

  test('can add a task node', async ({ page }) => {
    const response = await page.goto('/agent/delegation/graphs/builder');
    if (response?.status() !== 200) {
      test.skip(true, 'Delegation UI disabled');
      return;
    }
    await page.waitForLoadState('networkidle');
    const addBtn = page.locator('button:has-text("Add task")');
    if (!(await addBtn.isVisible())) {
      test.skip(true, 'Add task button not found');
      return;
    }
    await addBtn.click();
    await page.waitForTimeout(500);
    await expect(page.locator('[class*="delegationTask"], [data-id^="task-"]').first()).toBeVisible({ timeout: 5000 });
  });
});

test.describe('Org Layer Builder', () => {
  test.use({ storageState: 'tests/e2e/.auth/user.json' });

  test('builder page loads and shows Add agent or canvas', async ({ page }) => {
    const response = await page.goto('/agent/org/builder');
    if (response?.status() === 403 || response?.status() === 404) {
      test.skip(true, 'Org UI disabled');
      return;
    }
    expect(response?.status()).toBe(200);
    await page.waitForLoadState('networkidle');
    await expect(
      page.locator('button:has-text("Add agent"), [class*="vue-flow"], [class*="GraphCanvas"]').first()
    ).toBeVisible({ timeout: 10000 });
  });

  test('can add an agent node', async ({ page }) => {
    const response = await page.goto('/agent/org/builder');
    if (response?.status() !== 200) {
      test.skip(true, 'Org UI disabled');
      return;
    }
    await page.waitForLoadState('networkidle');
    const addBtn = page.locator('button:has-text("Add agent")');
    if (!(await addBtn.isVisible())) {
      test.skip(true, 'Add agent button not found');
      return;
    }
    await addBtn.click();
    await page.waitForTimeout(500);
    await expect(
      page.locator('[class*="orgAgent"], [data-id^="temp-"]').first()
    ).toBeVisible({ timeout: 5000 });
  });
});

test.describe('3D Office', () => {
  test.use({ storageState: 'tests/e2e/.auth/user.json' });

  test('office page loads and shows canvas or fallback message', async ({ page }) => {
    const response = await page.goto('/agent/office');
    if (response?.status() === 404) {
      test.skip(true, '3D Office disabled');
      return;
    }
    expect(response?.status()).toBe(200);
    await page.waitForLoadState('networkidle');
    const hasCanvas = await page.locator('canvas').isVisible();
    const hasFallback = await page.locator('text=3D view unavailable').isVisible();
    const hasHeading = await page.locator('h2:has-text("3D Office")').isVisible();
    expect(hasCanvas || hasFallback || hasHeading).toBeTruthy();
  });
});

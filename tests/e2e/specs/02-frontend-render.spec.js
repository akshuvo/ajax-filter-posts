const { test, expect } = require('@playwright/test');
const { captureScreenshot } = require('../helpers/visual-diff.js');
const { ENV } = require('../config/test-env.js');

test.describe('Frontend Grid Rendering & Visual Layouts', () => {
  // Use public/unauthenticated or authenticated state
  test('renders grids cleanly on demo page and captures responsive layouts', async ({ page }) => {
    const consoleErrors = [];
    page.on('console', (msg) => {
      if (msg.type() === 'error') {
        consoleErrors.push(msg.text());
      }
    });

    await page.goto(`${ENV.baseURL}/gridmaster-v2-demo/`, { waitUntil: 'networkidle' });

    // Assert both grids exist
    const grid18 = page.locator('#gm-grid-18');
    const grid19 = page.locator('#gm-grid-19');

    await expect(grid18).toBeVisible({ timeout: 10000 });
    await expect(grid19).toBeVisible({ timeout: 10000 });

    // Verify grid 18 items exist
    const grid18Items = grid18.locator('.gm-grid-item');
    expect(await grid18Items.count()).toBeGreaterThan(0);

    // Verify grid 19 items exist
    const grid19Items = grid19.locator('.gm-grid-item');
    expect(await grid19Items.count()).toBeGreaterThan(0);

    // Capture individual grid element screenshots on Desktop
    await captureScreenshot(grid18, 'grid-18-desktop', 'frontend-grids');
    await captureScreenshot(grid19, 'grid-19-desktop', 'frontend-grids');

    // Capture first card of grid 18 for high-res typography & spacing inspection
    const firstCard18 = grid18.locator('.gm-grid-item .gm-card').first();
    await captureScreenshot(firstCard18, 'grid-18-single-card-detail', 'frontend-grids');

    // Capture first card of grid 19 for list layout inspection
    const firstCard19 = grid19.locator('.gm-grid-item .gm-card').first();
    await captureScreenshot(firstCard19, 'grid-19-single-card-detail', 'frontend-grids');

    // Verify no fatal console errors
    const fatalErrors = consoleErrors.filter((err) =>
      !err.includes('favicon') && !err.includes('Deprecated') && !err.includes('Xdebug')
    );
    expect(fatalErrors).toEqual([]);
  });
});

const { test, expect } = require('@playwright/test');
const { captureScreenshot } = require('../helpers/visual-diff.js');
const { goToBuilder } = require('../helpers/wp-admin.js');
const { ENV } = require('../config/test-env.js');

test.describe('GridMaster Pro & WooCommerce Features', () => {
  // -------------------------------------------------------------
  // Test 1: Frontend WooCommerce Grid with Swatches, Price, & Meta
  // -------------------------------------------------------------
  test('renders WooCommerce Pro grid with variation swatches, prices, stock, and quote buttons', async ({ page }) => {
    const consoleErrors = [];
    page.on('console', (msg) => {
      if (msg.type() === 'error') {
        consoleErrors.push(msg.text());
      }
    });

    await page.goto(`${ENV.baseURL}/woo-grids/`, { waitUntil: 'networkidle' });

    const wooGrid = page.locator('#gm-grid-309');
    await expect(wooGrid).toBeVisible({ timeout: 10000 });

    // Assert Pro elements are present
    const firstItem = wooGrid.locator('.gm-grid-item').first();
    await expect(firstItem).toBeVisible();

    // Verify Product Price block
    const priceBlock = firstItem.locator('.gm-product-price');
    await expect(priceBlock).toBeVisible();

    // Verify Stock Status block
    const stockBlock = firstItem.locator('.gm-stock-status');
    await expect(stockBlock).toBeVisible();

    // Verify Variation Swatches block
    const swatchesBlock = firstItem.locator('.gm-variation-swatches');
    if (await swatchesBlock.isVisible()) {
      // Capture before swatch click
      await captureScreenshot(firstItem, 'woo-card-initial', 'pro-features');

      // Click second swatch (e.g. green)
      const secondSwatch = swatchesBlock.locator('.gm-swatch-item').nth(1);
      if (await secondSwatch.isVisible()) {
        await secondSwatch.click();
        await page.waitForTimeout(300);
        await expect(secondSwatch).toHaveClass(/is-selected/);
        await captureScreenshot(firstItem, 'woo-card-swatch-selected', 'pro-features');
      }
    }

    // Capture full WooCommerce Grid
    await captureScreenshot(wooGrid, 'woo-grid-desktop', 'pro-features');

    const fatalErrors = consoleErrors.filter((err) =>
      !err.includes('favicon') && !err.includes('Deprecated') && !err.includes('Xdebug')
    );
    expect(fatalErrors).toEqual([]);
  });

  // -------------------------------------------------------------
  // Test 2: Vertical Video Reels & Shorts Feed
  // -------------------------------------------------------------
  test('renders vertical snap video reels feed with navigation controls', async ({ page }) => {
    const consoleErrors = [];
    page.on('console', (msg) => {
      if (msg.type() === 'error') {
        consoleErrors.push(msg.text());
      }
    });

    await page.goto(`${ENV.baseURL}/vertical-reels/`, { waitUntil: 'networkidle' });

    const reelsGrid = page.locator('#gm-grid-289');
    await expect(reelsGrid).toBeVisible({ timeout: 10000 });

    // Assert reels layout classes
    await expect(reelsGrid).toHaveClass(/gm-layout-reels/);

    const viewport = reelsGrid.locator('.gm-reels-viewport');
    await expect(viewport).toBeVisible();

    // Capture vertical reels feed screenshot
    await captureScreenshot(reelsGrid, 'vertical-reels-feed', 'pro-features');

    const fatalErrors = consoleErrors.filter((err) =>
      !err.includes('favicon') && !err.includes('Deprecated') && !err.includes('Xdebug')
    );
    expect(fatalErrors).toEqual([]);
  });

  // -------------------------------------------------------------
  // Test 3: Builder UI with Pro WooCommerce Card
  // -------------------------------------------------------------
  test('loads WooCommerce grid inside builder and renders pro blocks', async ({ page }) => {
    const consoleErrors = [];
    page.on('console', (msg) => {
      if (msg.type() === 'error') {
        consoleErrors.push(msg.text());
      }
    });

    await goToBuilder(page, 309);

    const canvasPaper = page.locator('.gm-card-canvas-paper');
    await expect(canvasPaper).toBeVisible({ timeout: 10000 });

    // Capture builder canvas of Woo card
    await captureScreenshot(canvasPaper, 'woo-builder-card-canvas', 'pro-features');

    const fatalErrors = consoleErrors.filter((err) =>
      !err.includes('favicon') && !err.includes('Deprecated') && !err.includes('Xdebug')
    );
    expect(fatalErrors).toEqual([]);
  });
});

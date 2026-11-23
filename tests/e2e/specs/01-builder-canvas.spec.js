const { test, expect } = require('@playwright/test');
const { goToBuilder, openCardTemplates, applyCardTemplate, openPreviewModal, setPreviewDevice, closePreviewModal, switchBuilderTab } = require('../helpers/wp-admin.js');
const { captureScreenshot } = require('../helpers/visual-diff.js');

test.describe('Builder Canvas & Card Presets', () => {
  test.use({ storageState: './tests/e2e/.auth/admin.json' });

  test('loads existing grid in builder and captures canvas & inspector', async ({ page }) => {
    // Collect any page console errors
    const consoleErrors = [];
    page.on('console', (msg) => {
      if (msg.type() === 'error') {
        consoleErrors.push(msg.text());
      }
    });

    // 1. Open Builder with Grid 18
    await goToBuilder(page, 18);

    // Assert canvas paper exists and is visible
    const canvasPaper = page.locator('.gm-card-canvas-paper');
    await expect(canvasPaper).toBeVisible({ timeout: 10000 });

    // Capture initial builder canvas
    await captureScreenshot(page, '01-builder-canvas-initial', 'builder-canvas');
    await captureScreenshot(canvasPaper, '01-canvas-paper-only', 'builder-canvas');

    // 2. Select card canvas paper to inspect Card settings
    await canvasPaper.click();
    await page.waitForTimeout(400);

    // Capture contextual inspector panel
    const inspectorPanel = page.locator('.gm-contextual-inspector, .gm-inspector-panel').first();
    if (await inspectorPanel.isVisible()) {
      await captureScreenshot(inspectorPanel, '02-card-inspector-settings', 'builder-canvas');
    }

    // 3. Open Card Templates Popover
    await openCardTemplates(page);
    const popover = page.locator('.gm-card-templates-popover');
    await expect(popover).toBeVisible();
    await captureScreenshot(popover, '03-card-templates-library', 'builder-canvas');

    // Filter templates by 'Editorial' tab
    const editorialTab = popover.locator('.gm-tab-pill', { hasText: 'Editorial' });
    if (await editorialTab.isVisible()) {
      await editorialTab.click();
      await page.waitForTimeout(300);
      await captureScreenshot(popover, '03b-card-templates-editorial-tab', 'builder-canvas');
    }

    // Close popover
    const closePopoverBtn = popover.locator('button[aria-label="Close"], .gm-popover-close-btn').first();
    await closePopoverBtn.dispatchEvent('click');
    await expect(popover).toBeHidden({ timeout: 5000 });

    // 4. Open Live Preview Modal
    await openPreviewModal(page);
    const modal = page.locator('.gm-preview-modal-dialog, .components-modal__frame').first();
    await expect(modal).toBeVisible();

    // Wait for preview iframe content
    const iframe = page.frameLocator('.gm-preview-iframe');
    const previewGrid = iframe.locator('.gm-v2-grid, .gm-grid-wrapper');
    await expect(previewGrid.first()).toBeVisible({ timeout: 15000 });

    // Capture Desktop preview
    await captureScreenshot(modal, '04-preview-modal-desktop', 'preview-modal');

    // Switch to Tablet preview
    await setPreviewDevice(page, 'tablet');
    await captureScreenshot(modal, '05-preview-modal-tablet', 'preview-modal');

    // Switch to Mobile preview
    await setPreviewDevice(page, 'mobile');
    await captureScreenshot(modal, '06-preview-modal-mobile', 'preview-modal');

    // Close preview modal
    await closePreviewModal(page);
    await expect(modal).toBeHidden();

    // Verify no fatal console errors during builder operations
    const fatalErrors = consoleErrors.filter((err) =>
      !err.includes('favicon') && !err.includes('Deprecated') && !err.includes('Xdebug')
    );
    expect(fatalErrors).toEqual([]);
  });
});

const { test, expect } = require('@playwright/test');
const { captureScreenshot } = require('../helpers/visual-diff.js');
const { ENV } = require('../config/test-env.js');

test.describe('Frontend AJAX Filtering & Pagination', () => {
  test('interacts with category tabs, search input, dropdown filter, and pagination', async ({ page }) => {
    const consoleErrors = [];
    page.on('console', (msg) => {
      if (msg.type() === 'error') {
        consoleErrors.push(msg.text());
      }
    });

    await page.goto(`${ENV.baseURL}/gridmaster-v2-demo/`, { waitUntil: 'networkidle' });

    const grid18 = page.locator('#gm-grid-18');
    await expect(grid18).toBeVisible({ timeout: 10000 });

    // -------------------------------------------------------------
    // Test 1: Category Tab Filter (Design)
    // -------------------------------------------------------------
    const designTab = grid18.locator('.gm-facet-tabs button.gm-filter-btn', { hasText: 'Design' });
    if (await designTab.isVisible()) {
      const renderPromise = page.waitForResponse(
        (res) => res.url().includes('/wp-json/gridmaster/v2/grids/18/render') && res.status() === 200,
        { timeout: 10000 }
      );

      await designTab.click();
      await renderPromise;

      // Allow DOM to settle
      await page.waitForTimeout(400);

      // Verify active state
      await expect(designTab).toHaveClass(/gm-active/);

      // Capture filtered state
      await captureScreenshot(grid18, 'grid-18-filtered-design', 'filters');
    }

    // -------------------------------------------------------------
    // Test 2: Category Tab Filter (Technology)
    // -------------------------------------------------------------
    const techTab = grid18.locator('.gm-facet-tabs button.gm-filter-btn', { hasText: 'Technology' });
    if (await techTab.isVisible()) {
      const renderPromise = page.waitForResponse(
        (res) => res.url().includes('/wp-json/gridmaster/v2/grids/18/render') && res.status() === 200,
        { timeout: 10000 }
      );

      await techTab.click();
      await renderPromise;
      await page.waitForTimeout(400);

      await expect(techTab).toHaveClass(/gm-active/);
      await captureScreenshot(grid18, 'grid-18-filtered-technology', 'filters');
    }

    // -------------------------------------------------------------
    // Test 3: Reset Filter (All Stories)
    // -------------------------------------------------------------
    const allTab = grid18.locator('.gm-facet-tabs button.gm-filter-btn', { hasText: /All/i });
    if (await allTab.isVisible()) {
      const renderPromise = page.waitForResponse(
        (res) => res.url().includes('/wp-json/gridmaster/v2/grids/18/render') && res.status() === 200,
        { timeout: 10000 }
      );

      await allTab.click();
      await renderPromise;
      await page.waitForTimeout(400);

      await expect(allTab).toHaveClass(/gm-active/);
      await captureScreenshot(grid18, 'grid-18-reset-all', 'filters');
    }

    // -------------------------------------------------------------
    // Test 4: Search Bar Input Debounce
    // -------------------------------------------------------------
    const searchInput = grid18.locator('.gm-filter-search');
    if (await searchInput.isVisible()) {
      const renderPromise = page.waitForResponse(
        (res) => res.url().includes('/wp-json/gridmaster/v2/grids/18/render') && res.status() === 200,
        { timeout: 10000 }
      );

      await searchInput.fill('Design');
      await renderPromise;
      await page.waitForTimeout(400);

      await captureScreenshot(grid18, 'grid-18-search-design-results', 'filters');

      // Clear search to restore state
      const clearPromise = page.waitForResponse(
        (res) => res.url().includes('/wp-json/gridmaster/v2/grids/18/render') && res.status() === 200,
        { timeout: 10000 }
      );
      await searchInput.fill('');
      await clearPromise;
      await page.waitForTimeout(400);
    }

    // -------------------------------------------------------------
    // Test 5: Numbered Pagination Click
    // -------------------------------------------------------------
    const paginationLink = grid18.locator('.gm-pagination a.page-numbers', { hasText: '2' });
    if (await paginationLink.isVisible()) {
      const renderPromise = page.waitForResponse(
        (res) => res.url().includes('/wp-json/gridmaster/v2/grids/18/render') && res.status() === 200,
        { timeout: 10000 }
      );

      await paginationLink.click();
      await renderPromise;
      await page.waitForTimeout(400);

      await captureScreenshot(grid18, 'grid-18-pagination-page-2', 'filters');
    }

    // -------------------------------------------------------------
    // Test 6: Grid 19 Dropdown Filter
    // -------------------------------------------------------------
    const grid19 = page.locator('#gm-grid-19');
    const selectFilter = grid19.locator('select.gm-filter-select');
    if (await selectFilter.isVisible()) {
      const renderPromise = page.waitForResponse(
        (res) => res.url().includes('/wp-json/gridmaster/v2/grids/19/render') && res.status() === 200,
        { timeout: 10000 }
      );

      await selectFilter.selectOption('17');
      await renderPromise;
      await page.waitForTimeout(400);

      await captureScreenshot(grid19, 'grid-19-dropdown-filtered-design', 'filters');
    }

    // Verify console is free of fatal errors
    const fatalErrors = consoleErrors.filter((err) =>
      !err.includes('favicon') && !err.includes('Deprecated') && !err.includes('Xdebug')
    );
    expect(fatalErrors).toEqual([]);
  });
});

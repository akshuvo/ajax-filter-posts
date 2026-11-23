const { expect } = require('@playwright/test');
const { ENV } = require('../config/test-env.js');

/**
 * Navigate to GridMaster Builder
 *
 * @param {import('@playwright/test').Page} page
 * @param {number|string|null} gridId Optional grid ID to load
 */
async function goToBuilder(page, gridId = null) {
  const url = gridId
    ? `${ENV.baseURL}/wp-admin/admin.php?page=gridmaster-builder&id=${gridId}`
    : `${ENV.baseURL}/wp-admin/admin.php?page=gridmaster-builder`;

  await page.goto(url, { waitUntil: 'domcontentloaded' });
  // Wait for canvas paper to appear
  await page.waitForSelector('.gm-card-canvas-paper, .gm-workspace-bounded-body', { timeout: 15000 });
}

/**
 * Switch Builder Tab
 *
 * @param {import('@playwright/test').Page} page
 * @param {'card'|'content'|'filters'|'pagination'|'layout'|'code'} tabId
 */
async function switchBuilderTab(page, tabId) {
  const tabButton = page.locator(`#gm-tab-${tabId}`);
  await tabButton.click();
  await page.waitForTimeout(300); // Allow animation to settle
}

/**
 * Open Card Templates Popover
 *
 * @param {import('@playwright/test').Page} page
 */
async function openCardTemplates(page) {
  const btn = page.locator('button:has-text("Card Templates")');
  await btn.click();
  await page.waitForSelector('.gm-card-templates-popover', { timeout: 5000 });
}

/**
 * Apply a Card Template by ID or index
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} templateId
 */
async function applyCardTemplate(page, templateId) {
  await openCardTemplates(page);
  // Locate card template by key or text
  const card = page.locator(`.gm-card-templates-popover .gm-layout-card`).filter({
    has: page.locator(`.gm-layout-preview-inner, .gm-layout-card-title`),
  });
  // Or by title/keyword
  const target = page.locator(`.gm-card-templates-popover .gm-layout-card`).filter({
    hasText: new RegExp(templateId.replace('-', ' '), 'i'),
  }).first();

  if (await target.count() > 0) {
    await target.click();
  } else {
    // Fallback click first available layout card
    await page.locator('.gm-card-templates-popover .gm-layout-card').first().click();
  }
  // Wait for popover to close and canvas to re-render
  await page.waitForSelector('.gm-card-templates-popover', { state: 'detached', timeout: 5000 });
  await page.waitForTimeout(500);
}

/**
 * Open Preview Modal and wait for iframe content
 *
 * @param {import('@playwright/test').Page} page
 */
async function openPreviewModal(page) {
  const previewBtn = page.locator('.gm-preview-trigger-btn');
  await previewBtn.click();
  await page.waitForSelector('.gm-preview-modal-dialog, .components-modal__frame', { timeout: 8000 });
  // Wait for iframe to load
  const iframeLocator = page.locator('.gm-preview-iframe');
  await iframeLocator.waitFor({ state: 'visible', timeout: 10000 });
  // Give iframe rendering a moment to hydrate
  await page.waitForTimeout(1500);
}

/**
 * Switch Device in Preview Modal
 *
 * @param {import('@playwright/test').Page} page
 * @param {'desktop'|'tablet'|'mobile'} device
 */
async function setPreviewDevice(page, device) {
  const deviceBtn = page.locator(`.gm-device-btn`).filter({
    hasText: new RegExp(device, 'i'),
  });
  await deviceBtn.click();
  await page.waitForTimeout(500); // Transition animation
}

/**
 * Close Preview Modal
 *
 * @param {import('@playwright/test').Page} page
 */
async function closePreviewModal(page) {
  const closeBtn = page.locator('.gm-preview-modal-dialog button[aria-label="Close"], .components-modal__header button').last();
  await closeBtn.dispatchEvent('click');
  await page.waitForSelector('.gm-preview-modal-dialog, .components-modal__frame', { state: 'hidden', timeout: 5000 });
}

module.exports = {
  goToBuilder,
  switchBuilderTab,
  openCardTemplates,
  applyCardTemplate,
  openPreviewModal,
  setPreviewDevice,
  closePreviewModal,
};

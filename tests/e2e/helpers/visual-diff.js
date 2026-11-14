const fs = require('fs');
const path = require('path');
const { ENV } = require('../config/test-env.js');

let manifest = [];

/**
 * Capture an element or full page screenshot and log it to manifest
 *
 * @param {import('@playwright/test').Page | import('@playwright/test').Locator} target Page or Locator
 * @param {string} filename Name of the output image file (without .png)
 * @param {string} category Subdirectory (e.g. 'builder-canvas', 'preview-modal', 'frontend-grids', 'filters', 'pro-features')
 * @param {object} options Screenshot options (e.g. { fullPage: true })
 * @returns {Promise<string>} The absolute path to the saved screenshot
 */
async function captureScreenshot(target, filename, category = 'general', options = {}) {
  const categoryDir = path.join(ENV.screenshotDir, category);
  if (!fs.existsSync(categoryDir)) {
    fs.mkdirSync(categoryDir, { recursive: true });
  }

  // Detect viewport to distinguish desktop vs tablet vs mobile
  let deviceSuffix = '';
  try {
    const page = typeof target.page === 'function' ? target.page() : target;
    if (page && typeof page.viewportSize === 'function') {
      const vp = page.viewportSize();
      if (vp) {
        if (vp.width <= 480) deviceSuffix = '-mobile';
        else if (vp.width <= 820) deviceSuffix = '-tablet';
        else deviceSuffix = '-desktop';
      }
    }
  } catch (e) {
    // Fallback if viewport detection fails
  }

  const sanitized = filename.toLowerCase().replace(/[^a-z0-9_-]/g, '-');
  const baseName = sanitized.replace(/-(desktop|tablet|mobile)$/, '');
  const finalFilename = `${baseName}${deviceSuffix}`;

  const filepath = path.join(categoryDir, `${finalFilename}.png`);

  await target.screenshot({
    path: filepath,
    animations: 'disabled',
    ...options,
  });

  const entry = {
    category,
    name: finalFilename,
    filepath,
    relativePath: path.relative(process.cwd(), filepath),
    timestamp: new Date().toISOString(),
  };

  manifest.push(entry);

  const manifestPath = path.join(ENV.screenshotDir, 'manifest.json');
  fs.writeFileSync(manifestPath, JSON.stringify(manifest, null, 2));

  return filepath;
}

/**
 * Clear or reset manifest at start of run
 */
function resetScreenshotManifest() {
  manifest = [];
  const manifestPath = path.join(ENV.screenshotDir, 'manifest.json');
  if (fs.existsSync(manifestPath)) {
    fs.writeFileSync(manifestPath, JSON.stringify([], null, 2));
  }
}

module.exports = {
  captureScreenshot,
  resetScreenshotManifest,
};

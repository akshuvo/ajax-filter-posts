const { test: setup, expect } = require('@playwright/test');
const { ENV } = require('./test-env.js');
const fs = require('fs');
const path = require('path');

setup('authenticate as wordpress admin', async ({ page }) => {
  // Ensure .auth directory exists
  const authDir = path.dirname(ENV.authFile);
  if (!fs.existsSync(authDir)) {
    fs.mkdirSync(authDir, { recursive: true });
  }

  // Navigate to login
  await page.goto(`${ENV.baseURL}/wp-login.php`, { waitUntil: 'domcontentloaded' });

  // Fill credentials
  await page.fill('#user_login', ENV.adminUser);
  await page.fill('#user_pass', ENV.adminPass);

  // Submit and wait for admin dashboard or admin bar
  await Promise.all([
    page.waitForURL(/.*wp-admin.*/, { timeout: 15000 }),
    page.click('#wp-submit'),
  ]);

  // Assert successful admin login
  await expect(page.locator('#wpadminbar')).toBeVisible({ timeout: 10000 });

  // Save auth state for subsequent tests
  await page.context().storageState({ path: ENV.authFile });
});

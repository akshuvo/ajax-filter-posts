const { defineConfig, devices } = require('@playwright/test');

/**
 * See https://playwright.dev/docs/test-configuration.
 */
module.exports = defineConfig({
  testDir: './tests/e2e/specs',
  outputDir: './tests/e2e/test-results',
  snapshotDir: './tests/e2e/screenshots/baselines',
  /* Maximum time one test can run for. */
  timeout: 45 * 1000,
  expect: {
    timeout: 10 * 1000,
  },
  /* Run tests in files in parallel */
  fullyParallel: false,
  /* Fail the build on CI if you accidentally left test.only in the source code. */
  forbidOnly: !!process.env.CI,
  /* Retry on CI only */
  retries: 0,
  /* Opt out of parallel tests on local WordPress to prevent race conditions on shared DB */
  workers: 1,
  /* Reporter to use. See https://playwright.dev/docs/test-reporters */
  reporter: [
    ['list'],
    ['html', { outputFolder: './tests/e2e/playwright-report', open: 'never' }],
    ['json', { outputFile: './tests/e2e/test-results/results.json' }],
  ],
  /* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */
  use: {
    baseURL: process.env.WP_BASE_URL || 'https://grid-master.local',
    /* Ignore self-signed certificates on local development */
    ignoreHTTPSErrors: true,
    /* Collect trace when retrying the failed test. See https://playwright.dev/docs/trace-viewer */
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'off',
  },

  /* Configure projects for setup and browser testing */
  projects: [
    /* Setup project for WordPress authentication */
    {
      name: 'setup',
      testDir: './tests/e2e/config',
      testMatch: /auth\.setup\.js/,
    },
    /* Desktop Chrome */
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1280, height: 800 },
        storageState: './tests/e2e/.auth/admin.json',
      },
      dependencies: ['setup'],
    },
    /* Tablet Viewport */
    {
      name: 'tablet',
      use: {
        viewport: { width: 768, height: 1024 },
        userAgent: 'Mozilla/5.0 (iPad; CPU OS 16_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.5 Mobile/15E148 Safari/604.1',
        isMobile: true,
        hasTouch: true,
        storageState: './tests/e2e/.auth/admin.json',
      },
      dependencies: ['setup'],
    },
    /* Mobile Viewport */
    {
      name: 'mobile',
      use: {
        viewport: { width: 375, height: 667 },
        userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.5 Mobile/15E148 Safari/604.1',
        isMobile: true,
        hasTouch: true,
        storageState: './tests/e2e/.auth/admin.json',
      },
      dependencies: ['setup'],
    },
  ],
});

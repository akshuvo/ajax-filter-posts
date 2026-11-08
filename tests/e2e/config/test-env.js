const path = require('path');

const ENV = {
  baseURL: process.env.WP_BASE_URL || 'https://grid-master.local',
  adminUser: process.env.WP_ADMIN_USER || 'e2etest',
  adminPass: process.env.WP_ADMIN_PASS || 'E2eTestPass123!@#',
  authFile: path.resolve(__dirname, '../.auth/admin.json'),
  screenshotDir: path.resolve(__dirname, '../screenshots'),
};

module.exports = { ENV };

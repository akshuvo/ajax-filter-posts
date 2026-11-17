const { test, expect } = require('@playwright/test');
const { ENV } = require('../config/test-env.js');
const { goToBuilder, switchBuilderTab } = require('../helpers/wp-admin.js');

const filterFatalErrors = (errors) =>
  errors.filter(
    (err) =>
      !err.includes('favicon') &&
      !err.includes('Deprecated') &&
      !err.includes('Xdebug') &&
      !err.includes('ResizeObserver loop')
  );

test.describe('Part 3 Features, Admin Pages & Accessibility/LCP E2E', () => {
  test('verifies auto-login admin pages, saved card patterns, meta-range facet, URL persistence & SR/LCP', async ({ page }) => {
    const consoleErrors = [];
    const pageErrors = [];
    page.on('console', (msg) => {
      if (msg.type() === 'error') {
        consoleErrors.push(msg.text());
      }
    });
    page.on('pageerror', (err) => {
      pageErrors.push(err.message || String(err));
    });

    const cleanup = {
      grids: [],
      posts: [],
      pages: [],
      restNonce: '',
      patternIds: [],
    };

    const restCall = async (method, path, body = null) => {
      return page.evaluate(
        async ({ baseURL, nonce, method, path, body }) => {
          const res = await fetch(`${baseURL}/wp-json/${path}`, {
            method,
            headers: {
              'Content-Type': 'application/json',
              'X-WP-Nonce': nonce,
            },
            body: body ? JSON.stringify(body) : undefined,
          });
          const text = await res.text();
          let data = null;
          try {
            data = JSON.parse(text);
          } catch (e) {
            data = { raw: text };
          }
          return { status: res.status, ok: res.ok, data };
        },
        { baseURL: ENV.baseURL, nonce: cleanup.restNonce, method, path, body }
      );
    };

    try {
      // 1. Auto-login / WP Admin screens verification (Settings & Category Taxonomies)
      await page.goto(`${ENV.baseURL}/wp-admin/?localwp_auto_login=1`, { waitUntil: 'domcontentloaded' });
      await expect(page.locator('#wpadminbar')).toBeVisible({ timeout: 10000 });

      await page.goto(`${ENV.baseURL}/wp-admin/admin.php?page=gridmaster&path=settings`, { waitUntil: 'domcontentloaded' });
      await expect(page.locator('.gridmaster-options-form')).toBeVisible();

      await page.goto(`${ENV.baseURL}/wp-admin/edit-tags.php?taxonomy=category`, { waitUntil: 'domcontentloaded' });
      await expect(page.locator('#addtag')).toBeVisible();

      // 2. Studio Builder: User-Saved Card Patterns + Part 3 Meta Range controls
      await goToBuilder(page);
      cleanup.restNonce = await page.evaluate(() => (window.gridmasterBuilderData ? window.gridmasterBuilderData.nonce : ''));
      expect(cleanup.restNonce).toBeTruthy();

      const suffix = `${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 6)}`;
      const patternTitle = `E2E Pattern ${suffix}`;

      const savePatBtn = page.locator('.gm-save-pattern-btn');
      await savePatBtn.scrollIntoViewIfNeeded();
      await expect(savePatBtn).toBeVisible();
      await savePatBtn.click();

      const patPopover = page.locator('.gm-save-pattern-popover');
      await expect(patPopover).toBeVisible({ timeout: 5000 });
      await patPopover.locator('input[type="text"]').fill(patternTitle);

      const createPatPromise = page.waitForResponse(
        (res) => res.url().includes('/wp-json/gridmaster/v2/card-patterns') && res.request().method() === 'POST' && res.status() === 201,
        { timeout: 10000 }
      );
      await patPopover.locator('button:has-text("Save")').click();
      const patResp = await createPatPromise;
      const patBody = await patResp.json();
      if (patBody?.pattern?.id) {
        cleanup.patternIds.push(patBody.pattern.id);
      }
      await expect(patPopover).toBeHidden();

      // Verify pattern appears inside Card Templates -> Saved tab and can be deleted cleanly
      await page.locator('.gm-card-templates-btn').click();
      const templatesPopover = page.locator('.gm-card-templates-popover');
      await expect(templatesPopover).toBeVisible();
      const savedTab = templatesPopover.locator('.gm-tab-pill', { hasText: 'Saved' });
      await expect(savedTab).toBeVisible();
      await savedTab.click();

      const patCard = templatesPopover.locator('.gm-layout-card', { hasText: patternTitle });
      await expect(patCard).toBeVisible();

      const delBtn = patCard.locator('.gm-pattern-delete-btn');
      await delBtn.scrollIntoViewIfNeeded();
      await expect(delBtn).toBeVisible();
      const delPatPromise = page.waitForResponse(
        (res) => res.url().includes('/wp-json/gridmaster/v2/card-patterns/') && res.request().method() === 'DELETE' && res.status() === 200,
        { timeout: 10000 }
      );
      await delBtn.click();
      await delPatPromise;
      cleanup.patternIds = [];
      await templatesPopover.locator('.gm-popover-close-btn').click();

      // Verify Meta Range + Dynamic Recount Cache toggle in Filters tab
      await switchBuilderTab(page, 'filters');
      await page.locator('.gm-add-filter-btn').click();
      const mrAddBtn = page.locator('button:has-text("Numeric Meta Range Slider")').first();
      await expect(mrAddBtn).toBeVisible();
      await mrAddBtn.click();
      await expect(page.getByText(/Enable Dynamic Recount Fragment Cache/i).first()).toBeVisible();

      // 3. Seed posts & build a Meta-Range + LCP frontend grid via REST
      const metaKey = `gm_area_${suffix.toLowerCase()}`;
      const pLow = await restCall('POST', 'wp/v2/posts', {
        title: `Loft Small ${suffix}`,
        status: 'publish',
        content: 'Loft A',
      });
      const pHigh = await restCall('POST', 'wp/v2/posts', {
        title: `Villa Large ${suffix}`,
        status: 'publish',
        content: 'Villa B',
      });
      expect(pLow.ok && pHigh.ok).toBeTruthy();
      cleanup.posts.push(pLow.data.id, pHigh.data.id);

      const mrGridRes = await restCall('POST', 'gridmaster/v2/grids', {
        title: `E2E MetaRange ${suffix}`,
        block_content: [
          '<!-- wp:gridmaster/card {"padding":"16px","backgroundColor":"#ffffff"} -->',
          `<!-- wp:gridmaster/featured-image {"sourceType":"static_url","staticUrl":"${ENV.baseURL}/wp-content/plugins/ajax-filter-posts/admin/assets/tmpl-imgs/template-1.png"} /-->`,
          '<!-- wp:gridmaster/title {"isLink":false} /-->',
          '<!-- /wp:gridmaster/card -->',
        ].join('\n'),
        config: {
          query: {
            post_type: 'post',
            post__in: `${pLow.data.id},${pHigh.data.id}`,
            orderby: 'post__in',
            posts_per_page: 4,
          },
          facets: [
            {
              id: 'e2e_mr',
              type: 'meta-range',
              meta_key: metaKey,
              heading: 'Square Footage',
              min_val: 500,
              max_val: 3000,
              step: 100,
              unit_suffix: 'sq ft',
            },
            { id: 'e2e_af', type: 'active-filters' },
          ],
          pagination: { type: 'none' },
          layout: { type: 'grid', columns: { xs: 1, sm: 2, md: 2, lg: 2, xl: 2 } },
        },
      });
      expect(mrGridRes.ok).toBeTruthy();
      const mrGridId = mrGridRes.data.id;
      cleanup.grids.push(mrGridId);

      const pageRes = await restCall('POST', 'wp/v2/pages', {
        title: `E2E Part3 Front ${suffix}`,
        status: 'publish',
        content: `[gridmaster id="${mrGridId}"]`,
      });
      expect(pageRes.ok).toBeTruthy();
      cleanup.pages.push(pageRes.data.id);

      await page.goto(pageRes.data.link, { waitUntil: 'networkidle' });
      const wrap = page.locator(`#gm-grid-${mrGridId}`);
      await expect(wrap).toBeVisible();

      // Verify LCP priority attribute on first card image vs lazy on second card
      const firstImg = wrap.locator('.gm-grid-item').first().locator('img.gm-card-img');
      const secondImg = wrap.locator('.gm-grid-item').nth(1).locator('img.gm-card-img');
      await expect(firstImg).toHaveAttribute('fetchpriority', 'high');
      await expect(secondImg).toHaveAttribute('loading', 'lazy');

      // Change meta-range min input -> triggers AJAX render, chip creation, URL sync & SR status update
      const minNum = wrap.locator('.gm-meta-input-min');
      await expect(minNum).toBeVisible();

      const renderWait = page.waitForResponse(
        (res) => res.url().includes(`/wp-json/gridmaster/v2/grids/${mrGridId}/render`) && res.status() === 200,
        { timeout: 10000 }
      );
      await minNum.fill('1200');
      await minNum.dispatchEvent('change');
      await renderWait;

      // Active filter chip appears, URL contains gm_meta_min_*, SR announcer has text
      await expect(wrap.locator(`.gm-active-filter-chip[data-chip-type="meta_range"][data-chip-key="${metaKey}"]`)).toBeVisible();
      await expect(page).toHaveURL(new RegExp(`gm_meta_min_${metaKey}=1200`));
      await expect(wrap.locator('.gm-sr-status')).toContainText(/No posts found|Showing/i);

      // Reload & verify SSR + JS hydrate numeric range input value
      await page.reload({ waitUntil: 'networkidle' });
      const reloadedWrap = page.locator(`#gm-grid-${mrGridId}`);
      await expect(reloadedWrap).toHaveAttribute('data-url-hydrated', '1');
      await expect(reloadedWrap.locator('.gm-meta-input-min')).toHaveValue('1200');

      expect(pageErrors).toEqual([]);
      expect(filterFatalErrors(consoleErrors)).toEqual([]);
    } finally {
      if (cleanup.restNonce) {
        for (const patId of cleanup.patternIds) {
          await restCall('DELETE', `gridmaster/v2/card-patterns/${patId}`).catch(() => {});
        }
        for (const pid of cleanup.pages) {
          await restCall('DELETE', `wp/v2/pages/${pid}?force=true`).catch(() => {});
        }
        for (const gid of cleanup.grids) {
          await restCall('DELETE', `gridmaster/v2/grids/${gid}`).catch(() => {});
        }
        for (const pid of cleanup.posts) {
          await restCall('DELETE', `wp/v2/posts/${pid}?force=true`).catch(() => {});
        }
      }
    }
  });
});

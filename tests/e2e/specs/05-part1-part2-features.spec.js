const { test, expect } = require('@playwright/test');
const fs = require('fs');
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

test.describe('Part 1 & Part 2 E2E Verification', () => {
  test('builder starter blueprints, export/import JSON, frontend drawer, masonry, dynamic counts, URL persistence & wishlist drawer', async ({ page }) => {
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
      categories: [],
      restNonce: '',
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
      // -----------------------------------------------------------------------
      // 1. Builder UI: Blueprints Modal + Part 1/2 Controls
      // -----------------------------------------------------------------------
      await goToBuilder(page);

      const builderMeta = await page.evaluate(() => ({
        nonce: window.gridmasterBuilderData ? window.gridmasterBuilderData.nonce : '',
      }));
      expect(builderMeta.nonce).toBeTruthy();
      cleanup.restNonce = builderMeta.nonce;

      // Apply Starter Blueprint ("Editorial Metro Magazine")
      const blueprintsBtn = page.locator('.gm-blueprints-trigger-btn');
      await expect(blueprintsBtn).toBeVisible();
      await blueprintsBtn.click();

      const bpModal = page.locator('.gm-blueprints-modal');
      await expect(bpModal).toBeVisible({ timeout: 5000 });
      const metroCard = bpModal.locator('.gm-blueprint-card', { hasText: 'Editorial Metro Magazine' });
      await expect(metroCard).toBeVisible();
      await metroCard.locator('button:has-text("Use Blueprint")').click();
      await expect(bpModal).toBeHidden({ timeout: 5000 });

      // Verify Metro layout & Part 2 options in Layout tab
      await switchBuilderTab(page, 'layout');
      await expect(page.locator('.gm-layout-type-option.is-active', { hasText: /Metro/i })).toBeVisible();
      await expect(page.getByText(/Filter Placement/i).first()).toBeVisible();

      // Verify Part 1/2 options in Content tab (Author / Exclude Current / Offset / Meta Query)
      await switchBuilderTab(page, 'content');
      await expect(page.getByText(/Exclude Current Post/i).first()).toBeVisible();
      await expect(page.getByText(/Meta Query/i).first()).toBeVisible();

      // Verify Part 1/2 options in Filters tab (Taxonomy Radio, Meta Filter, Hide Empty Dynamic)
      await switchBuilderTab(page, 'filters');
      await expect(page.getByText(/Hide Empty Terms on Dynamic Recount/i).first()).toBeVisible();

      // -----------------------------------------------------------------------
      // 2. Seed Disposable Categories, Posts & Grids via REST
      // -----------------------------------------------------------------------
      const suffix = `${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 6)}`;

      const catAlpha = await restCall('POST', 'wp/v2/categories', { name: `E2E Alpha ${suffix}` });
      const catBeta = await restCall('POST', 'wp/v2/categories', { name: `E2E Beta ${suffix}` });
      expect(catAlpha.ok && catBeta.ok).toBeTruthy();
      cleanup.categories.push(catAlpha.data.id, catBeta.data.id);

      const searchNeedle = `gm_e2e_match_${suffix}`;
      const pAlpha = await restCall('POST', 'wp/v2/posts', {
        title: `E2E Post Alpha ${suffix}`,
        content: `Body content with unique token ${searchNeedle}.`,
        status: 'publish',
        categories: [catAlpha.data.id],
      });
      const pBeta = await restCall('POST', 'wp/v2/posts', {
        title: `E2E Post Beta ${suffix}`,
        content: `Body content without match ${suffix}.`,
        status: 'publish',
        categories: [catBeta.data.id],
      });
      expect(pAlpha.ok && pBeta.ok).toBeTruthy();
      cleanup.posts.push(pAlpha.data.id, pBeta.data.id);

      const cardBlockMarkup = [
        '<!-- wp:gridmaster/card {"padding":"16px","borderRadius":"10px","backgroundColor":"#ffffff"} -->',
        '<!-- wp:gridmaster/title {"tagName":"h3","isLink":true} /-->',
        `<!-- wp:gridmaster/badge {"text":"Alpha Only","gmVisibility":{"rule":"in_taxonomy","taxonomy":"category","termSlug":"${catAlpha.data.id}"}} /-->`,
        '<!-- wp:gridmaster-pro/bookmark-button {"buttonText":"Save","addedText":"Saved"} /-->',
        '<!-- /wp:gridmaster/card -->',
      ].join('\n');

      const masonryGridRes = await restCall('POST', 'gridmaster/v2/grids', {
        title: `E2E Masonry Drawer ${suffix}`,
        block_content: cardBlockMarkup,
        config: {
          query: {
            post_type: 'post',
            post__in: `${pAlpha.data.id},${pBeta.data.id}`,
            orderby: 'post__in',
            posts_per_page: 6,
          },
          facets: [
            { id: 'e2e_s', type: 'search', heading: 'Keyword', placeholder: 'Search e2e...' },
            { id: 'e2e_ms', type: 'multi-select', taxonomy: 'category', heading: 'Categories', show_count: true, hide_empty_dynamic: true },
          ],
          pagination: { type: 'numbered' },
          layout: {
            type: 'masonry',
            filter_position: 'drawer',
            drawer_button_label: 'Open Filters',
            columns: { xs: 1, sm: 2, md: 2, lg: 2, xl: 2 },
            col_gap: 16,
            row_gap: 16,
          },
        },
      });
      expect(masonryGridRes.ok).toBeTruthy();
      const masonryGridId = masonryGridRes.data.id;
      cleanup.grids.push(masonryGridId);

      // Second tiny grid using Metro + numbered pagination
      const metroGridRes = await restCall('POST', 'gridmaster/v2/grids', {
        title: `E2E Metro ${suffix}`,
        block_content: '<!-- wp:gridmaster/card --><!-- wp:gridmaster/title {"isLink":false} /--><!-- /wp:gridmaster/card -->',
        config: {
          query: { post_type: 'post', post__in: `${pAlpha.data.id},${pBeta.data.id}`, posts_per_page: 2 },
          facets: [],
          pagination: { type: 'none' },
          layout: { type: 'metro', columns: { xs: 1, sm: 2, md: 2, lg: 2, xl: 2 }, col_gap: 12, row_gap: 12 },
        },
      });
      expect(metroGridRes.ok).toBeTruthy();
      const metroGridId = metroGridRes.data.id;
      cleanup.grids.push(metroGridId);

      // -----------------------------------------------------------------------
      // 3. My Grids Admin: Export JSON & Import JSON Round-Trip
      // -----------------------------------------------------------------------
      await page.goto(`${ENV.baseURL}/wp-admin/admin.php?page=gridmaster&path=my-grids`, { waitUntil: 'domcontentloaded' });
      const exportBtn = page.locator(`.gm-action-export[data-grid-id="${masonryGridId}"]`).first();
      await exportBtn.scrollIntoViewIfNeeded();
      await expect(exportBtn).toBeVisible();

      const downloadPromise = page.waitForEvent('download');
      await exportBtn.click();
      const download = await downloadPromise;
      expect(download.suggestedFilename()).toContain('.gridmaster.json');

      const downloadedPath = await download.path();
      const exportedJson = JSON.parse(fs.readFileSync(downloadedPath, 'utf8'));
      expect(exportedJson.format).toBe('gridmaster-grid-v2');
      expect(exportedJson.grid.title).toBe(`E2E Masonry Drawer ${suffix}`);

      // Modify exported payload & import via modal
      exportedJson.grid.title = `E2E Imported ${suffix}`;
      await page.locator('#gm-import-grid-trigger').click();
      const importModal = page.locator('#gm-import-modal');
      await expect(importModal).toBeVisible();
      await page.locator('#gm-import-json-text').fill(JSON.stringify(exportedJson));

      const importRespPromise = page.waitForResponse(
        (res) => res.url().includes('/wp-json/gridmaster/v2/grids/import') && res.status() === 201,
        { timeout: 10000 }
      );
      const reloadNavPromise = page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 10000 });
      await page.locator('#gm-import-submit').click();
      const importResp = await importRespPromise;
      const importBody = await importResp.json();
      expect(importBody.imported).toBe(1);
      if (Array.isArray(importBody.ids)) {
        cleanup.grids.push(...importBody.ids);
      }
      await reloadNavPromise;
      await expect(page.getByText(`E2E Imported ${suffix}`).first()).toBeVisible();

      // -----------------------------------------------------------------------
      // 4. Frontend Page: Drawer, Masonry, Metro, Dynamic Counts, URL Reload, Cond Visibility & Wishlist Drawer
      // -----------------------------------------------------------------------
      const frontPageRes = await restCall('POST', 'wp/v2/pages', {
        title: `E2E Part2 Frontend ${suffix}`,
        status: 'publish',
        content: `[gridmaster id="${masonryGridId}"]\n\n[gridmaster_wishlist_drawer label="Saved Drawer"]\n\n[gridmaster id="${metroGridId}"]`,
      });
      expect(frontPageRes.ok).toBeTruthy();
      cleanup.pages.push(frontPageRes.data.id);

      const pageUrl = frontPageRes.data.link;
      await page.goto(pageUrl, { waitUntil: 'networkidle' });

      const gridWrap = page.locator(`#gm-grid-${masonryGridId}`);
      await expect(gridWrap).toBeVisible({ timeout: 10000 });
      await expect(gridWrap).toHaveClass(/gm-layout-masonry/);
      await expect(gridWrap).toHaveClass(/gm-filter-pos-drawer/);

      // Metro grid rendered as well
      const metroWrap = page.locator(`#gm-grid-${metroGridId}`);
      await expect(metroWrap).toHaveClass(/gm-layout-metro/);

      // Conditional visibility: Alpha post has badge, Beta post does not
      const alphaCard = gridWrap.locator(`.gm-grid-item[data-post-id="${pAlpha.data.id}"]`);
      const betaCard = gridWrap.locator(`.gm-grid-item[data-post-id="${pBeta.data.id}"]`);
      await expect(alphaCard.locator('.gm-field-badge')).toContainText('Alpha Only');
      await expect(betaCard.locator('.gm-field-badge')).toHaveCount(0);

      // Open Off-Canvas Filter Drawer
      const drawerToggle = gridWrap.locator('.gm-filter-drawer-toggle');
      await expect(drawerToggle).toBeVisible();
      await drawerToggle.click();

      const drawerPanel = gridWrap.locator(`#gm-filters-${masonryGridId}`);
      await expect(drawerPanel).toHaveClass(/is-open/);
      await expect(drawerToggle).toHaveAttribute('aria-expanded', 'true');

      // Search inside drawer -> triggers Pro dynamic facet_counts & filters items
      const searchInput = drawerPanel.locator('.gm-filter-search');
      const renderPromise = page.waitForResponse(
        (res) => res.url().includes(`/wp-json/gridmaster/v2/grids/${masonryGridId}/render`) && res.status() === 200,
        { timeout: 10000 }
      );
      await searchInput.fill(searchNeedle);
      const renderResp = await renderPromise;
      const renderJson = await renderResp.json();
      expect(renderJson.facet_counts && renderJson.facet_counts.category).toBeTruthy();

      await expect(gridWrap.locator('.gm-grid-item')).toHaveCount(1);
      await expect(alphaCard).toBeVisible();

      // Beta category pill should have 0 count & hidden class via hide_empty_dynamic
      const betaPill = drawerPanel.locator(`.gm-filter-checkbox-pill[data-term-id="${catBeta.data.id}"]`);
      await expect(betaPill).toHaveClass(/gm-term-hidden/);

      // Close drawer via Escape
      await page.keyboard.press('Escape');
      await expect(drawerPanel).not.toHaveClass(/is-open/);

      // Verify URL state persisted gm_q and survives reload via SSR hydration
      await expect(page).toHaveURL(new RegExp(`gm_q=${searchNeedle}`));
      await page.reload({ waitUntil: 'networkidle' });

      const reloadedGrid = page.locator(`#gm-grid-${masonryGridId}`);
      await expect(reloadedGrid).toHaveAttribute('data-url-hydrated', '1');
      await expect(reloadedGrid.locator('.gm-grid-item')).toHaveCount(1);
      await expect(reloadedGrid.locator('.gm-filter-search')).toHaveValue(searchNeedle);

      // Bookmark Alpha post & verify Wishlist Drawer
      const wlRoot = page.locator('.gm-wishlist-drawer-root').first();
      await expect(wlRoot).toBeVisible();
      const bmBtn = reloadedGrid.locator(`.gm-grid-item[data-post-id="${pAlpha.data.id}"] .gm-bookmark-btn`);
      await bmBtn.scrollIntoViewIfNeeded();

      const toggleBmPromise = page.waitForResponse(
        (res) => res.url().includes('admin-ajax.php') && res.status() === 200,
        { timeout: 10000 }
      );
      await bmBtn.click();
      await toggleBmPromise;
      await expect(bmBtn).toHaveClass(/is-active/);

      const wlTrigger = wlRoot.locator('.gm-wishlist-drawer-trigger');
      const wlFetchPromise = page.waitForResponse(
        (res) => res.url().includes('admin-ajax.php') && res.status() === 200,
        { timeout: 10000 }
      );
      await wlTrigger.click();
      await wlFetchPromise;

      const wlPanel = wlRoot.locator('.gm-wishlist-drawer-panel');
      await expect(wlPanel).toHaveClass(/is-open/);
      await expect(wlPanel.locator('.gm-wishlist-item', { hasText: `E2E Post Alpha ${suffix}` })).toBeVisible();

      // Remove item cleanly from wishlist drawer so user_meta doesn't retain disposable post ID
      const rmBtn = wlPanel.locator(`.gm-wishlist-remove-btn[data-post-id="${pAlpha.data.id}"]`);
      const removeBmPromise = page.waitForResponse(
        (res) => res.url().includes('admin-ajax.php') && res.status() === 200,
        { timeout: 10000 }
      );
      await rmBtn.click();
      await removeBmPromise;

      await page.keyboard.press('Escape');
      await expect(wlPanel).not.toHaveClass(/is-open/);

      expect(pageErrors).toEqual([]);
      expect(filterFatalErrors(consoleErrors)).toEqual([]);
    } finally {
      if (cleanup.restNonce) {
        for (const pid of cleanup.pages) {
          await restCall('DELETE', `wp/v2/pages/${pid}?force=true`).catch(() => {});
        }
        for (const gid of cleanup.grids) {
          await restCall('DELETE', `gridmaster/v2/grids/${gid}`).catch(() => {});
        }
        for (const pid of cleanup.posts) {
          await restCall('DELETE', `wp/v2/posts/${pid}?force=true`).catch(() => {});
        }
        for (const cid of cleanup.categories) {
          await restCall('DELETE', `wp/v2/categories/${cid}?force=true`).catch(() => {});
        }
      }
    }
  });
});

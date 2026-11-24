=== Post Grid Master — Post Grids & AJAX Filters ===
Contributors: mdshuvo, addonmaster
Tags: post grid, ajax filter, infinite scroll, pagination, taxonomy filter
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 3.4.25
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build custom WordPress post grids with a visual Gutenberg card builder, AJAX taxonomy filters, pagination, load more, and infinite scroll.

== Description ==

**Post Grid Master** (GridMaster) is a modern WordPress post grid and AJAX filtering plugin that lets you visually design custom post cards, organize content in responsive layouts, and filter posts by taxonomy or keyword — all without reloading the page.

Whether you are building a blog magazine, portfolio showcase, news directory, or custom post type catalog, Post Grid Master works seamlessly with any WordPress theme and your favorite page builder.

* [View Live Demo](https://plugins.addonmaster.com/gridmaster/)
* [Watch Video Walkthrough](https://www.youtube.com/watch?v=8Th_jp8YEk4)
* [Get Support](https://wordpress.org/support/plugin/ajax-filter-posts/)
* [Explore GridMaster Pro](https://addonmaster.com/gridmaster/)

= Visual Gutenberg Card Studio Builder (GridMaster 2.0) =

Design reusable post cards visually without writing template code:

* **Embedded Gutenberg Card Canvas:** Build custom card layouts visually inside the GridMaster builder with full drag-and-drop block control.
* **17+ Dynamic Card Blocks:** Post Title, Featured Image (with hover zoom & fallback image options), Excerpt, Read More Button, Post Date, Modified Date, Post Author, Author Avatar, Taxonomy Terms, Custom Post Meta, Comments Count, Reading Time, Post Format Icon, Social Share, Custom Badge, Shortcode, and Custom HTML.
* **Starter Blueprints & Card Pattern Library:** Start from pre-designed blueprints or save and reuse your own custom card patterns across grids.
* **Conditional Visibility:** Show or hide individual card blocks dynamically based on post data and context.
* **Live Responsive Preview Modal:** Inspect how your grid and cards adapt across Desktop, Tablet, and Mobile viewports before publishing.

= Modern Grid Layouts & Filter Placements =

* **Flexible Layout Modes:** Choose from CSS Grid, Horizontal List, True Masonry, Editorial Metro Mosaic, Accessible Carousel/Slider, 9:16 Vertical Reels, or classic pre-built grid styles.
* **AJAX Filter Facets:** Filter posts smoothly with Taxonomy Tabs, Taxonomy Dropdowns, Checkboxes, Radio Buttons, Live AJAX Search Input, and a Reset Button.
* **Adaptive Filter Placements:** Position filters at the Top, in a Sticky Left or Right Sidebar, or inside an Off-Canvas Mobile Filter Drawer.
* **Shareable Deep-Link URLs:** Filter and pagination states sync cleanly to shareable `gm_*` query parameters so visitors can bookmark or share filtered views.
* **Filter Customization:** Choose from 3 built-in filter styles, toggle the "All" button, add custom filter headings, make filter groups collapsible, or hide filters completely.

= AJAX Pagination & Loading Modes =

* **Numbered AJAX Pagination:** Navigate between pages instantly with smart ellipsis pagination and zero full-page reloads.
* **Load More Button:** Append the next batch of posts on click with customizable button behavior.
* **Infinite Scroll:** Automatically load additional posts as visitors scroll down the page.
* **Post Load Animations:** Add smooth entry animations when filtered or paginated posts appear.

= Context-Aware Query Builder =

* **Any Post Type & Taxonomy:** Query standard posts, pages, or single and multiple Custom Post Types with any registered taxonomy.
* **Precision Filtering:** Control posts per page, offset pagination, author filtering, include/exclude specific post IDs, and exclude the current singular post.
* **Dynamic Context Modes:** Automatically adapt queries to Current Archive pages or Related Posts contexts.
* **Flexible Sorting & Excerpts:** Order by date, title, author, ID, menu order, comment count, or random (ASC/DESC). Trim excerpts by word or character count from either the excerpt or main content field, and choose any registered thumbnail size.

= Native Page Builder Integrations & Shortcodes =

Embed any saved grid anywhere on your site with live previews and one-click "Edit this grid" links:

* **Gutenberg (Block Editor)** — Native Post Grid Master block with live canvas preview
* **Elementor** — Native widget with grid selector
* **Beaver Builder** — Native module
* **Bricks Builder** — Native element
* **WPBakery Page Builder** — Native element
* **Classic WordPress Widgets** — Sidebar & widget-area support
* **Shortcode Generator & Legacy Shortcodes** — Place `[gridmaster]` (plus backward-compatible `[am_post_grid]` and `[asr_ajax]`) inside posts, pages, or theme template files

= Performance, Accessibility & Portability =

* **Conditional Asset Loading:** Frontend scripts and styles are loaded only on pages where a grid is rendered.
* **Core Web Vitals & Accessibility:** Includes first-card LCP image prioritization and live screen-reader status announcements during AJAX updates.
* **Portable JSON Export & Import:** Export and import complete grid configurations as `.gridmaster.json` files to migrate designs between sites.
* **Theme Friendly & Translation Ready:** Clean, standards-compliant code compatible with any WordPress theme and multilingual plugins.

= Upgrade to Post Grid Master Pro =

Need advanced eCommerce grids, multi-facet filtering, or video feeds? [Post Grid Master Pro](https://addonmaster.com/gridmaster/) adds:

* **WooCommerce Product Grid & Merchandising Suite:** 16 Pro card blocks including Product Price, Add to Cart, Star Rating, Sale/Stock Badges, Stock Status, Sale Countdown Timer, Variation Swatches, Quick View Modal, Wishlist Bookmark & `[gridmaster_wishlist_drawer]`, Product Compare Drawer & Table, B2B Request a Quote Modal, Product Meta, and Action Bar — plus a 3rd-Party WooCommerce Extension Bridge (YITH, WPClever, TI Wishlist, and swatches plugins).
* **Advanced Pro Filter Facets & Dynamic Recounts:** Multi-Select Pills, Date Range Picker, Custom Field / Meta Filter, Numeric Meta Range Slider, WooCommerce Price Range Slider, Attribute Swatches, Star Rating Filter, Sale/Stock/Featured Toggles, Sort Dropdown, Active Filter Chips Bar, and cached Dynamic Facet Term Recounts.
* **Inline Video & 9:16 Reels Feeds:** Card Video block with click/hover inline playback, Video Lightbox player, and single-active audio/video coordination.
* **Pro Layouts, Taxonomy Media & Slider Controls:** Exclusive grid templates and filter styles, taxonomy images and colors, auto-select taxonomy/post type/initial term on archives, custom heading tags and responsive typography, per-device columns and gaps, and full slider configuration (slides to show/scroll, arrows, dots, autoplay, pause on hover, infinite loop, center mode).

= Privacy Policy & External Services =

Post Grid Master includes the [Appsero](https://appsero.com) SDK to collect optional, non-sensitive telemetry data only when a site administrator explicitly opts in via the admin notice.

Appsero SDK **does not collect any data by default**. Telemetry is gathered **only after user confirmation** to help troubleshoot issues and improve the plugin. Learn more in the [Appsero Privacy Policy](https://appsero.com/privacy-policy/).

= Need Help? =

Have questions or feature ideas? Post in the [WordPress.org support forum](https://wordpress.org/support/plugin/ajax-filter-posts/) or [submit a support ticket](https://addonmaster.com/submit-a-ticket/).

== Installation ==

= Install from WordPress Dashboard =

1. Navigate to **Plugins → Add New** in your WordPress admin dashboard.
2. Search for **Post Grid Master**.
3. Click **Install Now**, then click **Activate**.
4. Open **Post Grid Master** from the dashboard menu to create your first grid with the visual Grid Builder or Shortcode Generator.

= Manual Installation =

1. Download the plugin ZIP file and unzip it.
2. Upload the `ajax-filter-posts` folder to your `/wp-content/plugins/` directory.
3. Activate **Post Grid Master** from the **Plugins** screen in WordPress.
4. Go to **Post Grid Master** to design your grid, then embed it using the Gutenberg block, your page builder widget (Elementor, Beaver Builder, Bricks, WPBakery), or the generated shortcode.

== Frequently Asked Questions ==

= How do I create and display a post grid? =

Go to **Post Grid Master** in your WordPress dashboard, create a new grid using a Starter Blueprint or custom Gutenberg card layout, configure your query, filters, and pagination, and save. You can then insert the grid using the native Gutenberg block, Elementor/Beaver/Bricks/WPBakery widget, Classic Widget, or shortcode.

= Will Post Grid Master work with my theme? =

Yes. Post Grid Master uses scoped CSS and standard WordPress APIs so it works cleanly with both classic and block themes.

= Which page builders are supported? =

Post Grid Master provides native widgets, modules, and elements with live previews for Gutenberg (Block Editor), Elementor, Beaver Builder, Bricks Builder, WPBakery Page Builder, and Classic WordPress Widgets. You can also paste shortcodes into any other page builder or PHP template.

= Can I create grids for Custom Post Types and Custom Taxonomies? =

Yes. You can query single or multiple Custom Post Types and filter posts by any registered custom taxonomy or specific term selection.

= Can I place multiple post grids on the same page? =

Yes. Multiple independent grids can coexist on the same page, each with its own AJAX filters, search, and pagination state.

= Will this plugin slow down my website? =

No. Scripts and styles are enqueued conditionally only on pages that render a grid, and first-card images are prioritized for strong Largest Contentful Paint (LCP) performance.

= Can I hide filters or the "All" button? =

Yes. You can disable filters completely to display a clean grid, or keep filters enabled while hiding the "All" option.

= Does it support Load More and Infinite Scroll pagination? =

Yes. Numbered AJAX pagination with smart ellipsis, Load More buttons, and Infinite Scroll are all built into the free version.

= Are my older shortcodes still supported? =

Yes. Existing `[gridmaster]`, `[am_post_grid]`, and `[asr_ajax]` shortcodes remain fully supported for seamless backward compatibility.

= How can I request a feature or get support? =

Open a topic in the [WordPress.org support forum](https://wordpress.org/support/plugin/ajax-filter-posts/) or reach out via [addonmaster.com/submit-a-ticket](https://addonmaster.com/submit-a-ticket/).

== Screenshots ==

1. Backend Grid Builder and Shortcode Generator (Free & Pro)
2. Backend Live Preview and Welcome Overview
3. Key Features Overview
4. Free Features Summary
5. Pro Features Overview
6. Post Grid Master Grid & Filter Style 1 (Free)
7. Post Grid Master Grid & Filter Style 2 (Free)
8. Post Grid Master Grid & Filter Style 3 (Free)

== Changelog ==

= 3.4.25 =
* Added: GridMaster 2.0 visual Gutenberg Card Studio Builder, responsive card layouts, and live device preview modal.
* Added: Sidebar split & off-canvas mobile filter drawer (`left_sidebar`, `right_sidebar`, `drawer`), Masonry & Metro mosaic layouts, Starter Blueprints, and User-Saved Card Pattern Library.
* Added: Context-aware `Query_Builder` (`offset` pagination, multi-`post_type`, `post__in`, `post__not_in`, `exclude_current`, `current_archive`, `related_posts`), deep-link `gm_*` URL hydration, smart ellipsis pagination, and Card Block Conditional Visibility (`gmVisibility`).
* Added: Native page builder integrations for Gutenberg, Elementor, Beaver Builder, Bricks Builder, WPBakery Page Builder, and Classic Widgets.
* Added: Portable Grid JSON Export & Import (`GET /grids/<id>/export`, `POST /grids/import`), live screen-reader status announcements, and first-card LCP image prioritization.
* Security: Enforced `manage_options` capability guard, strict action allowlist, and recursive request sanitization on `gridmaster_ajax`, plus full v2 runtime packaging inventory.

= 3.4.18 =
* Improved: Release packaging with recursive file-integrity checks and verified WordPress.org deployment.
* Updated: WordPress compatibility metadata to 7.1.

= 3.4.14 =
* Fixed: Security issues.
* Fixed: Plugin Check (PCP) issues.
* Updated: Compatibility with WordPress 6.8.2.

= 3.4.13 =
* Fixed: Security issues.
* Fixed: Plugin Check (PCP) issues.
* Changed: Standardized text domain to `ajax-filter-posts`.
* Updated: Compatibility with WordPress 6.7.1.

= 3.4.9 =
* Fixed: Security issues.
* Updated: Compatibility with WordPress 6.5.x.

= 3.4.8 =
* Added: Nonce verification on post grid AJAX requests.
* Fixed: Security issues.
* Updated: Compatibility with WordPress 6.4.3.

= 3.4.7 =
* Added: Filter Heading option.
* Added: Toggle Filter Items option.
* Added: No Posts Found filter hook (`gridmaster-no-posts-found`).
* Fixed: Translatable "All" filter label and minor code improvements.

= 3.4.6 =
* Improved: Minified frontend CSS and JS assets.
* Fixed: Minor code improvements.
* Updated: Tested up to WordPress 6.4.3.

= 3.4.3 =
* Added: Excerpt length type option (Characters or Words).
* Fixed: Minor code improvements.
* Updated: Tested up to WordPress 6.3.2.

= 3.4.2 =
* Added: Thumbnail size function, submenu navigation, and Pro demo preview links.
* Fixed: Minor CSS, admin panel, and code adjustments.

= 3.4.1 =
* Fixed: "Cannot modify header information" warning and minor code issues.

= 3.4.0 =
* Changed: Rebranded from Post Grid with Ajax Filter to Post Grid Master and rebuilt from the ground up.
* Added: Visual Shortcode Generator with backend grid, filter, and pagination previews.
* Added: 2 grid layouts, 3 filter layouts, excerpt controls, and customizable Read More button text.
* Maintained: Full backward compatibility with legacy shortcodes.

= 3.3.0 =
* Updated: WordPress 6.1.1 compatibility.

= 3.2.1 =
* Added: Static initial load on page load.

= 3.1.5 =
* Fixed: Initial loading issue on page load.
* Updated: Tested up to WordPress 6.0.2.

= 3.0.4 =
* Fixed: Nonce verification issue.
* Updated: Tested up to WordPress 6.0.

= 3.0.0 =
* Added: Load More button pagination and Infinite Scroll.
* Added: Post load animation effects and term-based post restrictions.
* Fixed: General code improvements.

= 2.2.1 =
* Added: `order` and `orderby` shortcode parameters.

= 2.2.0 =
* Added: Show/hide specific category terms and toggle empty category terms.
* Updated: WordPress 5.6.2 compatibility.

= 2.1.0 =
* Updated: WordPress 5.6 compatibility.

= 2.0.4 =
* Fixed: Prevented draft posts from appearing in grids.

= 2.0.2 =
* Improved: AJAX pagination, CSS styling, and loading performance.

= 2.0.0 =
* Added: Multiple grids per page support.
* Added: Extended shortcode parameters (`posts_per_page`, `btn_all`, `show_filter`).
* Improved: AJAX and speed optimizations.

= 1.0.4 =
* Added: New grid design layout and multiple grid support.
* Improved: AJAX and performance enhancements.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 3.4.25 =
Major GridMaster 2.0 release featuring the visual Gutenberg Card Studio Builder, modern Masonry/Metro/Carousel/Reels layouts, sidebar & drawer filters, native page builder widgets, JSON import/export, and hardened AJAX security. Legacy shortcodes remain fully compatible.

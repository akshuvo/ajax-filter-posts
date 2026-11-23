<?php
/**
 * Free vs Pro Comparison View
 * High-Converting & Focusable Feature Matrix
 *
 * @package GridMaster
 */

// Direct Access is not allowed.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_pro = function_exists( 'gridmaster_is_pro' ) && gridmaster_is_pro();
$pro_url = esc_url( 'https://addonmaster.com/gridmaster/' );
$account_url = esc_url( 'https://portal.addonmaster.com/my-account/' );
?>

<div class="gm-free-vs-pro-wrap">

	<?php if ( $is_pro ) : ?>
		<div class="gm-pro-active-banner">
			<div class="gm-pro-active-content">
				<div class="gm-pro-active-icon-wrap">
					<span class="dashicons dashicons-yes-alt"></span>
				</div>
				<div>
					<strong><?php esc_html_e( 'GridMaster Pro is Active on this site!', 'ajax-filter-posts' ); ?></strong>
					<p><?php esc_html_e( 'You have unlocked all premium builder features, 20+ designer templates, advanced multi-filters, and priority updates.', 'ajax-filter-posts' ); ?></p>
				</div>
			</div>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=gridmaster&path=settings#license_activation_field' ) ); ?>" class="gm-btn gm-btn-white">
				<span class="dashicons dashicons-admin-network"></span>
				<?php esc_html_e( 'Manage License', 'ajax-filter-posts' ); ?>
			</a>
		</div>
	<?php endif; ?>

	<!-- Hero Header -->
	<div class="gm-fvp-hero">
		<div class="gm-fvp-hero-badge">
			<span class="dashicons dashicons-star-filled"></span>
			<?php esc_html_e( 'GridMaster Pro Edition', 'ajax-filter-posts' ); ?>
		</div>
		<h1 class="gm-fvp-hero-title">
			<?php esc_html_e( 'Supercharge Your Post Grids with Pro Features', 'ajax-filter-posts' ); ?>
		</h1>
		<p class="gm-fvp-hero-subtitle">
			<?php esc_html_e( 'Design visual drag-and-drop Gutenberg post cards, display any custom post type, filter content in real-time with AJAX multi-taxonomy filters, and elevate your archive pages with 20+ handcrafted presets.', 'ajax-filter-posts' ); ?>
		</p>
		<div class="gm-fvp-hero-cta">
			<a href="<?php echo esc_url( $pro_url ); ?>" target="_blank" class="gm-btn-premium gm-btn-hero">
				<span class="dashicons dashicons-cart"></span>
				<?php esc_html_e( 'Get GridMaster Pro Today', 'ajax-filter-posts' ); ?>
				<span class="dashicons dashicons-arrow-right-alt"></span>
			</a>
			<a href="#gm-comparison-table" class="gm-btn-secondary gm-btn-hero">
				<span class="dashicons dashicons-list-view"></span>
				<?php esc_html_e( 'Jump to Feature Matrix', 'ajax-filter-posts' ); ?>
			</a>
		</div>
		<div class="gm-fvp-hero-trust">
			<span><span class="dashicons dashicons-shield"></span> <?php esc_html_e( '14-Day Money-Back Guarantee', 'ajax-filter-posts' ); ?></span>
			<span><span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Automatic 1-Click Updates', 'ajax-filter-posts' ); ?></span>
			<span><span class="dashicons dashicons-lock"></span> <?php esc_html_e( '100% Risk-Free Secure Purchase', 'ajax-filter-posts' ); ?></span>
		</div>
	</div>

	<!-- 3 Value Pillars -->
	<div class="gm-fvp-pillars">
		<div class="gm-fvp-pillar-card">
			<div class="gm-pillar-icon gm-icon-indigo">
				<span class="dashicons dashicons-layout"></span>
			</div>
			<h3><?php esc_html_e( 'Visual Gutenberg Card Designer', 'ajax-filter-posts' ); ?></h3>
			<p><?php esc_html_e( 'Design every pixel of your post card inside the native WordPress block editor. Rearrange titles, featured images, dates, excerpts, badges, custom fields, and buttons with real-time live preview.', 'ajax-filter-posts' ); ?></p>
			<ul class="gm-pillar-list">
				<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Drag-and-drop card architecture', 'ajax-filter-posts' ); ?></li>
				<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Custom fonts, colors, borders & shadows', 'ajax-filter-posts' ); ?></li>
				<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Pixel-perfect responsive device controls', 'ajax-filter-posts' ); ?></li>
			</ul>
		</div>

		<div class="gm-fvp-pillar-card gm-pillar-featured">
			<div class="gm-pillar-badge"><?php esc_html_e( 'Most Popular', 'ajax-filter-posts' ); ?></div>
			<div class="gm-pillar-icon gm-icon-purple">
				<span class="dashicons dashicons-filter"></span>
			</div>
			<h3><?php esc_html_e( 'Advanced Multi-Taxonomy AJAX Filters', 'ajax-filter-posts' ); ?></h3>
			<p><?php esc_html_e( 'Let visitors filter posts, products, and portfolios instantly without reloading the page. Combine multiple categories, tags, custom taxonomies, and live search bar with zero lag.', 'ajax-filter-posts' ); ?></p>
			<ul class="gm-pillar-list">
				<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Real-time AJAX filtering without page refresh', 'ajax-filter-posts' ); ?></li>
				<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Buttons, dropdowns, pills, badges & search bar', 'ajax-filter-posts' ); ?></li>
				<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Multi-select with AND / OR criteria logic', 'ajax-filter-posts' ); ?></li>
			</ul>
		</div>

		<div class="gm-fvp-pillar-card">
			<div class="gm-pillar-icon gm-icon-emerald">
				<span class="dashicons dashicons-art"></span>
			</div>
			<h3><?php esc_html_e( '20+ Designer Layouts & Presets', 'ajax-filter-posts' ); ?></h3>
			<p><?php esc_html_e( 'Transform your site with professional layouts: equal height grids, Pinterest-style masonry, metro layouts, and responsive touch-enabled carousel sliders with smooth animations.', 'ajax-filter-posts' ); ?></p>
			<ul class="gm-pillar-list">
				<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( '20+ handcrafted designer templates', 'ajax-filter-posts' ); ?></li>
				<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Masonry, Metro, Grid, Slider & List views', 'ajax-filter-posts' ); ?></li>
				<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( '12+ modern hover and transition effects', 'ajax-filter-posts' ); ?></li>
			</ul>
		</div>
	</div>

	<!-- Comparison Table (Focusable Section) -->
	<div id="gm-comparison-table" class="gm-comparison-card">
		<div class="gm-comparison-header">
			<div class="gm-comparison-header-text">
				<div class="gm-badge-matrix">
					<span class="dashicons dashicons-performance"></span>
					<?php esc_html_e( 'Side-by-Side Comparison', 'ajax-filter-posts' ); ?>
				</div>
				<h2 class="gm-comparison-title"><?php esc_html_e( 'Free vs Pro Feature Matrix', 'ajax-filter-posts' ); ?></h2>
				<p class="gm-comparison-subtitle"><?php esc_html_e( 'Explore the powerful capabilities unlocked with GridMaster Pro. Select a category below or search for a specific feature.', 'ajax-filter-posts' ); ?></p>
			</div>
			<div class="gm-comparison-header-cta">
				<a href="<?php echo esc_url( $pro_url ); ?>" target="_blank" class="gm-btn-premium gm-btn-header-upgrade">
					<span class="dashicons dashicons-star-filled"></span>
					<?php esc_html_e( 'Upgrade to Pro Now', 'ajax-filter-posts' ); ?>
				</a>
			</div>
		</div>

		<!-- Interactive Category Filter Toolbar -->
		<div class="gm-matrix-toolbar">
			<div class="gm-matrix-category-tabs">
				<button type="button" class="gm-matrix-tab gm-tab-active" data-cat="all">
					<?php esc_html_e( 'All Features', 'ajax-filter-posts' ); ?>
					<span class="gm-tab-count">20</span>
				</button>
				<button type="button" class="gm-matrix-tab" data-cat="builder">
					<span class="dashicons dashicons-layout"></span>
					<?php esc_html_e( 'Card Builder & CPT', 'ajax-filter-posts' ); ?>
				</button>
				<button type="button" class="gm-matrix-tab" data-cat="filters">
					<span class="dashicons dashicons-filter"></span>
					<?php esc_html_e( 'AJAX Filters & Search', 'ajax-filter-posts' ); ?>
				</button>
				<button type="button" class="gm-matrix-tab" data-cat="pagination">
					<span class="dashicons dashicons-ellipsis"></span>
					<?php esc_html_e( 'Pagination & Scroll', 'ajax-filter-posts' ); ?>
				</button>
				<button type="button" class="gm-matrix-tab" data-cat="styling">
					<span class="dashicons dashicons-admin-appearance"></span>
					<?php esc_html_e( 'Styling & Effects', 'ajax-filter-posts' ); ?>
				</button>
				<button type="button" class="gm-matrix-tab" data-cat="support">
					<span class="dashicons dashicons-sos"></span>
					<?php esc_html_e( 'Updates & Support', 'ajax-filter-posts' ); ?>
				</button>
			</div>

			<div class="gm-matrix-search-box">
				<span class="dashicons dashicons-search gm-matrix-search-icon"></span>
				<input type="text" id="gm-matrix-search-input" placeholder="<?php esc_attr_e( 'Filter features (e.g. WooCommerce, ACF, Infinite Scroll)...', 'ajax-filter-posts' ); ?>" />
				<button type="button" id="gm-matrix-search-clear" title="<?php esc_attr_e( 'Clear search', 'ajax-filter-posts' ); ?>">✕</button>
			</div>
		</div>

		<!-- Focusable Feature Table -->
		<div class="gm-table-responsive gm-fvp-table-wrap">
			<table class="gm-fvp-table" id="gm-fvp-table">
				<thead>
					<tr>
						<th class="gm-col-feature">
							<div class="gm-col-feature-header">
								<span class="gm-tier-badge gm-tier-badge-feature"><?php esc_html_e( 'Feature Matrix', 'ajax-filter-posts' ); ?></span>
								<h3 class="gm-tier-title"><?php esc_html_e( 'Capability / Feature', 'ajax-filter-posts' ); ?></h3>
								<span class="gm-tier-note"><?php esc_html_e( 'Detailed breakdown of core features & limitations', 'ajax-filter-posts' ); ?></span>
							</div>
						</th>
						<th class="gm-col-free">
							<div class="gm-col-free-header">
								<span class="gm-tier-badge gm-tier-badge-free"><?php esc_html_e( 'Standard Free', 'ajax-filter-posts' ); ?></span>
								<h3 class="gm-tier-title"><?php esc_html_e( 'GridMaster Core', 'ajax-filter-posts' ); ?></h3>
								<span class="gm-tier-note"><?php esc_html_e( 'Basic grid essentials for regular posts', 'ajax-filter-posts' ); ?></span>
								<div class="gm-free-included-tag">
									<span class="dashicons dashicons-yes-alt"></span>
									<?php esc_html_e( 'Included Free', 'ajax-filter-posts' ); ?>
								</div>
							</div>
						</th>
						<th class="gm-col-pro gm-focused-col">
							<div class="gm-col-pro-header">
								<div class="gm-pro-hero-pill">
									<span class="dashicons dashicons-star-filled"></span>
									<?php esc_html_e( 'RECOMMENDED', 'ajax-filter-posts' ); ?>
								</div>
								<h3 class="gm-tier-title gm-tier-title-pro"><?php esc_html_e( 'GridMaster Pro', 'ajax-filter-posts' ); ?></h3>
								<span class="gm-tier-note gm-tier-note-pro"><?php esc_html_e( 'Unlimited potential for creators & developers', 'ajax-filter-posts' ); ?></span>
								<a href="<?php echo esc_url( $pro_url ); ?>" target="_blank" class="gm-btn-pro-col-action">
									<span class="dashicons dashicons-star-filled"></span>
									<?php esc_html_e( 'Upgrade to Pro ↗', 'ajax-filter-posts' ); ?>
								</a>
							</div>
						</th>
					</tr>
				</thead>
				<tbody>
					<!-- Category 1: Builder & CPT -->
					<tr class="gm-row-group" data-group="builder">
						<td colspan="3">
							<div class="gm-group-header-cell">
								<span class="dashicons dashicons-layout gm-group-icon"></span>
								<span class="gm-group-title"><?php esc_html_e( 'Gutenberg Card Builder & Content Types', 'ajax-filter-posts' ); ?></span>
								<span class="gm-group-badge">6 features</span>
							</div>
						</td>
					</tr>
					<tr data-cat="builder" tabindex="0">
						<td class="gm-td-feature">
							<div class="gm-feature-title-wrap">
								<strong class="gm-feature-name"><?php esc_html_e( 'Gutenberg Visual Card Builder', 'ajax-filter-posts' ); ?></strong>
								<span class="gm-feature-desc"><?php esc_html_e( 'Design custom post cards with drag & drop WordPress blocks, layout controls, and real-time live preview.', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
						<td class="gm-td-free">
							<span class="gm-val-muted"><?php esc_html_e( 'Starter Template Card', 'ajax-filter-posts' ); ?></span>
						</td>
						<td class="gm-td-pro gm-focused-cell">
							<div class="gm-pro-val-wrap">
								<span class="gm-icon-circle-check"><span class="dashicons dashicons-yes"></span></span>
								<span class="gm-pro-text"><?php esc_html_e( 'Full Visual Card Builder', 'ajax-filter-posts' ); ?></span>
								<span class="gm-highlight-tag"><?php esc_html_e( 'Visual Editor', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
					</tr>
					<tr data-cat="builder" tabindex="0">
						<td class="gm-td-feature">
							<div class="gm-feature-title-wrap">
								<strong class="gm-feature-name"><?php esc_html_e( 'Custom Post Types (CPT) Support', 'ajax-filter-posts' ); ?></strong>
								<span class="gm-feature-desc"><?php esc_html_e( 'Display custom post types: Portfolios, Case Studies, Testimonials, Recipes, Events, CPT UI & Pods.', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
						<td class="gm-td-free">
							<div class="gm-free-val-wrap">
								<span class="gm-icon-cross"><span class="dashicons dashicons-no-alt"></span></span>
								<span class="gm-val-muted"><?php esc_html_e( 'Standard Blog Posts Only', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
						<td class="gm-td-pro gm-focused-cell">
							<div class="gm-pro-val-wrap">
								<span class="gm-icon-circle-check"><span class="dashicons dashicons-yes"></span></span>
								<span class="gm-pro-text"><?php esc_html_e( 'Any Public Post Type', 'ajax-filter-posts' ); ?></span>
								<span class="gm-highlight-tag gm-tag-unlimited"><?php esc_html_e( 'Unlimited', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
					</tr>
					<tr data-cat="builder" tabindex="0">
						<td class="gm-td-feature">
							<div class="gm-feature-title-wrap">
								<strong class="gm-feature-name"><?php esc_html_e( 'WooCommerce Products Support', 'ajax-filter-posts' ); ?></strong>
								<span class="gm-feature-desc"><?php esc_html_e( 'Create dynamic product catalogs with price tags, sales badges, star ratings, and Add to Cart buttons.', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
						<td class="gm-td-free">
							<div class="gm-free-val-wrap">
								<span class="gm-icon-cross"><span class="dashicons dashicons-no-alt"></span></span>
								<span class="gm-val-muted"><?php esc_html_e( 'Not Available', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
						<td class="gm-td-pro gm-focused-cell">
							<div class="gm-pro-val-wrap">
								<span class="gm-icon-circle-check"><span class="dashicons dashicons-yes"></span></span>
								<span class="gm-pro-text"><?php esc_html_e( 'Full WooCommerce Integration', 'ajax-filter-posts' ); ?></span>
								<span class="gm-highlight-tag"><?php esc_html_e( 'WooCommerce', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
					</tr>
					<tr data-cat="builder" tabindex="0">
						<td class="gm-td-feature">
							<div class="gm-feature-title-wrap">
								<strong class="gm-feature-name"><?php esc_html_e( 'Advanced Custom Fields (ACF) & Meta', 'ajax-filter-posts' ); ?></strong>
								<span class="gm-feature-desc"><?php esc_html_e( 'Extract and display custom meta fields, ACF text, images, badges, and custom taxonomy terms on cards.', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
						<td class="gm-td-free">
							<div class="gm-free-val-wrap">
								<span class="gm-icon-cross"><span class="dashicons dashicons-no-alt"></span></span>
								<span class="gm-val-muted"><?php esc_html_e( 'Not Available', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
						<td class="gm-td-pro gm-focused-cell">
							<div class="gm-pro-val-wrap">
								<span class="gm-icon-circle-check"><span class="dashicons dashicons-yes"></span></span>
								<span class="gm-pro-text"><?php esc_html_e( 'Full ACF & Post Meta Support', 'ajax-filter-posts' ); ?></span>
								<span class="gm-highlight-tag"><?php esc_html_e( 'ACF Ready', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
					</tr>
					<tr data-cat="builder" tabindex="0">
						<td class="gm-td-feature">
							<div class="gm-feature-title-wrap">
								<strong class="gm-feature-name"><?php esc_html_e( 'Multi-Format Layout Engines', 'ajax-filter-posts' ); ?></strong>
								<span class="gm-feature-desc"><?php esc_html_e( 'Render cards in Equal Height Grid, Masonry (Pinterest style), Metro layout, Carousel Slider, or List view.', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
						<td class="gm-td-free">
							<span class="gm-val-muted"><?php esc_html_e( 'Standard Grid Only', 'ajax-filter-posts' ); ?></span>
						</td>
						<td class="gm-td-pro gm-focused-cell">
							<div class="gm-pro-val-wrap">
								<span class="gm-icon-circle-check"><span class="dashicons dashicons-yes"></span></span>
								<span class="gm-pro-text"><?php esc_html_e( 'Grid, Masonry, Metro, Slider, List', 'ajax-filter-posts' ); ?></span>
								<span class="gm-highlight-tag"><?php esc_html_e( '5 Layouts', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
					</tr>
					<tr data-cat="builder" tabindex="0">
						<td class="gm-td-feature">
							<div class="gm-feature-title-wrap">
								<strong class="gm-feature-name"><?php esc_html_e( 'Pre-Built Designer Layout Presets', 'ajax-filter-posts' ); ?></strong>
								<span class="gm-feature-desc"><?php esc_html_e( 'Instantly apply ready-made, professionally crafted post card designs with zero coding required.', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
						<td class="gm-td-free">
							<span class="gm-val-muted"><?php esc_html_e( '2 Basic Templates', 'ajax-filter-posts' ); ?></span>
						</td>
						<td class="gm-td-pro gm-focused-cell">
							<div class="gm-pro-val-wrap">
								<span class="gm-icon-circle-check"><span class="dashicons dashicons-yes"></span></span>
								<span class="gm-pro-text"><?php esc_html_e( '20+ Premium Designer Presets', 'ajax-filter-posts' ); ?></span>
								<span class="gm-highlight-tag gm-tag-unlimited"><?php esc_html_e( '20+ Presets', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
					</tr>

					<!-- Category 2: AJAX Filters & Search -->
					<tr class="gm-row-group" data-group="filters">
						<td colspan="3">
							<div class="gm-group-header-cell">
								<span class="dashicons dashicons-filter gm-group-icon"></span>
								<span class="gm-group-title"><?php esc_html_e( 'Instant AJAX Filtering & Search Controls', 'ajax-filter-posts' ); ?></span>
								<span class="gm-group-badge">5 features</span>
							</div>
						</td>
					</tr>
					<tr data-cat="filters" tabindex="0">
						<td class="gm-td-feature">
							<div class="gm-feature-title-wrap">
								<strong class="gm-feature-name"><?php esc_html_e( 'Category Live Filtering (Zero Reload)', 'ajax-filter-posts' ); ?></strong>
								<span class="gm-feature-desc"><?php esc_html_e( 'Filter posts by category on the fly without refreshing the page.', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
						<td class="gm-td-free">
							<div class="gm-free-val-wrap">
								<span class="gm-icon-circle-check gm-check-free"><span class="dashicons dashicons-yes"></span></span>
								<span class="gm-val-text"><?php esc_html_e( 'Single Taxonomy Only', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
						<td class="gm-td-pro gm-focused-cell">
							<div class="gm-pro-val-wrap">
								<span class="gm-icon-circle-check"><span class="dashicons dashicons-yes"></span></span>
								<span class="gm-pro-text"><?php esc_html_e( 'Multi-Taxonomy Live Filtering', 'ajax-filter-posts' ); ?></span>
								<span class="gm-highlight-tag"><?php esc_html_e( 'Multi-Filter', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
					</tr>
					<tr data-cat="filters" tabindex="0">
						<td class="gm-td-feature">
							<div class="gm-feature-title-wrap">
								<strong class="gm-feature-name"><?php esc_html_e( 'Custom Taxonomies, Tags & Attributes', 'ajax-filter-posts' ); ?></strong>
								<span class="gm-feature-desc"><?php esc_html_e( 'Filter by post tags, product categories, brands, locations, or any custom registered taxonomy.', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
						<td class="gm-td-free">
							<div class="gm-free-val-wrap">
								<span class="gm-icon-cross"><span class="dashicons dashicons-no-alt"></span></span>
								<span class="gm-val-muted"><?php esc_html_e( 'Not Available', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
						<td class="gm-td-pro gm-focused-cell">
							<div class="gm-pro-val-wrap">
								<span class="gm-icon-circle-check"><span class="dashicons dashicons-yes"></span></span>
								<span class="gm-pro-text"><?php esc_html_e( 'Unlimited Custom Taxonomies', 'ajax-filter-posts' ); ?></span>
								<span class="gm-highlight-tag gm-tag-unlimited"><?php esc_html_e( 'Unlimited', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
					</tr>
					<tr data-cat="filters" tabindex="0">
						<td class="gm-td-feature">
							<div class="gm-feature-title-wrap">
								<strong class="gm-feature-name"><?php esc_html_e( 'Real-Time AJAX Live Search Bar', 'ajax-filter-posts' ); ?></strong>
								<span class="gm-feature-desc"><?php esc_html_e( 'Instant search bar tied directly to grid filtering, updating results as the visitor types.', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
						<td class="gm-td-free">
							<div class="gm-free-val-wrap">
								<span class="gm-icon-cross"><span class="dashicons dashicons-no-alt"></span></span>
								<span class="gm-val-muted"><?php esc_html_e( 'Not Available', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
						<td class="gm-td-pro gm-focused-cell">
							<div class="gm-pro-val-wrap">
								<span class="gm-icon-circle-check"><span class="dashicons dashicons-yes"></span></span>
								<span class="gm-pro-text"><?php esc_html_e( 'Instant Live AJAX Search Bar', 'ajax-filter-posts' ); ?></span>
								<span class="gm-highlight-tag"><?php esc_html_e( 'Instant Search', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
					</tr>
					<tr data-cat="filters" tabindex="0">
						<td class="gm-td-feature">
							<div class="gm-feature-title-wrap">
								<strong class="gm-feature-name"><?php esc_html_e( 'Multiple Filter UI Styles', 'ajax-filter-posts' ); ?></strong>
								<span class="gm-feature-desc"><?php esc_html_e( 'Render filters as segmented buttons, select dropdowns, modern pill chips, or count badges.', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
						<td class="gm-td-free">
							<span class="gm-val-muted"><?php esc_html_e( 'Basic Button Style', 'ajax-filter-posts' ); ?></span>
						</td>
						<td class="gm-td-pro gm-focused-cell">
							<div class="gm-pro-val-wrap">
								<span class="gm-icon-circle-check"><span class="dashicons dashicons-yes"></span></span>
								<span class="gm-pro-text"><?php esc_html_e( 'Buttons, Dropdowns, Badges, Pills', 'ajax-filter-posts' ); ?></span>
								<span class="gm-highlight-tag"><?php esc_html_e( '4 Filter Styles', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
					</tr>
					<tr data-cat="filters" tabindex="0">
						<td class="gm-td-feature">
							<div class="gm-feature-title-wrap">
								<strong class="gm-feature-name"><?php esc_html_e( 'Multi-Select Criteria Logic', 'ajax-filter-posts' ); ?></strong>
								<span class="gm-feature-desc"><?php esc_html_e( 'Allow visitors to select multiple terms simultaneously using AND / OR logical operators.', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
						<td class="gm-td-free">
							<div class="gm-free-val-wrap">
								<span class="gm-icon-cross"><span class="dashicons dashicons-no-alt"></span></span>
								<span class="gm-val-muted"><?php esc_html_e( 'Single Term Only', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
						<td class="gm-td-pro gm-focused-cell">
							<div class="gm-pro-val-wrap">
								<span class="gm-icon-circle-check"><span class="dashicons dashicons-yes"></span></span>
								<span class="gm-pro-text"><?php esc_html_e( 'Supported (AND / OR Logic)', 'ajax-filter-posts' ); ?></span>
								<span class="gm-highlight-tag"><?php esc_html_e( 'Multi-Select', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
					</tr>

					<!-- Category 3: Pagination & Navigation -->
					<tr class="gm-row-group" data-group="pagination">
						<td colspan="3">
							<div class="gm-group-header-cell">
								<span class="dashicons dashicons-ellipsis gm-group-icon"></span>
								<span class="gm-group-title"><?php esc_html_e( 'Pagination & Content Loading Methods', 'ajax-filter-posts' ); ?></span>
								<span class="gm-group-badge">4 features</span>
							</div>
						</td>
					</tr>
					<tr data-cat="pagination" tabindex="0">
						<td class="gm-td-feature">
							<div class="gm-feature-title-wrap">
								<strong class="gm-feature-name"><?php esc_html_e( 'Standard Numbered Pagination', 'ajax-filter-posts' ); ?></strong>
								<span class="gm-feature-desc"><?php esc_html_e( 'Classic 1, 2, 3 page navigation with smooth AJAX post loading.', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
						<td class="gm-td-free">
							<div class="gm-free-val-wrap">
								<span class="gm-icon-circle-check gm-check-free"><span class="dashicons dashicons-yes"></span></span>
								<span class="gm-val-text"><?php esc_html_e( 'Numbered Pages', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
						<td class="gm-td-pro gm-focused-cell">
							<div class="gm-pro-val-wrap">
								<span class="gm-icon-circle-check"><span class="dashicons dashicons-yes"></span></span>
								<span class="gm-pro-text"><?php esc_html_e( 'Numbered Pages with Custom Styles', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
					</tr>
					<tr data-cat="pagination" tabindex="0">
						<td class="gm-td-feature">
							<div class="gm-feature-title-wrap">
								<strong class="gm-feature-name"><?php esc_html_e( 'AJAX "Load More" Button', 'ajax-filter-posts' ); ?></strong>
								<span class="gm-feature-desc"><?php esc_html_e( 'Load next batch of posts seamlessly with a single click, custom button label, and loading spinner.', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
						<td class="gm-td-free">
							<div class="gm-free-val-wrap">
								<span class="gm-icon-cross"><span class="dashicons dashicons-no-alt"></span></span>
								<span class="gm-val-muted"><?php esc_html_e( 'Not Available', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
						<td class="gm-td-pro gm-focused-cell">
							<div class="gm-pro-val-wrap">
								<span class="gm-icon-circle-check"><span class="dashicons dashicons-yes"></span></span>
								<span class="gm-pro-text"><?php esc_html_e( 'AJAX Load More with Custom Spinners', 'ajax-filter-posts' ); ?></span>
								<span class="gm-highlight-tag"><?php esc_html_e( 'Load More', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
					</tr>
					<tr data-cat="pagination" tabindex="0">
						<td class="gm-td-feature">
							<div class="gm-feature-title-wrap">
								<strong class="gm-feature-name"><?php esc_html_e( 'AJAX Seamless Infinite Scroll', 'ajax-filter-posts' ); ?></strong>
								<span class="gm-feature-desc"><?php esc_html_e( 'Automatically loads more cards as the visitor scrolls down the page for modern magazine experiences.', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
						<td class="gm-td-free">
							<div class="gm-free-val-wrap">
								<span class="gm-icon-cross"><span class="dashicons dashicons-no-alt"></span></span>
								<span class="gm-val-muted"><?php esc_html_e( 'Not Available', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
						<td class="gm-td-pro gm-focused-cell">
							<div class="gm-pro-val-wrap">
								<span class="gm-icon-circle-check"><span class="dashicons dashicons-yes"></span></span>
								<span class="gm-pro-text"><?php esc_html_e( 'Automated Infinite Scroll Loading', 'ajax-filter-posts' ); ?></span>
								<span class="gm-highlight-tag"><?php esc_html_e( 'Infinite Scroll', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
					</tr>
					<tr data-cat="pagination" tabindex="0">
						<td class="gm-td-feature">
							<div class="gm-feature-title-wrap">
								<strong class="gm-feature-name"><?php esc_html_e( 'Next / Previous Pagination', 'ajax-filter-posts' ); ?></strong>
								<span class="gm-feature-desc"><?php esc_html_e( 'Simple Next and Previous page navigation controls with custom arrow icons.', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
						<td class="gm-td-free">
							<div class="gm-free-val-wrap">
								<span class="gm-icon-circle-check gm-check-free"><span class="dashicons dashicons-yes"></span></span>
								<span class="gm-val-text"><?php esc_html_e( 'Supported', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
						<td class="gm-td-pro gm-focused-cell">
							<div class="gm-pro-val-wrap">
								<span class="gm-icon-circle-check"><span class="dashicons dashicons-yes"></span></span>
								<span class="gm-pro-text"><?php esc_html_e( 'Supported with Custom Buttons', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
					</tr>

					<!-- Category 4: Styling & Effects -->
					<tr class="gm-row-group" data-group="styling">
						<td colspan="3">
							<div class="gm-group-header-cell">
								<span class="dashicons dashicons-admin-appearance gm-group-icon"></span>
								<span class="gm-group-title"><?php esc_html_e( 'Styling, Typography & Hover Animations', 'ajax-filter-posts' ); ?></span>
								<span class="gm-group-badge">3 features</span>
							</div>
						</td>
					</tr>
					<tr data-cat="styling" tabindex="0">
						<td class="gm-td-feature">
							<div class="gm-feature-title-wrap">
								<strong class="gm-feature-name"><?php esc_html_e( 'Taxonomy Custom Colors & Icons', 'ajax-filter-posts' ); ?></strong>
								<span class="gm-feature-desc"><?php esc_html_e( 'Assign custom background colors and icons to individual categories and tags on card badges.', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
						<td class="gm-td-free">
							<div class="gm-free-val-wrap">
								<span class="gm-icon-cross"><span class="dashicons dashicons-no-alt"></span></span>
								<span class="gm-val-muted"><?php esc_html_e( 'Not Available', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
						<td class="gm-td-pro gm-focused-cell">
							<div class="gm-pro-val-wrap">
								<span class="gm-icon-circle-check"><span class="dashicons dashicons-yes"></span></span>
								<span class="gm-pro-text"><?php esc_html_e( 'Custom Colors & Icons Per Term', 'ajax-filter-posts' ); ?></span>
								<span class="gm-highlight-tag"><?php esc_html_e( 'Badges', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
					</tr>
					<tr data-cat="styling" tabindex="0">
						<td class="gm-td-feature">
							<div class="gm-feature-title-wrap">
								<strong class="gm-feature-name"><?php esc_html_e( 'Card Hover Animations & Transitions', 'ajax-filter-posts' ); ?></strong>
								<span class="gm-feature-desc"><?php esc_html_e( 'Image zoom, 3D tilt, overlay fade, slide-up captions, shadow lift, and glassmorphism styling.', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
						<td class="gm-td-free">
							<span class="gm-val-muted"><?php esc_html_e( 'Basic Hover Only', 'ajax-filter-posts' ); ?></span>
						</td>
						<td class="gm-td-pro gm-focused-cell">
							<div class="gm-pro-val-wrap">
								<span class="gm-icon-circle-check"><span class="dashicons dashicons-yes"></span></span>
								<span class="gm-pro-text"><?php esc_html_e( '12+ Modern Designer Animations', 'ajax-filter-posts' ); ?></span>
								<span class="gm-highlight-tag gm-tag-unlimited"><?php esc_html_e( '12+ Effects', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
					</tr>
					<tr data-cat="styling" tabindex="0">
						<td class="gm-td-feature">
							<div class="gm-feature-title-wrap">
								<strong class="gm-feature-name"><?php esc_html_e( 'Granular Responsive Breakpoint Tuning', 'ajax-filter-posts' ); ?></strong>
								<span class="gm-feature-desc"><?php esc_html_e( 'Control columns and gaps separately for Desktop (XL), Laptop (LG), Tablet (MD), Mobile Landscape (SM), and Mobile (XS).', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
						<td class="gm-td-free">
							<span class="gm-val-muted"><?php esc_html_e( 'Basic Desktop / Mobile', 'ajax-filter-posts' ); ?></span>
						</td>
						<td class="gm-td-pro gm-focused-cell">
							<div class="gm-pro-val-wrap">
								<span class="gm-icon-circle-check"><span class="dashicons dashicons-yes"></span></span>
								<span class="gm-pro-text"><?php esc_html_e( '5 Independent Breakpoint Levels', 'ajax-filter-posts' ); ?></span>
								<span class="gm-highlight-tag"><?php esc_html_e( 'Responsive', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
					</tr>

					<!-- Category 5: Updates & Support -->
					<tr class="gm-row-group" data-group="support">
						<td colspan="3">
							<div class="gm-group-header-cell">
								<span class="dashicons dashicons-sos gm-group-icon"></span>
								<span class="gm-group-title"><?php esc_html_e( 'Updates, Maintenance & Support', 'ajax-filter-posts' ); ?></span>
								<span class="gm-group-badge">2 features</span>
							</div>
						</td>
					</tr>
					<tr data-cat="support" tabindex="0">
						<td class="gm-td-feature">
							<div class="gm-feature-title-wrap">
								<strong class="gm-feature-name"><?php esc_html_e( 'Plugin Updates & New Features', 'ajax-filter-posts' ); ?></strong>
								<span class="gm-feature-desc"><?php esc_html_e( 'Automated new feature releases, performance optimizations, bugfixes, and security patches.', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
						<td class="gm-td-free">
							<span class="gm-val-muted"><?php esc_html_e( 'WordPress.org Directory', 'ajax-filter-posts' ); ?></span>
						</td>
						<td class="gm-td-pro gm-focused-cell">
							<div class="gm-pro-val-wrap">
								<span class="gm-icon-circle-check"><span class="dashicons dashicons-yes"></span></span>
								<span class="gm-pro-text"><?php esc_html_e( 'Automatic 1-Click Dashboard Updates', 'ajax-filter-posts' ); ?></span>
								<span class="gm-highlight-tag"><?php esc_html_e( 'Priority', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
					</tr>
					<tr data-cat="support" tabindex="0">
						<td class="gm-td-feature">
							<div class="gm-feature-title-wrap">
								<strong class="gm-feature-name"><?php esc_html_e( 'Customer Support & Live Assistance', 'ajax-filter-posts' ); ?></strong>
								<span class="gm-feature-desc"><?php esc_html_e( 'Direct access to senior developers and support specialists for setup guidance and troubleshooting.', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
						<td class="gm-td-free">
							<span class="gm-val-muted"><?php esc_html_e( 'Community Forum', 'ajax-filter-posts' ); ?></span>
						</td>
						<td class="gm-td-pro gm-focused-cell">
							<div class="gm-pro-val-wrap">
								<span class="gm-icon-circle-check"><span class="dashicons dashicons-yes"></span></span>
								<span class="gm-pro-text"><?php esc_html_e( 'Priority 24/7 Ticket & Live Chat Support', 'ajax-filter-posts' ); ?></span>
								<span class="gm-highlight-tag gm-tag-unlimited"><?php esc_html_e( '24/7 Priority', 'ajax-filter-posts' ); ?></span>
							</div>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>

	<!-- Upgrade Bottom Banner -->
	<div class="gm-fvp-cta-banner">
		<div class="gm-fvp-cta-content">
			<span class="gm-badge-cta"><?php esc_html_e( '100% Risk Free', 'ajax-filter-posts' ); ?></span>
			<h2><?php esc_html_e( 'Ready to Build Stunning, High-Converting Post Grids?', 'ajax-filter-posts' ); ?></h2>
			<p><?php esc_html_e( 'Join thousands of professional designers, developers, and agency owners who craft exceptional post grids with GridMaster Pro. Get instant access today with our 100% money-back guarantee.', 'ajax-filter-posts' ); ?></p>
			<div class="gm-fvp-cta-buttons">
				<a href="<?php echo esc_url( $pro_url ); ?>" target="_blank" class="gm-btn-premium gm-btn-large">
					<span class="dashicons dashicons-cart"></span>
					<?php esc_html_e( 'Upgrade to GridMaster Pro Now', 'ajax-filter-posts' ); ?>
					<span class="dashicons dashicons-arrow-right-alt"></span>
				</a>
				<a href="<?php echo esc_url( $account_url ); ?>" target="_blank" class="gm-btn-secondary gm-btn-large">
					<?php esc_html_e( 'Already Purchased? Access My Account', 'ajax-filter-posts' ); ?>
				</a>
			</div>
		</div>
	</div>

	<!-- FAQ Section -->
	<div class="gm-fvp-faq">
		<h2 class="gm-faq-title"><?php esc_html_e( 'Frequently Asked Questions', 'ajax-filter-posts' ); ?></h2>
		<div class="gm-faq-grid">
			<div class="gm-faq-item">
				<h4><?php esc_html_e( 'Will my existing grids keep working when I upgrade to Pro?', 'ajax-filter-posts' ); ?></h4>
				<p><?php esc_html_e( 'Yes, absolutely! GridMaster Pro is 100% backward-compatible with the free version. All your existing grids, shortcodes, and builder layouts will remain completely intact and instantly gain access to Pro features.', 'ajax-filter-posts' ); ?></p>
			</div>
			<div class="gm-faq-item">
				<h4><?php esc_html_e( 'Can I display WooCommerce products and Custom Post Types?', 'ajax-filter-posts' ); ?></h4>
				<p><?php esc_html_e( 'Yes! GridMaster Pro supports standard posts, pages, WooCommerce products, portfolios, testimonials, and any custom post type registered on your WordPress installation (including ACF, CPT UI, and Pods).', 'ajax-filter-posts' ); ?></p>
			</div>
			<div class="gm-faq-item">
				<h4><?php esc_html_e( 'How do updates work for GridMaster Pro?', 'ajax-filter-posts' ); ?></h4>
				<p><?php esc_html_e( 'Once you enter your license key in the Settings tab, updates appear directly inside your WordPress Plugins dashboard, just like any other plugin. You can update with a single click.', 'ajax-filter-posts' ); ?></p>
			</div>
			<div class="gm-faq-item">
				<h4><?php esc_html_e( 'What is your refund policy?', 'ajax-filter-posts' ); ?></h4>
				<p><?php esc_html_e( 'We offer an unconditional 14-day money-back guarantee. If GridMaster Pro does not meet your project needs, simply submit a ticket to our support team and we will issue a full refund promptly.', 'ajax-filter-posts' ); ?></p>
			</div>
		</div>
	</div>

</div>

<script>
(function() {
	function initMatrixInteractions() {
		var tabs = document.querySelectorAll('.gm-matrix-tab');
		var searchInput = document.getElementById('gm-matrix-search-input');
		var clearBtn = document.getElementById('gm-matrix-search-clear');
		var table = document.getElementById('gm-fvp-table');
		if (!table) return;

		var rows = table.querySelectorAll('tbody tr[data-cat]');
		var groupRows = table.querySelectorAll('tbody tr.gm-row-group');
		var activeCategory = 'all';

		function filterRows() {
			var query = searchInput ? searchInput.value.toLowerCase().trim() : '';

			if (clearBtn) {
				clearBtn.style.display = query ? 'block' : 'none';
			}

			var visibleByGroup = {};

			rows.forEach(function(row) {
				var cat = row.getAttribute('data-cat');
				var text = row.innerText.toLowerCase();

				var matchesCat = (activeCategory === 'all' || cat === activeCategory);
				var matchesSearch = !query || text.indexOf(query) !== -1;

				if (matchesCat && matchesSearch) {
					row.style.display = '';
					visibleByGroup[cat] = (visibleByGroup[cat] || 0) + 1;
				} else {
					row.style.display = 'none';
				}
			});

			groupRows.forEach(function(groupRow) {
				var groupName = groupRow.getAttribute('data-group');
				if (visibleByGroup[groupName] && visibleByGroup[groupName] > 0) {
					groupRow.style.display = '';
				} else {
					groupRow.style.display = 'none';
				}
			});
		}

		tabs.forEach(function(tab) {
			tab.addEventListener('click', function() {
				tabs.forEach(function(t) { t.classList.remove('gm-tab-active'); });
				this.classList.add('gm-tab-active');
				activeCategory = this.getAttribute('data-cat');
				filterRows();
			});
		});

		if (searchInput) {
			searchInput.addEventListener('input', filterRows);
		}

		if (clearBtn) {
			clearBtn.addEventListener('click', function() {
				if (searchInput) {
					searchInput.value = '';
					filterRows();
					searchInput.focus();
				}
			});
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initMatrixInteractions);
	} else {
		initMatrixInteractions();
	}
})();
</script>

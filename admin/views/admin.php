<?php
/**
 * Handles admin menus and shell layout.
 *
 * @package GridMaster
 */

// Direct Access is not allowed.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get path.
$raw_path   = isset( $_GET['path'] ) ? sanitize_text_field( wp_unslash( $_GET['path'] ) ) : ''; // phpcs:ignore.
$get_path   = empty( $raw_path ) ? 'my-grids' : $raw_path;
$is_pro     = function_exists( 'gridmaster_is_pro' ) && gridmaster_is_pro();
$grid_id    = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : null; // phpcs:ignore.

// Navigation Tabs.
$nav_tabs = array(
	array(
		'title'  => __( 'All Grids', 'ajax-filter-posts' ),
		'url'    => admin_url( 'admin.php?page=gridmaster' ),
		'icon'   => 'dashicons-grid-view',
		'path'   => 'my-grids',
		'target' => '',
	),
	array(
		'title'  => __( 'Add New Grid', 'ajax-filter-posts' ),
		'url'    => admin_url( 'admin.php?page=gridmaster-builder' ),
		'icon'   => 'dashicons-plus-alt2',
		'path'   => 'gridmaster-builder',
		'target' => '',
	),
	array(
		'title'      => __( 'Legacy Builder', 'ajax-filter-posts' ),
		'url'        => admin_url( 'admin.php?page=gridmaster&path=build-grid' ),
		'icon'       => 'dashicons-schedule',
		'path'       => 'build-grid',
		'target'     => '',
		'pill'       => __( 'DEPRECATED', 'ajax-filter-posts' ),
		'pill_class' => 'gm-badge-deprecated',
	),
	array(
		'title'  => __( 'Settings', 'ajax-filter-posts' ),
		'url'    => admin_url( 'admin.php?page=gridmaster&path=settings' ),
		'icon'   => 'dashicons-admin-generic',
		'path'   => 'settings',
		'target' => '',
	),
	array(
		'title'  => __( 'Free vs Pro', 'ajax-filter-posts' ),
		'url'    => admin_url( 'admin.php?page=gridmaster&path=free-vs-pro' ),
		'icon'   => 'dashicons-star-filled',
		'path'   => 'free-vs-pro',
		'target' => '',
		'pill'   => $is_pro ? __( 'PRO', 'ajax-filter-posts' ) : __( 'PRO', 'ajax-filter-posts' ),
	),
);
?>

<div class="gridmaster-wrap gm-admin-shell <?php echo esc_attr( 'gm-page-' . $get_path ); ?>" data-grid-id="<?php echo esc_attr( $grid_id ? 1 : 0 ); ?>">

	<?php if ( 'build-grid' === $get_path ) : ?>
		<!-- Legacy Builder Header Bar -->
		<div class="gm-admin-header gm-legacy-header">
			<div class="gm-admin-into">
				<div class="gm-legacy-brand">
					<div class="gm-brand-logo">
						<svg width="30" height="30" viewBox="0 0 34 34" fill="none" xmlns="http://www.w3.org/2000/svg">
							<rect width="34" height="34" rx="8" fill="url(#gm_logo_grad_legacy)"/>
							<rect x="7" y="7" width="8" height="8" rx="2" fill="white" fill-opacity="0.95"/>
							<rect x="19" y="7" width="8" height="8" rx="2" fill="white" fill-opacity="0.95"/>
							<rect x="7" y="19" width="8" height="8" rx="2" fill="white" fill-opacity="0.95"/>
							<rect x="19" y="19" width="8" height="8" rx="2" fill="white" fill-opacity="0.95"/>
							<defs>
								<linearGradient id="gm_logo_grad_legacy" x1="0" y1="0" x2="34" y2="34" gradientUnits="userSpaceOnUse">
									<stop stop-color="#4F46E5"/>
									<stop offset="1" stop-color="#7C3AED"/>
								</linearGradient>
							</defs>
						</svg>
					</div>
					<div class="gm-legacy-title-wrap">
						<div class="d-flex align-items-center gap-2">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=gridmaster' ) ); ?>" class="gm-legacy-brand-link">
								<span class="gm-legacy-app-title"><?php esc_html_e( 'GridMaster', 'ajax-filter-posts' ); ?></span>
							</a>
							<span class="gm-badge-deprecated-header"><?php esc_html_e( 'Legacy Builder', 'ajax-filter-posts' ); ?></span>
						</div>
					</div>
				</div>
			</div>

			<div class="gm-admin-toolbar">
				<?php
				$grid       = gm_get_grid( $grid_id, true );
				$grid_title = isset( $grid->title ) ? $grid->title : __( 'Sample Grid #', 'ajax-filter-posts' );
				?>
				<div class="nav-tab gm-legacy-nav-tab">
					<div class="gm-legacy-grid-title-wrapper">
						<span class="dashicons dashicons-edit gm-title-edit-icon"></span>
						<input class="gm-ignore-field gm-grid-title" type="text" name="title" form="gm-shortcode-generator" value="<?php echo esc_attr( $grid_title ); ?>" placeholder="<?php esc_attr_e( 'Enter grid title', 'ajax-filter-posts' ); ?>">
					</div>
					<div class="d-flex gap-2 align-items-center">
						<span class="spinner"></span>
						<button type="button" class="gm-btn gm-btn-has-icon gm-toggle-modal" data-modal-id="gm-embed-modal">
							<span class="dashicons dashicons-editor-code"></span> <?php esc_html_e( 'Embed', 'ajax-filter-posts' ); ?>
						</button>
						<button type="submit" form="gm-shortcode-generator" class="gm-save-grid gm-btn gm-btn-primary">
							<span class="dashicons dashicons-saved me-1"></span>
							<span class="gm-update-label"><?php esc_html_e( 'Update Grid', 'ajax-filter-posts' ); ?></span>
							<span class="gm-save-label"><?php esc_html_e( 'Save Grid', 'ajax-filter-posts' ); ?></span>
							<span class="unsaved-alert gm-tooltip gm-tooltip-end-bottom" title="<?php esc_html_e( 'You have some unsaved changes.', 'ajax-filter-posts' ); ?>"></span>
						</button>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=gridmaster' ) ); ?>" class="gm-btn gm-btn-secondary">
							<span class="dashicons dashicons-arrow-left-alt"></span> <?php esc_html_e( 'All Grids', 'ajax-filter-posts' ); ?>
						</a>
					</div>
				</div>
			</div>
		</div>
	<?php else : ?>
		<!-- Modern Unified Top Navigation & App Header -->
		<header class="gm-app-header">
			<div class="gm-header-inner">
				<div class="gm-brand-group">
					<div class="gm-brand-logo">
						<svg width="34" height="34" viewBox="0 0 34 34" fill="none" xmlns="http://www.w3.org/2000/svg">
							<rect width="34" height="34" rx="8" fill="url(#gm_logo_grad)"/>
							<rect x="7" y="7" width="8" height="8" rx="2" fill="white" fill-opacity="0.95"/>
							<rect x="19" y="7" width="8" height="8" rx="2" fill="white" fill-opacity="0.95"/>
							<rect x="7" y="19" width="8" height="8" rx="2" fill="white" fill-opacity="0.95"/>
							<rect x="19" y="19" width="8" height="8" rx="2" fill="white" fill-opacity="0.95"/>
							<defs>
								<linearGradient id="gm_logo_grad" x1="0" y1="0" x2="34" y2="34" gradientUnits="userSpaceOnUse">
									<stop stop-color="#4F46E5"/>
									<stop offset="1" stop-color="#7C3AED"/>
								</linearGradient>
							</defs>
						</svg>
					</div>
					<div class="gm-brand-info">
						<div class="gm-brand-title-row">
							<h1 class="gm-brand-title"><?php esc_html_e( 'GridMaster', 'ajax-filter-posts' ); ?></h1>
							<span class="gm-badge-version">v<?php echo esc_html( GRIDMASTER_VERSION ); ?></span>
							<?php if ( $is_pro ) : ?>
								<span class="gm-badge-pill gm-badge-pro"><?php esc_html_e( 'PRO ACTIVE', 'ajax-filter-posts' ); ?></span>
							<?php else : ?>
								<span class="gm-badge-pill gm-badge-free"><?php esc_html_e( 'FREE', 'ajax-filter-posts' ); ?></span>
							<?php endif; ?>
						</div>
						<p class="gm-brand-tagline"><?php esc_html_e( 'Next-generation post grid builder with visual card designer & live AJAX filters.', 'ajax-filter-posts' ); ?></p>
					</div>
				</div>

				<div class="gm-header-actions">
					<a href="<?php echo esc_url( 'https://addonmaster.com/gridmaster/' ); ?>" target="_blank" class="gm-header-link" title="<?php esc_attr_e( 'View Documentation', 'ajax-filter-posts' ); ?>">
						<span class="dashicons dashicons-book"></span>
						<span class="gm-link-text"><?php esc_html_e( 'Docs & Tutorials', 'ajax-filter-posts' ); ?></span>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=gridmaster-builder' ) ); ?>" class="gm-btn-header-primary">
						<span class="dashicons dashicons-plus-alt2"></span>
						<span><?php esc_html_e( 'Add New Grid', 'ajax-filter-posts' ); ?></span>
					</a>
				</div>
			</div>

			<!-- Sleek Horizontal Segmented Tabs -->
			<div class="gm-nav-container">
				<nav class="gm-nav-tabs" role="tablist">
					<?php foreach ( $nav_tabs as $nav_tab ) :
						$is_active = ( $get_path === $nav_tab['path'] );
						?>
						<a href="<?php echo esc_url( $nav_tab['url'] ); ?>" class="gm-nav-item <?php echo $is_active ? 'gm-nav-active' : ''; ?> <?php echo 'free-vs-pro' === $nav_tab['path'] ? 'gm-nav-pro-item' : ''; ?>" role="tab" aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>">
							<span class="dashicons <?php echo esc_attr( $nav_tab['icon'] ); ?>"></span>
							<span class="gm-nav-label"><?php echo esc_html( $nav_tab['title'] ); ?></span>
							<?php if ( ! empty( $nav_tab['pill'] ) ) : ?>
								<span class="gm-nav-pill <?php echo isset( $nav_tab['pill_class'] ) ? esc_attr( $nav_tab['pill_class'] ) : ( $is_pro ? 'gm-nav-pill-active' : '' ); ?>">
									<?php echo esc_html( $nav_tab['pill'] ); ?>
								</span>
							<?php endif; ?>
						</a>
					<?php endforeach; ?>
				</nav>
			</div>
		</header>
	<?php endif; ?>

	<!-- Main Body Content Canvas -->
	<main class="gm-admin-content">
		<?php
		switch ( $get_path ) {
			case 'build-grid':
				$file_path = GRIDMASTER_PATH . '/admin/views/build-grid.php';
				break;
			case 'templates':
				$file_path = GRIDMASTER_PATH . '/admin/views/templates.php';
				break;
			case 'settings':
				$file_path = GRIDMASTER_PATH . '/admin/views/settings.php';
				break;
			case 'free-vs-pro':
				$file_path = GRIDMASTER_PATH . '/admin/views/free-vs-pro.php';
				break;
			case 'support':
				$file_path = GRIDMASTER_PATH . '/admin/views/support.php';
				break;
			case 'my-grids':
			default:
				$file_path = GRIDMASTER_PATH . '/admin/views/my-grids.php';
				break;
		}

		if ( file_exists( $file_path ) ) {
			require_once $file_path;
		} else {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Requested view file not found.', 'ajax-filter-posts' ) . '</p></div>';
		}
		?>
	</main>
</div>

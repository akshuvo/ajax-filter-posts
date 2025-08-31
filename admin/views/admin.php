<?php
/**
 * Handles admin menus
 */

// Direct Access is not allowed.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get path.
$get_path = isset( $_GET['path'] ) ? sanitize_text_field( wp_unslash( $_GET['path'] ) ) : ''; // phpcs:ignore.

// Grid id.
$grid_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : null; // phpcs:ignore.

// Navigation Tabs.
$nav_tabs = array(
	array(
		'title'  => __( 'Welcome', 'ajax-filter-posts'  ),
		'url'    => admin_url( 'admin.php?page=gridmaster' ),
		'icon'   => 'dashicons dashicons-admin-home',
		'path'   => '',
		'target' => '',
	),
	// array(
	// 	'title'  => __( 'My Grids', 'ajax-filter-posts'  ),
	// 	'url'    => admin_url( 'admin.php?page=gridmaster&path=my-grids' ),
	// 	'icon'   => 'dashicons dashicons-layout',
	// 	'path'   => 'my-grids',
	// 	'target' => '',
	// ),
	// [
	// 'title' => __( 'Templates', 'ajax-filter-posts'  ),
	// 'url'   => admin_url( 'admin.php?page=gridmaster&path=templates' ),
	// 'icon'  => 'dashicons dashicons-layout',
	// 'path' => 'templates',
	// 'target' => '',
	// ],
	array(
		'title'  => __( 'Grid Builder', 'ajax-filter-posts'  ),
		'url'    => admin_url( 'admin.php?page=gridmaster&path=build-grid' ),
		'icon'   => 'dashicons dashicons-schedule',
		'path'   => 'build-grid',
		'target' => '',
	),
	array(
		'title'  => __( 'Settings', 'ajax-filter-posts'  ),
		'url'    => admin_url( 'admin.php?page=gridmaster&path=settings' ),
		'icon'   => 'dashicons dashicons-admin-generic',
		'path'   => 'settings',
		'target' => '',
	),
	array(
		'title'  => __( 'Free vs Pro', 'ajax-filter-posts'  ),
		'url'    => gridmaster_website_url( 'gridmaster/free-vs-pro/' ),
		'icon'   => 'dashicons dashicons-star-filled',
		'path'   => 'free-vs-pro',
		'target' => '_blank',
	),
);
?>
<div class="gridmaster-wrap <?php echo esc_attr( 'gm-page-' . $get_path ); ?>" data-grid-id="<?php echo esc_attr( $grid_id ? 1 : 0 ); ?>">
	<div class="gm-admin-header">
		<div class="gm-admin-into">
			<h2><span class="dashicons dashicons-forms me-2"></span> <?php esc_html_e( 'GridMaster', 'ajax-filter-posts'  ); ?></h2>
			<p><?php esc_html_e( 'Your ultimate tool for crafting visually stunning, customizable, and user-friendly post grids with ease.', 'ajax-filter-posts'  ); ?></p>
		</div>

		<div class="gm-admin-toolbar">
			<?php
			if ( 'build-grid' === $get_path ) :

				// Get grid.
				$grid = gm_get_grid( $grid_id, true );

				// Grid title.
				$grid_title = isset( $grid->title ) ? $grid->title : __( 'Sample Grid #', 'ajax-filter-posts'  );
				?>
				<div class="nav-tab">
					<input class="gm-ignore-field gm-grid-title" type="text" name="title" form="gm-shortcode-generator" value="<?php echo esc_attr( $grid_title ); ?>" placeholder="<?php esc_attr_e( 'Enter grid title', 'ajax-filter-posts'  ); ?>">
					<div class="d-flex gap-1">
						<span class="spinner"></span>
						<button type="button" class="gm-btn gm-btn-has-icon gm-toggle-modal" data-modal-id="gm-embed-modal"><span class="dashicons dashicons-editor-code"></span> <?php esc_html_e( 'Embed', 'ajax-filter-posts'  ); ?></button>
						<!-- <button type="submit" class="gm-save-grid gm-btn gm-btn-fill">
							<div class="gm-update-label"><?php esc_html_e( 'Update Grid', 'ajax-filter-posts'  ); ?></div>
							<div class="gm-save-label"><?php esc_html_e( 'Save Grid', 'ajax-filter-posts'  ); ?></div>
							<span class="unsaved-alert gm-tooltip gm-tooltip-end-bottom" title="<?php esc_html_e( 'You have some unsaved changes.', 'ajax-filter-posts'  ); ?>"></span>
						</button> -->
					</div>
				</div>
			<?php else : ?>
				<nav class="nav-tab-wrapper woo-nav-tab-wrapper">
				<?php foreach ( $nav_tabs as $nav_tab ) : ?>
					<a href="<?php echo esc_url( $nav_tab['url'] ); ?>" class="nav-tab <?php echo $get_path === $nav_tab['path'] ? 'nav-tab-active' : ''; ?>" target="<?php echo esc_attr( $nav_tab['target'] ); ?>">
						<span class="<?php echo esc_attr( $nav_tab['icon'] ); ?>"></span>
						<?php echo esc_html( $nav_tab['title'] ); ?>
					</a>
				<?php endforeach; ?>
				</nav>
			<?php endif; ?>
	   
		</div>
	</div>
	<div class="gm-admin-content">
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
			case 'support':
				$file_path = GRIDMASTER_PATH . '/admin/views/support.php';
				break;
			case 'my-grids':
				$file_path = GRIDMASTER_PATH . '/admin/views/my-grids.php';
				break;
			default:
				$file_path = GRIDMASTER_PATH . '/admin/views/welcome.php';
				break;
		}

		if ( file_exists( $file_path ) ) {
			require_once $file_path;
		}
		?>
	</div>
</div>

<?php
/**
 * All Grids Dashboard View
 *
 * @package GridMaster
 */

use GridMaster\Storage\Grid_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Handle GET actions (delete, duplicate, migrate, rollback) with nonces.
if ( isset( $_GET['gm_action'] ) && check_admin_referer( 'gm_grid_action' ) ) {
	$action  = sanitize_key( $_GET['gm_action'] );
	$grid_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

	if ( $grid_id > 0 ) {
		if ( 'delete' === $action ) {
			Grid_Repository::delete( $grid_id );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Grid deleted successfully.', 'ajax-filter-posts' ) . '</p></div>';
		} elseif ( 'duplicate' === $action ) {
			$new_id = Grid_Repository::duplicate( $grid_id );
			if ( ! is_wp_error( $new_id ) ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Grid duplicated successfully.', 'ajax-filter-posts' ) . '</p></div>';
			}
		} elseif ( 'migrate' === $action ) {
			if ( ! class_exists( 'GridMaster\Migration\Legacy_Migrator' ) ) {
				require_once GRIDMASTER_PATH . '/inc/Migration/Legacy_Migrator.php';
			}
			$migrated = \GridMaster\Migration\Legacy_Migrator::migrate( $grid_id );
			if ( ! is_wp_error( $migrated ) ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Legacy grid migrated to GridMaster 2.0 successfully!', 'ajax-filter-posts' ) . '</p></div>';
			}
		} elseif ( 'rollback' === $action ) {
			if ( ! class_exists( 'GridMaster\Migration\Legacy_Migrator' ) ) {
				require_once GRIDMASTER_PATH . '/inc/Migration/Legacy_Migrator.php';
			}
			\GridMaster\Migration\Legacy_Migrator::rollback( $grid_id );
			echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__( 'Grid rolled back to legacy renderer.', 'ajax-filter-posts' ) . '</p></div>';
		}
	}
}

// Query parameters.
$page          = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
$search        = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$status_filter = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';

$grids_data = Grid_Repository::list_grids( array(
	'per_page' => 20,
	'page'     => $page,
	'search'   => $search,
	'status'   => $status_filter,
) );

$items = $grids_data['items'];
$total = $grids_data['total'];
$pages = $grids_data['pages'];

// Metric counts.
global $wpdb;
$table_name            = Grid_Repository::table();
$counts_raw            = $wpdb->get_results( "SELECT migration_status, COUNT(*) as cnt FROM {$table_name} GROUP BY migration_status", OBJECT_K );
$total_grids_count     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
$v2_grids_count        = isset( $counts_raw['new_builder'] ) ? (int) $counts_raw['new_builder']->cnt : 0;
$converted_grids_count = isset( $counts_raw['converted'] ) ? (int) $counts_raw['converted']->cnt : 0;
$legacy_grids_count    = isset( $counts_raw['legacy'] ) ? (int) $counts_raw['legacy']->cnt : 0;
$is_pro                = function_exists( 'gridmaster_is_pro' ) && gridmaster_is_pro();
?>

<div class="gm-dashboard-wrap">

	<!-- Metric Summary KPI Cards -->
	<div class="gm-metrics-row">
		<div class="gm-metric-card">
			<div class="gm-metric-icon gm-icon-indigo">
				<span class="dashicons dashicons-grid-view"></span>
			</div>
			<div class="gm-metric-content">
				<span class="gm-metric-label"><?php esc_html_e( 'Total Grids', 'ajax-filter-posts' ); ?></span>
				<div class="gm-metric-value-row">
					<span class="gm-metric-number"><?php echo esc_html( $total_grids_count ); ?></span>
					<span class="gm-metric-pill gm-pill-slate"><?php esc_html_e( 'Active', 'ajax-filter-posts' ); ?></span>
				</div>
			</div>
		</div>

		<div class="gm-metric-card">
			<div class="gm-metric-icon gm-icon-emerald">
				<span class="dashicons dashicons-block-default"></span>
			</div>
			<div class="gm-metric-content">
				<span class="gm-metric-label"><?php esc_html_e( 'Gutenberg v2 Grids', 'ajax-filter-posts' ); ?></span>
				<div class="gm-metric-value-row">
					<span class="gm-metric-number"><?php echo esc_html( $v2_grids_count ); ?></span>
					<span class="gm-metric-pill gm-pill-emerald"><?php esc_html_e( 'Modern Engine', 'ajax-filter-posts' ); ?></span>
				</div>
			</div>
		</div>

		<div class="gm-metric-card">
			<div class="gm-metric-icon gm-icon-amber">
				<span class="dashicons dashicons-update"></span>
			</div>
			<div class="gm-metric-content">
				<span class="gm-metric-label"><?php esc_html_e( 'Converted / Legacy', 'ajax-filter-posts' ); ?></span>
				<div class="gm-metric-value-row">
					<span class="gm-metric-number"><?php echo esc_html( $converted_grids_count + $legacy_grids_count ); ?></span>
					<?php if ( $legacy_grids_count > 0 ) : ?>
						<span class="gm-metric-pill gm-pill-amber"><?php printf( esc_html__( '%d need migrate', 'ajax-filter-posts' ), $legacy_grids_count ); ?></span>
					<?php else : ?>
						<span class="gm-metric-pill gm-pill-emerald"><?php esc_html_e( 'Modernized', 'ajax-filter-posts' ); ?></span>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<div class="gm-metric-card gm-metric-card-cta">
			<div class="gm-metric-icon gm-icon-purple">
				<span class="dashicons dashicons-star-filled"></span>
			</div>
			<div class="gm-metric-content">
				<span class="gm-metric-label"><?php esc_html_e( 'Edition Status', 'ajax-filter-posts' ); ?></span>
				<div class="gm-metric-value-row">
					<span class="gm-metric-number-text"><?php echo $is_pro ? esc_html__( 'Pro Active', 'ajax-filter-posts' ) : esc_html__( 'Free Tier', 'ajax-filter-posts' ); ?></span>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=gridmaster&path=free-vs-pro' ) ); ?>" class="gm-metric-action-link">
						<?php echo $is_pro ? esc_html__( 'Features', 'ajax-filter-posts' ) : esc_html__( 'Upgrade ↗', 'ajax-filter-posts' ); ?>
					</a>
				</div>
			</div>
		</div>
	</div>

	<!-- Control Toolbar: Filter Tabs + Search -->
	<div class="gm-control-toolbar">
		<div class="gm-filter-pills">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=gridmaster' ) ); ?>" class="gm-filter-pill <?php echo empty( $status_filter ) ? 'gm-filter-active' : ''; ?>">
				<?php esc_html_e( 'All Grids', 'ajax-filter-posts' ); ?>
				<span class="gm-pill-count"><?php echo esc_html( $total_grids_count ); ?></span>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=gridmaster&status=new_builder' ) ); ?>" class="gm-filter-pill <?php echo 'new_builder' === $status_filter ? 'gm-filter-active' : ''; ?>">
				<?php esc_html_e( 'Gutenberg v2', 'ajax-filter-posts' ); ?>
				<span class="gm-pill-count"><?php echo esc_html( $v2_grids_count ); ?></span>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=gridmaster&status=converted' ) ); ?>" class="gm-filter-pill <?php echo 'converted' === $status_filter ? 'gm-filter-active' : ''; ?>">
				<?php esc_html_e( 'Converted v2', 'ajax-filter-posts' ); ?>
				<span class="gm-pill-count"><?php echo esc_html( $converted_grids_count ); ?></span>
			</a>
			<?php if ( $legacy_grids_count > 0 ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=gridmaster&status=legacy' ) ); ?>" class="gm-filter-pill gm-filter-warning <?php echo 'legacy' === $status_filter ? 'gm-filter-active' : ''; ?>">
					<?php esc_html_e( 'Legacy 1.x', 'ajax-filter-posts' ); ?>
					<span class="gm-pill-count"><?php echo esc_html( $legacy_grids_count ); ?></span>
				</a>
			<?php endif; ?>
		</div>

		<div class="gm-toolbar-actions">
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="gm-search-form">
				<input type="hidden" name="page" value="gridmaster" />
				<?php if ( ! empty( $status_filter ) ) : ?>
					<input type="hidden" name="status" value="<?php echo esc_attr( $status_filter ); ?>" />
				<?php endif; ?>
				<div class="gm-search-input-wrap">
					<span class="dashicons dashicons-search gm-search-icon"></span>
					<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search grids by title...', 'ajax-filter-posts' ); ?>" class="gm-search-input" />
					<?php if ( ! empty( $search ) ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=gridmaster' ) ); ?>" class="gm-search-clear" title="<?php esc_attr_e( 'Clear search', 'ajax-filter-posts' ); ?>">✕</a>
					<?php endif; ?>
				</div>
			</form>

			<button type="button" id="gm-import-grid-trigger" class="gm-btn gm-btn-secondary gm-btn-has-icon">
				<span class="dashicons dashicons-upload"></span>
				<span><?php esc_html_e( 'Import JSON', 'ajax-filter-posts' ); ?></span>
			</button>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=gridmaster-builder' ) ); ?>" class="gm-btn gm-btn-primary gm-btn-has-icon">
				<span class="dashicons dashicons-plus-alt2"></span>
				<span><?php esc_html_e( 'Add New Grid', 'ajax-filter-posts' ); ?></span>
			</a>
		</div>
	</div>

	<!-- Grids Table or Empty State -->
	<?php if ( ! empty( $items ) ) : ?>
		<div class="gm-table-container">
			<table class="gm-data-table">
				<thead>
					<tr>
						<th class="gm-th-id"><?php esc_html_e( 'ID', 'ajax-filter-posts' ); ?></th>
						<th class="gm-th-title"><?php esc_html_e( 'Grid Title & Layout', 'ajax-filter-posts' ); ?></th>
						<th class="gm-th-shortcode"><?php esc_html_e( 'Shortcode', 'ajax-filter-posts' ); ?></th>
						<th class="gm-th-status"><?php esc_html_e( 'Builder Engine', 'ajax-filter-posts' ); ?></th>
						<th class="gm-th-updated"><?php esc_html_e( 'Last Updated', 'ajax-filter-posts' ); ?></th>
						<th class="gm-th-actions"><?php esc_html_e( 'Actions', 'ajax-filter-posts' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $items as $grid ) :
						$shortcode    = '[gridmaster id="' . $grid->id . '"]';
						$is_legacy    = ( 'legacy' === $grid->migration_status );
						$is_converted = ( 'converted' === $grid->migration_status );
						$edit_url     = $is_legacy
							? admin_url( 'admin.php?page=gridmaster&path=build-grid&id=' . $grid->id )
							: admin_url( 'admin.php?page=gridmaster-builder&id=' . $grid->id );
						$display_title = ! empty( $grid->title ) ? $grid->title : sprintf( __( 'Grid #%d', 'ajax-filter-posts' ), $grid->id );
						?>
						<tr class="gm-grid-row">
							<td class="gm-cell-id">
								<span class="gm-id-badge">#<?php echo esc_html( $grid->id ); ?></span>
							</td>

							<td class="gm-cell-title">
								<div class="gm-title-wrap">
									<a href="<?php echo esc_url( $edit_url ); ?>" class="gm-grid-name" title="<?php esc_attr_e( 'Click to edit grid', 'ajax-filter-posts' ); ?>">
										<?php echo esc_html( $display_title ); ?>
									</a>
									<span class="gm-grid-meta">
										<?php if ( $is_legacy ) : ?>
											<span class="dashicons dashicons-schedule"></span> <?php esc_html_e( 'Legacy 1.x Renderer', 'ajax-filter-posts' ); ?>
										<?php else : ?>
											<span class="dashicons dashicons-layout"></span> <?php esc_html_e( 'Gutenberg Post Card Builder', 'ajax-filter-posts' ); ?>
										<?php endif; ?>
									</span>
								</div>
							</td>

							<td class="gm-cell-shortcode">
								<div class="gm-shortcode-chip-wrap">
									<code class="gm-code-snippet" title="<?php esc_attr_e( 'Click Copy to clipboard', 'ajax-filter-posts' ); ?>"><?php echo esc_html( $shortcode ); ?></code>
									<button type="button" class="gm-btn-copy-chip gm-copy-btn" data-clipboard="<?php echo esc_attr( $shortcode ); ?>" title="<?php esc_attr_e( 'Copy shortcode to clipboard', 'ajax-filter-posts' ); ?>">
										<span class="dashicons dashicons-clipboard"></span>
										<span class="gm-copy-text"><?php esc_html_e( 'Copy', 'ajax-filter-posts' ); ?></span>
									</button>
								</div>
							</td>

							<td class="gm-cell-status">
								<?php if ( $is_legacy ) : ?>
									<span class="gm-status-tag gm-tag-legacy">
										<span class="gm-status-dot"></span>
										<?php esc_html_e( 'Legacy 1.x', 'ajax-filter-posts' ); ?>
									</span>
								<?php elseif ( $is_converted ) : ?>
									<span class="gm-status-tag gm-tag-converted">
										<span class="gm-status-dot"></span>
										<?php esc_html_e( 'Converted v2', 'ajax-filter-posts' ); ?>
									</span>
								<?php else : ?>
									<span class="gm-status-tag gm-tag-v2">
										<span class="gm-status-dot"></span>
										<?php esc_html_e( 'Gutenberg v2', 'ajax-filter-posts' ); ?>
									</span>
								<?php endif; ?>
							</td>

							<td class="gm-cell-updated">
								<div class="gm-date-wrap">
									<span class="dashicons dashicons-calendar-alt"></span>
									<time datetime="<?php echo esc_attr( $grid->updated_at ); ?>">
										<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $grid->updated_at ) ) ); ?>
									</time>
								</div>
							</td>

							<td class="gm-cell-actions">
								<div class="gm-action-buttons">
									<a href="<?php echo esc_url( $edit_url ); ?>" class="gm-action-btn gm-action-edit" title="<?php esc_attr_e( 'Edit in Builder', 'ajax-filter-posts' ); ?>">
										<span class="dashicons dashicons-edit"></span>
										<span><?php esc_html_e( 'Edit', 'ajax-filter-posts' ); ?></span>
									</a>

									<div class="gm-action-icon-group">
										<?php if ( $is_legacy ) : ?>
											<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=gridmaster&path=my-grids&gm_action=migrate&id=' . $grid->id ), 'gm_grid_action' ) ); ?>" class="gm-action-icon-btn gm-action-migrate" title="<?php esc_attr_e( 'Convert to Gutenberg v2 Builder', 'ajax-filter-posts' ); ?>" aria-label="<?php esc_attr_e( 'Migrate to Gutenberg v2', 'ajax-filter-posts' ); ?>">
												<span class="dashicons dashicons-update"></span>
											</a>
										<?php elseif ( $is_converted ) : ?>
											<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=gridmaster&path=my-grids&gm_action=rollback&id=' . $grid->id ), 'gm_grid_action' ) ); ?>" class="gm-action-icon-btn gm-action-rollback" onclick="return confirm('<?php esc_attr_e( 'Roll back this grid to the legacy renderer?', 'ajax-filter-posts' ); ?>');" title="<?php esc_attr_e( 'Roll back to legacy renderer', 'ajax-filter-posts' ); ?>" aria-label="<?php esc_attr_e( 'Rollback to legacy renderer', 'ajax-filter-posts' ); ?>">
												<span class="dashicons dashicons-undo"></span>
											</a>
										<?php endif; ?>

										<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=gridmaster&path=my-grids&gm_action=duplicate&id=' . $grid->id ), 'gm_grid_action' ) ); ?>" class="gm-action-icon-btn gm-action-duplicate" title="<?php esc_attr_e( 'Duplicate Grid', 'ajax-filter-posts' ); ?>" aria-label="<?php esc_attr_e( 'Duplicate Grid', 'ajax-filter-posts' ); ?>">
											<span class="dashicons dashicons-admin-page"></span>
										</a>
										<button type="button" class="gm-action-icon-btn gm-action-export" data-grid-id="<?php echo esc_attr( $grid->id ); ?>" data-grid-slug="<?php echo esc_attr( sanitize_title( $grid->title ? $grid->title : 'grid-' . $grid->id ) ); ?>" title="<?php esc_attr_e( 'Export JSON', 'ajax-filter-posts' ); ?>" aria-label="<?php esc_attr_e( 'Export JSON', 'ajax-filter-posts' ); ?>">
											<span class="dashicons dashicons-download"></span>
										</button>

										<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=gridmaster&path=my-grids&gm_action=delete&id=' . $grid->id ), 'gm_grid_action' ) ); ?>" class="gm-action-icon-btn gm-action-delete" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this grid? This action cannot be undone.', 'ajax-filter-posts' ); ?>');" title="<?php esc_attr_e( 'Delete Grid', 'ajax-filter-posts' ); ?>" aria-label="<?php esc_attr_e( 'Delete Grid', 'ajax-filter-posts' ); ?>">
											<span class="dashicons dashicons-trash"></span>
										</a>
									</div>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( $pages > 1 ) : ?>
				<div class="gm-pagination-bar">
					<span class="gm-pagination-info">
						<?php printf( esc_html__( 'Showing page %1$d of %2$d (%3$d total grids)', 'ajax-filter-posts' ), $page, $pages, $total ); ?>
					</span>
					<div class="gm-pagination-links">
						<?php
						echo paginate_links( array(
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'prev_text' => '<span class="dashicons dashicons-arrow-left-alt2"></span>',
							'next_text' => '<span class="dashicons dashicons-arrow-right-alt2"></span>',
							'total'     => $pages,
							'current'   => $page,
						) );
						?>
					</div>
				</div>
			<?php endif; ?>
		</div>
	<?php else : ?>
		<div class="gm-empty-state-card">
			<div class="gm-empty-illustration">
				<svg width="80" height="80" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg">
					<rect width="80" height="80" rx="16" fill="#EEF2FF"/>
					<rect x="18" y="18" width="20" height="20" rx="4" fill="#818CF8" fill-opacity="0.4"/>
					<rect x="42" y="18" width="20" height="20" rx="4" fill="#C7D2FE"/>
					<rect x="18" y="42" width="20" height="20" rx="4" fill="#C7D2FE"/>
					<rect x="42" y="42" width="20" height="20" rx="4" fill="#818CF8" fill-opacity="0.4"/>
					<path d="M40 32V48M32 40H48" stroke="#4F46E5" stroke-width="2.5" stroke-linecap="round"/>
				</svg>
			</div>
			<h2><?php esc_html_e( 'No Post Grids Found', 'ajax-filter-posts' ); ?></h2>
			<p>
				<?php if ( ! empty( $search ) || ! empty( $status_filter ) ) : ?>
					<?php esc_html_e( 'No grids matched your active filter or search terms. Try clearing filters to view all grids.', 'ajax-filter-posts' ); ?>
				<?php else : ?>
					<?php esc_html_e( 'Get started by creating your first post grid using our visual drag-and-drop Gutenberg card builder.', 'ajax-filter-posts' ); ?>
				<?php endif; ?>
			</p>
			<div class="gm-empty-actions">
				<?php if ( ! empty( $search ) || ! empty( $status_filter ) ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=gridmaster' ) ); ?>" class="gm-btn gm-btn-secondary">
						<?php esc_html_e( 'Clear Filter & Search', 'ajax-filter-posts' ); ?>
					</a>
				<?php endif; ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=gridmaster-builder' ) ); ?>" class="gm-btn gm-btn-primary gm-btn-hero">
					<span class="dashicons dashicons-plus-alt2"></span>
					<?php esc_html_e( 'Create Your First Grid', 'ajax-filter-posts' ); ?>
				</a>
			</div>
		</div>
	<?php endif; ?>

	<!-- Resource & Helper Cards -->
	<div class="gm-resources-row">
		<div class="gm-resource-card">
			<div class="gm-resource-icon">
				<span class="dashicons dashicons-layout"></span>
			</div>
			<div class="gm-resource-body">
				<h4><?php esc_html_e( 'Visual Gutenberg Card Designer', 'ajax-filter-posts' ); ?></h4>
				<p><?php esc_html_e( 'Customize card layouts with native WordPress block components. Add title, image, meta, custom fields, and buttons.', 'ajax-filter-posts' ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=gridmaster-builder' ) ); ?>" class="gm-resource-link">
					<?php esc_html_e( 'Launch Card Builder →', 'ajax-filter-posts' ); ?>
				</a>
			</div>
		</div>

		<div class="gm-resource-card">
			<div class="gm-resource-icon">
				<span class="dashicons dashicons-shortcode"></span>
			</div>
			<div class="gm-resource-body">
				<h4><?php esc_html_e( 'Embed Anywhere with Shortcodes', 'ajax-filter-posts' ); ?></h4>
				<p><?php esc_html_e( 'Paste [gridmaster id="..."] into any post, page, Elementor layout, or block editor template to render your grids.', 'ajax-filter-posts' ); ?></p>
				<a href="<?php echo esc_url( 'https://addonmaster.com/gridmaster/' ); ?>" target="_blank" class="gm-resource-link">
					<?php esc_html_e( 'View Shortcode Guide →', 'ajax-filter-posts' ); ?>
				</a>
			</div>
		</div>

		<div class="gm-resource-card">
			<div class="gm-resource-icon">
				<span class="dashicons dashicons-sos"></span>
			</div>
			<div class="gm-resource-body">
				<h4><?php esc_html_e( 'Need Help or Have Questions?', 'ajax-filter-posts' ); ?></h4>
				<p><?php esc_html_e( 'Access our documentation, video tutorials, or reach out to our dedicated support team for quick assistance.', 'ajax-filter-posts' ); ?></p>
				<a href="<?php echo esc_url( 'https://addonmaster.com/gridmaster/' ); ?>" target="_blank" class="gm-resource-link">
					<?php esc_html_e( 'Get Support →', 'ajax-filter-posts' ); ?>
				</a>
			</div>
		</div>
	</div>

</div>

<script>
(function() {
	function fallbackCopy(text, btn) {
		var textArea = document.createElement("textarea");
		textArea.value = text;
		textArea.style.position = "fixed";
		textArea.style.left = "-999999px";
		textArea.style.top = "-999999px";
		document.body.appendChild(textArea);
		textArea.focus();
		textArea.select();
		try {
			document.execCommand('copy');
			showCopiedFeedback(btn);
		} catch (err) {
			console.error('Copy fallback failed', err);
		}
		document.body.removeChild(textArea);
	}

	function showCopiedFeedback(btn) {
		var origHtml = btn.innerHTML;
		btn.innerHTML = '<span class="dashicons dashicons-yes"></span> <span class="gm-copy-text">' + 'Copied!' + '</span>';
		btn.classList.add('gm-copied');
		setTimeout(function() {
			btn.innerHTML = origHtml;
			btn.classList.remove('gm-copied');
		}, 2000);
	}

	function initCopyButtons() {
		document.querySelectorAll('.gm-copy-btn').forEach(function(btn) {
			btn.addEventListener('click', function(e) {
				e.preventDefault();
				var code = this.getAttribute('data-clipboard');
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(code).then(function() {
						showCopiedFeedback(btn);
					}).catch(function() {
						fallbackCopy(code, btn);
					});
				} else {
					fallbackCopy(code, btn);
				}
			});
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initCopyButtons);
	} else {
		initCopyButtons();
	}

	var gmRestUrl   = <?php echo wp_json_encode( esc_url_raw( rest_url( 'gridmaster/v2/' ) ) ); ?>;
	var gmRestNonce = <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>;

	function initExportImport() {
		document.querySelectorAll('.gm-action-export').forEach(function(btn) {
			btn.addEventListener('click', function() {
				var id   = this.getAttribute('data-grid-id');
				var slug = this.getAttribute('data-grid-slug') || ('grid-' + id);
				btn.disabled = true;

				fetch(gmRestUrl + 'grids/' + id + '/export', {
					method: 'GET',
					headers: { 'X-WP-Nonce': gmRestNonce }
				})
				.then(function(res) {
					if (!res.ok) throw new Error('Export failed (' + res.status + ')');
					return res.json();
				})
				.then(function(payload) {
					var blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' });
					var url  = URL.createObjectURL(blob);
					var a    = document.createElement('a');
					a.href = url;
					a.download = slug + '.gridmaster.json';
					document.body.appendChild(a);
					a.click();
					document.body.removeChild(a);
					setTimeout(function() { URL.revokeObjectURL(url); }, 1000);
				})
				.catch(function(err) {
					alert(err.message || 'Could not export grid.');
				})
				.finally(function() {
					btn.disabled = false;
				});
			});
		});

		var triggerBtn = document.getElementById('gm-import-grid-trigger');
		var modal      = document.getElementById('gm-import-modal');
		var closeBtn   = document.getElementById('gm-import-close');
		var fileInput  = document.getElementById('gm-import-file');
		var textArea   = document.getElementById('gm-import-json-text');
		var submitBtn  = document.getElementById('gm-import-submit');
		var statusEl   = document.getElementById('gm-import-status');

		if (!triggerBtn || !modal) return;

		function setModalOpen(open) {
			modal.style.display = open ? 'block' : 'none';
			modal.setAttribute('aria-hidden', open ? 'false' : 'true');
			if (open && statusEl) {
				statusEl.textContent = '';
				statusEl.className = 'gm-import-status';
			}
		}

		triggerBtn.addEventListener('click', function() {
			setModalOpen(true);
		});

		if (closeBtn) {
			closeBtn.addEventListener('click', function() {
				setModalOpen(false);
			});
		}

		modal.addEventListener('click', function(e) {
			if (e.target === modal) setModalOpen(false);
		});

		document.addEventListener('keydown', function(e) {
			if (e.key === 'Escape' && modal.style.display === 'block') {
				setModalOpen(false);
			}
		});

		if (fileInput && textArea) {
			fileInput.addEventListener('change', function() {
				var file = fileInput.files && fileInput.files[0];
				if (!file) return;
				var reader = new FileReader();
				reader.onload = function(evt) {
					textArea.value = String(evt.target.result || '');
				};
				reader.readAsText(file);
			});
		}

		if (submitBtn && textArea) {
			submitBtn.addEventListener('click', function() {
				var raw = (textArea.value || '').trim();
				if (!raw) {
					if (statusEl) {
						statusEl.textContent = '<?php echo esc_js( __( 'Please select a JSON file or paste exported JSON first.', 'ajax-filter-posts' ) ); ?>';
						statusEl.style.color = '#dc2626';
					}
					return;
				}

				var parsed;
				try {
					parsed = JSON.parse(raw);
				} catch (err) {
					if (statusEl) {
						statusEl.textContent = '<?php echo esc_js( __( 'Invalid JSON syntax.', 'ajax-filter-posts' ) ); ?>';
						statusEl.style.color = '#dc2626';
					}
					return;
				}

				submitBtn.disabled = true;
				if (statusEl) {
					statusEl.textContent = '<?php echo esc_js( __( 'Importing...', 'ajax-filter-posts' ) ); ?>';
					statusEl.style.color = '#475569';
				}

				fetch(gmRestUrl + 'grids/import', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': gmRestNonce
					},
					body: JSON.stringify(parsed)
				})
				.then(function(res) {
					return res.json().then(function(body) {
						if (!res.ok) throw new Error((body && body.message) || 'Import failed');
						return body;
					});
				})
				.then(function(body) {
					if (statusEl) {
						statusEl.textContent = '<?php echo esc_js( __( 'Imported successfully! Reloading…', 'ajax-filter-posts' ) ); ?> (' + (body.imported || 1) + ')';
						statusEl.style.color = '#059669';
					}
					setTimeout(function() { window.location.reload(); }, 500);
				})
				.catch(function(err) {
					if (statusEl) {
						statusEl.textContent = err.message || '<?php echo esc_js( __( 'Import failed.', 'ajax-filter-posts' ) ); ?>';
						statusEl.style.color = '#dc2626';
					}
				})
				.finally(function() {
					submitBtn.disabled = false;
				});
			});
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initExportImport);
	} else {
		initExportImport();
	}
})();
</script>

<div id="gm-import-modal" class="gm-modal-wrap" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="gm-import-modal-title">
	<div class="gm-modal-inner">
		<button type="button" id="gm-import-close" class="gm-modal-close" aria-label="<?php esc_attr_e( 'Close modal', 'ajax-filter-posts' ); ?>">
			<span class="dashicons dashicons-no-alt"></span>
		</button>
		<div class="gm-modal-body" style="text-align:left;padding:28px 30px;">
			<h2 id="gm-import-modal-title" style="margin:0 0 6px;"><?php esc_html_e( 'Import GridMaster Grid JSON', 'ajax-filter-posts' ); ?></h2>
			<p class="description" style="margin:0 0 16px;"><?php esc_html_e( 'Upload a .gridmaster.json file exported from another site or paste the JSON payload below.', 'ajax-filter-posts' ); ?></p>
			<div style="margin-bottom:14px;">
				<input type="file" id="gm-import-file" accept=".json,application/json" style="width:100%;" />
			</div>
			<textarea id="gm-import-json-text" rows="8" placeholder="{&quot;format&quot;:&quot;gridmaster-grid-v2&quot;,&quot;grid&quot;:{...}}" style="width:100%;font-family:monospace;font-size:12px;padding:10px;border:1px solid #cbd5e1;border-radius:6px;box-sizing:border-box;"></textarea>
			<div id="gm-import-status" class="gm-import-status" style="min-height:20px;margin:10px 0;font-size:13px;font-weight:500;"></div>
			<div style="display:flex;justify-content:flex-end;gap:10px;margin-top:8px;">
				<button type="button" id="gm-import-submit" class="gm-btn gm-btn-primary">
					<span class="dashicons dashicons-upload"></span>
					<span><?php esc_html_e( 'Import Grid', 'ajax-filter-posts' ); ?></span>
				</button>
			</div>
		</div>
	</div>
</div>

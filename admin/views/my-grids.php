<?php
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

$page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
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
?>

<div class="wrap gm-admin-dashboard">
	<div class="gm-dashboard-header">
		<div class="gm-dashboard-title">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'GridMaster Grids', 'ajax-filter-posts' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=gridmaster-builder' ) ); ?>" class="page-title-action gm-btn-primary">
				<span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Add New Grid', 'ajax-filter-posts' ); ?>
			</a>
		</div>

		<div class="gm-dashboard-search">
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
				<input type="hidden" name="page" value="gridmaster" />
				<input type="hidden" name="path" value="my-grids" />
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search grids...', 'ajax-filter-posts' ); ?>" />
				<button type="submit" class="button"><?php esc_html_e( 'Search', 'ajax-filter-posts' ); ?></button>
			</form>
		</div>
	</div>

	<?php if ( ! empty( $items ) ) : ?>
		<table class="wp-list-table widefat fixed striped table-view-list gm-table">
			<thead>
				<tr>
					<th scope="col" style="width: 60px;"><?php esc_html_e( 'ID', 'ajax-filter-posts' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Title', 'ajax-filter-posts' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Shortcode', 'ajax-filter-posts' ); ?></th>
					<th scope="col" style="width: 140px;"><?php esc_html_e( 'Builder Status', 'ajax-filter-posts' ); ?></th>
					<th scope="col" style="width: 150px;"><?php esc_html_e( 'Updated', 'ajax-filter-posts' ); ?></th>
					<th scope="col" style="width: 240px;"><?php esc_html_e( 'Actions', 'ajax-filter-posts' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $items as $grid ) :
					$shortcode = '[gridmaster id="' . $grid->id . '"]';
					$is_legacy = 'legacy' === $grid->migration_status;
					$is_converted = 'converted' === $grid->migration_status;
					$edit_url = $is_legacy
						? admin_url( 'admin.php?page=gridmaster&path=build-grid&id=' . $grid->id )
						: admin_url( 'admin.php?page=gridmaster-builder&id=' . $grid->id );
					?>
					<tr>
						<td><strong>#<?php echo esc_html( $grid->id ); ?></strong></td>
						<td>
							<strong><a href="<?php echo esc_url( $edit_url ); ?>" class="row-title"><?php echo esc_html( $grid->title ); ?></a></strong>
						</td>
						<td>
							<div class="gm-shortcode-cell">
								<code class="gm-code-pill"><?php echo esc_html( $shortcode ); ?></code>
								<button type="button" class="button button-small gm-copy-btn" data-clipboard="<?php echo esc_attr( $shortcode ); ?>" title="<?php esc_attr_e( 'Copy shortcode', 'ajax-filter-posts' ); ?>">
									<span class="dashicons dashicons-clipboard"></span> <?php esc_html_e( 'Copy', 'ajax-filter-posts' ); ?>
								</button>
							</div>
						</td>
						<td>
							<?php if ( $is_legacy ) : ?>
								<span class="gm-status-badge gm-status-legacy"><?php esc_html_e( 'Legacy 1.x', 'ajax-filter-posts' ); ?></span>
							<?php elseif ( $is_converted ) : ?>
								<span class="gm-status-badge gm-status-converted"><?php esc_html_e( 'Converted v2', 'ajax-filter-posts' ); ?></span>
							<?php else : ?>
								<span class="gm-status-badge gm-status-new"><?php esc_html_e( 'Gutenberg v2', 'ajax-filter-posts' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<time datetime="<?php echo esc_attr( $grid->updated_at ); ?>">
								<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $grid->updated_at ) ) ); ?>
							</time>
						</td>
						<td>
							<div class="gm-actions-group">
								<a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small">
									<?php esc_html_e( 'Edit', 'ajax-filter-posts' ); ?>
								</a>

								<?php if ( $is_legacy ) : ?>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=gridmaster&path=my-grids&gm_action=migrate&id=' . $grid->id ), 'gm_grid_action' ) ); ?>" class="button button-small button-primary" title="<?php esc_attr_e( 'Convert to Gutenberg v2 Builder', 'ajax-filter-posts' ); ?>">
										<?php esc_html_e( 'Migrate', 'ajax-filter-posts' ); ?>
									</a>
								<?php elseif ( $is_converted ) : ?>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=gridmaster&path=my-grids&gm_action=rollback&id=' . $grid->id ), 'gm_grid_action' ) ); ?>" class="button button-small" onclick="return confirm('<?php esc_attr_e( 'Roll back this grid to the legacy renderer?', 'ajax-filter-posts' ); ?>');">
										<?php esc_html_e( 'Rollback', 'ajax-filter-posts' ); ?>
									</a>
								<?php endif; ?>

								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=gridmaster&path=my-grids&gm_action=duplicate&id=' . $grid->id ), 'gm_grid_action' ) ); ?>" class="button button-small">
									<?php esc_html_e( 'Duplicate', 'ajax-filter-posts' ); ?>
								</a>

								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=gridmaster&path=my-grids&gm_action=delete&id=' . $grid->id ), 'gm_grid_action' ) ); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this grid?', 'ajax-filter-posts' ); ?>');">
									<?php esc_html_e( 'Delete', 'ajax-filter-posts' ); ?>
								</a>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php else : ?>
		<div class="gm-empty-state">
			<div class="gm-empty-icon"><span class="dashicons dashicons-grid-view"></span></div>
			<h2><?php esc_html_e( 'No grids created yet', 'ajax-filter-posts' ); ?></h2>
			<p><?php esc_html_e( 'Create your first post grid using the new drag-and-drop Gutenberg post card builder.', 'ajax-filter-posts' ); ?></p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=gridmaster-builder' ) ); ?>" class="button button-primary button-hero">
				<?php esc_html_e( 'Create New Grid', 'ajax-filter-posts' ); ?>
			</a>
		</div>
	<?php endif; ?>
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
			btn.innerHTML = '<span class="dashicons dashicons-yes"></span> Copied!';
			setTimeout(function() {
				btn.innerHTML = '<span class="dashicons dashicons-clipboard"></span> Copy';
			}, 2000);
		} catch (err) {
			console.error('Fallback copy failed', err);
		}
		document.body.removeChild(textArea);
	}

	function initCopyButtons() {
		document.querySelectorAll('.gm-copy-btn').forEach(function(btn) {
			btn.addEventListener('click', function() {
				var code = this.getAttribute('data-clipboard');
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(code).then(function() {
						btn.innerHTML = '<span class="dashicons dashicons-yes"></span> Copied!';
						setTimeout(function() {
							btn.innerHTML = '<span class="dashicons dashicons-clipboard"></span> Copy';
						}, 2000);
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
})();
</script>

<style>
.gm-admin-dashboard { margin-top: 20px; }
.gm-dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.gm-dashboard-title { display: flex; align-items: center; gap: 16px; }
.gm-btn-primary { background: #2563eb !important; color: #fff !important; border-color: #2563eb !important; border-radius: 6px; padding: 6px 14px; font-weight: 500; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
.gm-btn-primary:hover { background: #1d4ed8 !important; color: #fff !important; }
.gm-table { border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
.gm-shortcode-cell { display: inline-flex; align-items: center; gap: 8px; }
.gm-code-pill { background: #f1f5f9; padding: 4px 8px; border-radius: 4px; font-family: monospace; font-size: 13px; color: #334155; }
.gm-status-badge { display: inline-block; padding: 3px 8px; border-radius: 9999px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
.gm-status-legacy { background: #fef3c7; color: #92400e; }
.gm-status-converted { background: #e0e7ff; color: #3730a3; }
.gm-status-new { background: #dcfce7; color: #166534; }
.gm-actions-group { display: flex; align-items: center; gap: 6px; }
.gm-empty-state { text-align: center; padding: 60px 20px; background: #fff; border-radius: 8px; border: 1px dashed #cbd5e1; margin-top: 20px; }
.gm-empty-icon .dashicons { font-size: 48px; width: 48px; height: 48px; color: #94a3b8; }
</style>

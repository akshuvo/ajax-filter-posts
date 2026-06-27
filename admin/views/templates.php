<?php
/**
 * Admin Menu: Gutenberg grid templates.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$templates = get_posts(
	array(
		'post_type'      => array( 'gm_grid_template', 'gm_grid_style' ),
		'post_status'    => array( 'publish', 'draft' ),
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'DESC',
	)
);

$new_template_url = admin_url( 'post-new.php?post_type=gm_grid_template' );
?>

<div class="gridmaster-wrap">
	<div class="container-fluid pt-0 pt-3 gm-container">
		<div class="gm-grid-list">
			<div class="gm-grid-list-header d-flex" style="justify-content: space-between; align-items: center;">
				<div>
					<h2><?php esc_html_e( 'Gutenberg Grid Templates', 'ajax-filter-posts' ); ?></h2>
					<p><?php esc_html_e( 'Create reusable post grid designs once, then choose them from Gutenberg, Elementor, or any builder that supports shortcodes.', 'ajax-filter-posts' ); ?></p>
				</div>
				<a class="gm-btn gm-btn-fill" href="<?php echo esc_url( $new_template_url ); ?>">
					<span class="dashicons dashicons-plus-alt2"></span>
					<?php esc_html_e( 'Create Template', 'ajax-filter-posts' ); ?>
				</a>
			</div>

			<div class="gm-grid-list-body">
				<?php if ( empty( $templates ) ) : ?>
					<div class="gm-card text-center">
						<div class="gm-card-details">
							<div class="gm-icon"><span class="dashicons dashicons-layout"></span></div>
							<div class="gm-card-containt">
								<h2><?php esc_html_e( 'No Gutenberg templates yet.', 'ajax-filter-posts' ); ?></h2>
								<p><?php esc_html_e( 'Start with a post card layout using the GridMaster field blocks, then insert it from your page builder.', 'ajax-filter-posts' ); ?></p>
								<a class="gm-btn gm-btn-fill" href="<?php echo esc_url( $new_template_url ); ?>"><?php esc_html_e( 'Create Your First Template', 'ajax-filter-posts' ); ?></a>
							</div>
						</div>
					</div>
				<?php else : ?>
					<table class="wp-list-table widefat striped table-view-list">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Template', 'ajax-filter-posts' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Status', 'ajax-filter-posts' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Shortcode', 'ajax-filter-posts' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Use In Builders', 'ajax-filter-posts' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Actions', 'ajax-filter-posts' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $templates as $template ) : ?>
								<?php $shortcode = '[gridmaster template_id="' . absint( $template->ID ) . '"]'; ?>
								<tr>
									<th>
										<strong><?php echo esc_html( get_the_title( $template ) ); ?></strong>
										<div class="row-actions">
											<span><?php echo esc_html( $template->post_type ); ?></span>
										</div>
									</th>
									<td><?php echo esc_html( get_post_status_object( $template->post_status )->label ); ?></td>
									<td>
										<div class="d-flex gm-copy-wrap input-sheamless">
											<input type="text" value="<?php echo esc_attr( $shortcode ); ?>" class="gm-copy-inp gm-copy-val" readonly>
											<button type="button" class="gm-copy-btn gm-tooltip button" title="<?php esc_attr_e( 'Copy Shortcode', 'ajax-filter-posts' ); ?>"><span class="m-0 dashicons dashicons-admin-page"></span></button>
										</div>
									</td>
									<td>
										<?php esc_html_e( 'Gutenberg block, Elementor widget, WordPress widget, shortcode widget, HTML widget.', 'ajax-filter-posts' ); ?>
									</td>
									<td>
										<div class="action-btns">
											<a href="<?php echo esc_url( get_edit_post_link( $template->ID ) ); ?>" class="button gm-tooltip" title="<?php esc_attr_e( 'Edit in Gutenberg', 'ajax-filter-posts' ); ?>"><span class="m-0 dashicons dashicons-edit"></span></a>
											<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=gm_grid_template' ) ); ?>" class="button gm-tooltip" title="<?php esc_attr_e( 'Create New Template', 'ajax-filter-posts' ); ?>"><span class="m-0 dashicons dashicons-plus-alt2"></span></a>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>

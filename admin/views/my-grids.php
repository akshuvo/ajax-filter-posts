<?php

// Create table if not exists
if ( ! class_exists( 'GridMaster\DB_Migration' ) ) {
	require_once GRIDMASTER_PATH . '/admin/DB_Migration.php';
}
if ( ! class_exists( 'GridMaster\Grids' ) ) {
	require_once GRIDMASTER_PATH . '/admin/Grids.php';
}

$my_grids = GridMaster\Grids::init()->list();


?>


<div class="gridmaster-wrap ">
	<div class="container-fluid pt-0 pt-3 gm-container">
		<div class="row">
			<div class="col-md-12">
				
				<?php if ( ! empty( $my_grids ) ) : ?>
					<div class="gm-grid-list">
						<div class="gm-grid-list-header">
							<h2><?php esc_html_e( 'My Grids', 'gridmaster' ); ?></h2>
						</div>
						<div class="gm-grid-list-body">
							<table class="wp-list-table widefat fixed striped table-view-list">
								<thead>
									<tr>
										<th scope="col"><?php esc_html_e( 'Title', 'gridmaster' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Post Type', 'gridmaster' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Taxonomy', 'gridmaster' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Shortcode', 'gridmaster' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Actions', 'gridmaster' ); ?></th>
									</tr>
								</thead>
								<tbody id="the-list">

									
									
								</tbody>
							</table>
							<form class="gm-ajax-form" id="gm-list-grids" action="" method="post">
							  

								<input type="hidden" name="action" value="gridmaster_ajax">
								<input type="hidden" name="gm-action" value="list_grids">
								<?php wp_nonce_field( 'gm-ajax-nonce', 'gm_nonce' ); ?>

								<button type="submit">Load</button>
							</form>
						</div>
					</div>
				<?php else : ?>
					<div class="gm-card text-center">
						<div class="gm-card-details">
							<div class="gm-icon">
								<span class="dashicons dashicons-info-outline"></span>
							</div>
							<div class="gm-card-containt">
								<h2><?php esc_html_e( 'No Grids Found', 'gridmaster' ); ?></h2>
								<a class="gm-btn gm-btn-fill" href="<?php echo esc_url( admin_url( 'admin.php?page=gridmaster&path=build-grid' ) ); ?>"><?php esc_html_e( 'Create Your First Grid', 'gridmaster' ); ?></a>
							</div>
						</div>
					</div>
				<?php endif; ?>

			</div>
		</div>
	</div>
</div>

<script type="text/javascript">
	jQuery( document ).on( 'gm-ajax-success-list_grids', ( e, data ) => {
		console.log(data)
		let template = wp.template( 'wp-grid_row' );
		let output = '';

		const grids = data.grids;
		for (let i = 0; i < grids.length; i++) {
			const grid = grids[i];
			output += template( grid );
			
		}

		console.log(output)

	})
</script>

<script id="tmpl-wp-grid_row" type="text/html">
	<tr>
		<th scope="col">{{ title }}</th>
		<th scope="col"><?php esc_html_e( 'Post Type', 'gridmaster' ); ?></th>
		<th scope="col"><?php esc_html_e( 'Taxonomy', 'gridmaster' ); ?></th>
		<th scope="col"><?php esc_html_e( 'Shortcode', 'gridmaster' ); ?></th>
		<th scope="col"><?php esc_html_e( 'Actions', 'gridmaster' ); ?></th>
	</tr>
</script>
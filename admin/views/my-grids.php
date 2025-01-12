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
							<h2><?php esc_html_e( 'My Grids', 'ajax-filter-posts'  ); ?></h2>
						</div>
						<div class="gm-grid-list-body">
							<table class="wp-list-table widefat striped table-view-list">
								<thead>
									<tr>
										<th scope="col"><?php esc_html_e( 'Title', 'ajax-filter-posts'  ); ?></th>
										<th scope="col"><?php esc_html_e( 'Shortcode', 'ajax-filter-posts'  ); ?></th>
										<th scope="col"><?php esc_html_e( 'Post Type', 'ajax-filter-posts'  ); ?></th>
										<th scope="col"><?php esc_html_e( 'Taxonomy', 'ajax-filter-posts'  ); ?></th>
										<th scope="col"><?php esc_html_e( 'Actions', 'ajax-filter-posts'  ); ?></th>
									</tr>
								</thead>
								<tbody id="the-list">
									<!-- Loads by JS -->
								</tbody>
							</table>
							<form class="gm-ajax-form" id="gm-list-grids" action="" method="post">
								<div class="spinner-wrap text-center">
									<span class="spinner"></span>
								</div>
								<input type="hidden" name="action" value="gridmaster_ajax">
								<input type="hidden" name="gm-action" value="list_grids">
								<?php wp_nonce_field( 'gm-ajax-nonce', 'gm_nonce' ); ?>
								<!-- <button type="submit">Load</button> -->
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
								<h2><?php esc_html_e( 'You didn\'t save any grid yet!', 'ajax-filter-posts'  ); ?></h2>
								<a class="gm-btn gm-btn-fill" href="<?php echo esc_url( admin_url( 'admin.php?page=gridmaster&path=build-grid' ) ); ?>"><?php esc_html_e( 'Create Your First Grid', 'ajax-filter-posts'  ); ?></a>
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
		let output = '';

		const grids = data.grids;
		for (let i = 0; i < grids.length; i++) {
			const grid = grids[i];
			console.log(grid)
			output += `
			<tr>
				<th>${grid.title}</th>
				<th>
					<div class="d-flex gm-copy-wrap input-sheamless">
						<input type="text" value='[gridmaster id="${grid.id}"]' class="gm-copy-inp gm-copy-val" readonly>
						<button type="button" class="gm-copy-btn gm-tooltip" title="Copy Shortcode"><span class="m-0 dashicons dashicons-admin-page"></span></button>
					</div>
				</th>
				<th>${grid.attributes.post_type}</th>
				<th>${grid.attributes.taxonomy}</th>
				<th>
					<div class="action-btns">
						<a href="admin.php?page=gridmaster&path=build-grid&id=${grid.id}" class="button gm-tooltip" title="Edit"><span class="m-0 dashicons dashicons-edit"></span></a>
						<button type="button" class="button gm-tooltip" title="Duplicate"><span class="m-0 dashicons dashicons-admin-page"></span></button>
						<button type="button" class="button gm-tooltip" title="Delete"><span class="m-0 dashicons dashicons-trash"></span></button>
					</div>
				</th>
			</tr>
			`;
			
		}

		jQuery( '#the-list' ).html( output );

	

	})
</script>
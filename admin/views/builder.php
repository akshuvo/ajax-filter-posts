<?php
use GridMaster\Storage\Grid_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$grid_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
$grid    = $grid_id ? Grid_Repository::get( $grid_id ) : null;

// Pre-populate public post types and taxonomies for instant UI availability
$post_types    = get_post_types( array( 'public' => true ), 'objects' );
$formatted_pts = array();
foreach ( $post_types as $pt ) {
	if ( 'attachment' === $pt->name ) {
		continue;
	}
	$formatted_pts[] = array(
		'name'  => $pt->name,
		'label' => $pt->labels->singular_name ? $pt->labels->singular_name : $pt->label,
	);
}

$taxonomies    = get_taxonomies( array( 'public' => true ), 'objects' );
$formatted_tax = array();
foreach ( $taxonomies as $tax ) {
	$formatted_tax[] = array(
		'name'       => $tax->name,
		'label'      => $tax->labels->singular_name ? $tax->labels->singular_name : $tax->label,
		'post_types' => $tax->object_type,
	);
}

$initial_data = array(
	'id'             => $grid ? $grid->id : 0,
	'title'          => $grid ? $grid->title : __( 'Untitled Grid', 'ajax-filter-posts' ),
	'config'         => $grid ? $grid->config : Grid_Repository::default_config(),
	'block_content'  => $grid && ! empty( $grid->block_content ) ? $grid->block_content : Grid_Repository::default_block_content(),
	'status'         => $grid ? $grid->migration_status : 'new_builder',
	'restUrl'        => esc_url_raw( rest_url( 'gridmaster/v2/' ) ),
	'nonce'          => wp_create_nonce( 'wp_rest' ),
	'adminUrl'       => admin_url( 'admin.php?page=gridmaster' ),
	'isPro'          => defined( 'GRIDMASTER_PRO_VERSION' ),
	'frontendCssUrl' => esc_url_raw( GRIDMASTER_ASSETS . 'css/frontend-v2.css' ),
	'frontendJsUrl'  => esc_url_raw( GRIDMASTER_ASSETS . 'js/frontend-v2.js' ),
	'metadata'       => array(
		'post_types' => $formatted_pts,
		'taxonomies' => $formatted_tax,
	),
);
?>

<div id="gridmaster-builder-root" class="gm-builder-root">
	<!-- React/Gutenberg Builder Workspace Mounts Here -->
	<div class="gm-builder-loading">
		<span class="spinner is-active"></span>
		<p><?php esc_html_e( 'Loading GridMaster Gutenberg Builder...', 'ajax-filter-posts' ); ?></p>
	</div>
</div>

<script>
// Zero-FOUC synchronous focus mode initialization for maximum efficiency
if ( localStorage.getItem( 'gm_builder_focus_mode' ) === 'true' ) {
	document.body.classList.add( 'gm-focus-mode' );
}
window.gridmasterBuilderData = <?php echo wp_json_encode( $initial_data ); ?>;
</script>

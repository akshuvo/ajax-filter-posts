<?php
/**
 * Free-only degradation matrix test (expects gridmaster-pro to be inactive).
 * Run with `wp plugin deactivate gridmaster-pro`, then `wp eval-file ...`, then `wp plugin activate gridmaster-pro`.
 */

use GridMaster\Engine\Grid_Renderer;
use GridMaster\Storage\Grid_Repository;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

$original_user = get_current_user_id();
$created_posts = array();
$created_grids = array();
$assertions    = 0;

$expect = function ( $condition, $message ) use ( &$assertions ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	++$assertions;
	echo "PASS: {$message}\n";
};

try {
	$expect( ! class_exists( 'GridMasterPro\Blocks\Pro_Blocks' ), 'GridMaster Pro is confirmed inactive for Free-only verification.' );

	$admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
	$expect( ! empty( $admins ), 'An administrator account is available.' );
	$admin_id = (int) $admins[0]->ID;
	wp_set_current_user( $admin_id );

	$suffix  = wp_generate_password( 6, false );
	$post_id = wp_insert_post( array(
		'post_title'  => 'FreeOnly Post ' . $suffix,
		'post_status' => 'publish',
		'post_author' => $admin_id,
	), true );
	$expect( ! is_wp_error( $post_id ), 'Disposable post created.' );
	$created_posts[] = $post_id;

	$cfg = Grid_Repository::default_config();
	$cfg['query']['post__in'] = array( $post_id );
	$cfg['facets'] = array(
		array( 'type' => 'search', 'heading' => 'Search' ),
		array( 'type' => 'meta-range', 'meta_key' => 'area_sqft', 'heading' => 'Area' ),
		array( 'type' => 'woo-status-toggles', 'heading' => 'Status' ),
	);
	$cfg['layout']['filter_position'] = 'drawer';
	$cfg['layout']['type']            = 'masonry';

	$pro_markup = '<!-- wp:gridmaster/card -->'
		. '<!-- wp:gridmaster/title {"isLink":false} /-->'
		. '<!-- wp:gridmaster-pro/bookmark-button /-->'
		. '<!-- wp:gridmaster-pro/product-price /-->'
		. '<!-- wp:gridmaster/badge {"text":"Visible Badge","gmVisibility":{"rule":"always"}} /-->'
		. '<!-- /wp:gridmaster/card -->';

	$grid_id = Grid_Repository::save( array(
		'title'         => 'FreeOnly Fallback ' . $suffix,
		'config'        => $cfg,
		'block_content' => $pro_markup,
	) );
	$expect( ! is_wp_error( $grid_id ) && $grid_id > 0, 'Grid with Pro facets & Pro blocks saved cleanly while Pro inactive.' );
	$created_grids[] = $grid_id;

	$html = Grid_Renderer::render_grid( $grid_id, array() );
	$expect( false !== strpos( $html, 'FreeOnly Post ' . $suffix ), 'Free-only render displays card title cleanly.' );
	$expect( false !== strpos( $html, 'Visible Badge' ), 'Free-only render displays sibling Free badge block cleanly.' );
	$expect( false !== strpos( $html, 'gm-filter-search' ), 'Free search facet still renders alongside Pro-only facets.' );
	$expect( false !== strpos( $html, 'gm-filter-pos-drawer' ) && false !== strpos( $html, 'gm-layout-masonry' ), 'Drawer and Masonry layouts render without Pro.' );

	$req = new WP_REST_Request( 'POST', "/gridmaster/v2/grids/{$grid_id}/render" );
	$req->set_body_params( array( 'search' => $suffix ) );
	$res = rest_do_request( $req );
	$expect( 200 === $res->get_status() && false !== strpos( $res->get_data()['html'] ?? '', $suffix ), 'REST /render works cleanly in Free-only mode.' );

	$stored = Grid_Repository::get( $grid_id );
	$expect( 'meta-range' === ( $stored->config['facets'][1]['type'] ?? '' ), 'Stored Pro config keys remain preserved cleanly in DB during Free-only mode.' );

	echo "PASS: All {$assertions} Free-only degradation assertions passed.\n";
} finally {
	foreach ( $created_grids as $gid ) {
		if ( $gid && ! is_wp_error( $gid ) ) {
			Grid_Repository::delete( $gid );
		}
	}
	foreach ( $created_posts as $pid ) {
		wp_delete_post( $pid, true );
	}
	wp_set_current_user( $original_user );
}

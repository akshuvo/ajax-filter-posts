<?php
/**
 * Builder draft preview regression checks using disposable WordPress fixtures.
 * Run: wp eval-file tests/gm2-storage/test_builder_preview.php
 */

use GridMaster\Storage\Grid_Repository;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

$original_user = get_current_user_id();
$posts         = array();
$grid_id       = 0;
$term_id       = 0;
$assertions    = 0;
$expect        = function ( $condition, $message ) use ( &$assertions ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	++$assertions;
	echo "PASS: {$message}\n";
};

try {
	$admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
	$expect( ! empty( $admins ), 'An administrator is available for management requests.' );
	wp_set_current_user( $admins[0]->ID );
	$name = 'gm-builder-regression-' . wp_generate_password( 8, false );
	$term = wp_insert_term( $name, 'category' );
	$expect( ! is_wp_error( $term ), 'Disposable category created.' );
	$term_id = $term['term_id'];
	for ( $index = 0; $index < 3; ++$index ) {
		$post_id = wp_insert_post( array(
			'post_title'   => $name . ' ' . $index,
			'post_content' => 'Published fixture content.',
			'post_status'  => 'publish',
		), true );
		$expect( ! is_wp_error( $post_id ), 'Disposable result post created.' );
		$posts[] = $post_id;
		wp_set_post_terms( $post_id, array( $term_id ), 'category' );
	}
	$config = Grid_Repository::default_config();
	$config['query']['tax_query'] = array( array( 'taxonomy' => 'category', 'terms' => array( $term_id ) ) );
	$config['query']['orderby'] = 'ID';
	$config['query']['order'] = 'ASC';
	$grid_id = Grid_Repository::save( array( 'title' => $name, 'config' => $config ) );
	$expect( ! is_wp_error( $grid_id ), 'Disposable grid created.' );
	$before = wp_json_encode( Grid_Repository::get( $grid_id ) );
	$config['query']['posts_per_page'] = 1;
	$draft = '<!-- wp:paragraph --><p>Draft-only card</p><!-- /wp:paragraph -->';
	$render = function ( $page, $search = '' ) use ( $grid_id, $config, $draft ) {
		$request = new WP_REST_Request( 'POST', '/gridmaster/v2/preview/render' );
		$request->set_body_params( array(
			'id' => $grid_id, 'config' => $config, 'block_content' => $draft,
			'page' => $page, 'search' => $search,
		) );
		return rest_do_request( $request );
	};
	foreach ( array( 1, 2, 3 ) as $page ) {
		$response = $render( $page );
		$html = $response->get_data()['html'];
		$expect( 200 === $response->get_status(), 'Authorized draft page renders.' );
		$expect( 1 === preg_match_all( '/data-post-id="(\d+)"/', $html, $matches ), 'Draft page supplies one stable card identity for append deduplication.' );
		$expect( (int) $matches[1][0] === $posts[ $page - 1 ], 'Draft pagination advances to the next distinct post.' );
		$expect( false !== strpos( $html, 'Draft-only card' ), 'Pagination retains unsaved card markup.' );
	}
	$empty = $render( 1, $name . '-no-match' )->get_data()['html'];
	$expect( false !== strpos( $empty, 'gm-empty-results' ), 'Empty draft uses the real empty-results state.' );
	$expect( $before === wp_json_encode( Grid_Repository::get( $grid_id ) ), 'Interactive previews do not persist draft changes.' );
	$request = new WP_REST_Request( 'GET', '/gridmaster/v2/meta-data' );
	$request->set_query_params( array( 'taxonomy' => 'category', 'term_search' => $name ) );
	$metadata = rest_do_request( $request )->get_data();
	$expect( 1 === count( $metadata['taxonomies'] ), 'Term search scopes results to the chosen public taxonomy.' );
	$expect( $term_id === $metadata['taxonomies'][0]['terms'][0]['id'], 'Term search finds the matching category.' );
	wp_set_current_user( 0 );
	$expect( 200 !== $render( 1 )->get_status(), 'Anonymous users cannot render arbitrary draft definitions.' );
	echo "PASS: {$assertions} builder preview assertions.\n";
} finally {
	if ( $grid_id && ! is_wp_error( $grid_id ) ) {
		Grid_Repository::delete( $grid_id );
	}
	foreach ( $posts as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	if ( $term_id ) {
		wp_delete_term( $term_id, 'category' );
	}
	wp_set_current_user( $original_user );
}

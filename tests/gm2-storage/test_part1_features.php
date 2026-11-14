<?php
/**
 * Part 1 fixes and partially-wired feature completion verification.
 * Run: php -d "mysqli.default_socket=..." wp eval-file tests/gm2-storage/test_part1_features.php
 */

use GridMaster\Engine\Grid_Renderer;
use GridMaster\Pagination\Pagination_Registry;
use GridMaster\Storage\Grid_Repository;
use GridMasterPro\Assets\Pro_Assets;
use GridMasterPro\Facets\Date_Range_Facet;
use GridMasterPro\Facets\Meta_Filter_Facet;
use GridMasterPro\Facets\Multi_Select_Facet;
use GridMasterPro\Facets\Woo_Status_Toggles_Facet;

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
	$expect( ! empty( $admins ), 'An administrator account is available.' );
	wp_set_current_user( $admins[0]->ID );

	// 1. Pro_Assets modular facet & video detection from $config.
	Pro_Assets::register_assets();
	$expect( wp_style_is( 'gridmaster-pro-facets', 'registered' ), 'Pro facets stylesheet handle is registered.' );

	wp_dequeue_style( 'gridmaster-pro-facets' );
	wp_dequeue_style( 'gridmaster-pro-video' );

	Pro_Assets::detect_and_enqueue_from_content( '', array(
		'facets' => array(
			array( 'type' => 'woo-status-toggles' ),
		),
	) );
	$expect( wp_style_is( 'gridmaster-pro-facets', 'enqueued' ), 'Pro facets stylesheet enqueues from config facets.' );

	Pro_Assets::detect_and_enqueue_from_content( '', array(
		'layout' => array( 'type' => 'reels' ),
	) );
	$expect( wp_style_is( 'gridmaster-pro-video', 'enqueued' ), 'Pro video stylesheet enqueues from config layout reels.' );

	// 2. Woo_Status_Toggles_Facet HTML contract.
	$status_html = Woo_Status_Toggles_Facet::render( array( 'heading' => 'Status' ), 99, array( 'on_sale' => true ) );
	$expect( false !== strpos( $status_html, 'data-status="on_sale"' ), 'Woo status toggle renders data-status="on_sale".' );
	$expect( false !== strpos( $status_html, 'data-status-key="on_sale"' ), 'Woo status toggle preserves data-status-key="on_sale".' );
	$expect( false !== strpos( $status_html, 'aria-pressed="true"' ), 'Woo status toggle marks active state via aria-pressed.' );

	// 3. Multi_Select_Facet HTML contract.
	$slug    = 'gm-part1-' . wp_generate_password( 6, false );
	$created = wp_insert_term( 'Part1 Tag ' . $slug, 'category' );
	$expect( ! is_wp_error( $created ), 'Disposable category created.' );
	$term_id = (int) $created['term_id'];

	$post_id = wp_insert_post( array(
		'post_title'   => 'Part1 Post ' . $slug,
		'post_content' => 'Part1 content fixture.',
		'post_status'  => 'publish',
		'post_author'  => $admins[0]->ID,
	), true );
	$expect( ! is_wp_error( $post_id ), 'Disposable post created.' );
	$posts[] = $post_id;
	wp_set_post_terms( $post_id, array( $term_id ), 'category' );
	update_post_meta( $post_id, 'gm_city', 'Toronto' );

	$multi_html = Multi_Select_Facet::render(
		array( 'taxonomy' => 'category', 'heading' => 'Cats' ),
		99,
		array( 'facets' => array( 'category' => array( $term_id ) ) )
	);
	$expect( false !== strpos( $multi_html, '<button type="button"' ), 'Multi-select renders button pills.' );
	$expect( false !== strpos( $multi_html, 'data-label=' ), 'Multi-select pills include data-label attribute.' );
	$expect( false !== strpos( $multi_html, 'aria-pressed="true"' ), 'Multi-select pills reflect active aria-pressed state.' );

	// 4. Meta_Filter_Facet HTML contract & SSR hydration.
	$meta_html = Meta_Filter_Facet::render(
		array( 'meta_key' => 'gm_city', 'heading' => 'City' ),
		99,
		array( 'meta' => array( 'gm_city' => 'Toronto' ) )
	);
	$expect( false !== strpos( $meta_html, '<input type="text"' ) && false !== strpos( $meta_html, 'class="gm-meta-input"' ), 'Meta filter renders .gm-meta-input.' );
	$expect( 2 === substr_count( $meta_html, 'data-meta-key="gm_city"' ), 'Meta filter puts data-meta-key on wrapper and input.' );
	$expect( false !== strpos( $meta_html, 'value="Toronto"' ), 'Meta filter prefills input value from runtime params.' );

	// 5. Date_Range_Facet SSR hydration.
	$date_html = Date_Range_Facet::render( array( 'heading' => 'Dates' ), 99, array( 'date_from' => '2026-01-01', 'date_to' => '2026-02-01' ) );
	$expect( false !== strpos( $date_html, 'value="2026-01-01"' ) && false !== strpos( $date_html, 'value="2026-02-01"' ), 'Date range prefills date inputs from runtime params.' );

	// 6. Ellipsis Pagination sequences.
	$expect( array( 1, 2, 3, 4, 5, 6, 7 ) === Pagination_Registry::build_page_sequence( 4, 7 ), 'Pagination renders full sequence when max <= 7.' );
	$expect( array( 1, 2, 3, 4, '...', 20 ) === Pagination_Registry::build_page_sequence( 1, 20 ), 'Pagination page 1 of 20 renders leading window + ellipsis + last.' );
	$expect( array( 1, '...', 9, 10, 11, '...', 20 ) === Pagination_Registry::build_page_sequence( 10, 20 ), 'Pagination page 10 of 20 renders double ellipsis window.' );
	$expect( array( 1, '...', 17, 18, 19, 20 ) === Pagination_Registry::build_page_sequence( 19, 20 ), 'Pagination page 19 of 20 renders trailing window.' );
	$expect( array( 1, '...', 4, 5, 6, 7, 8 ) === Pagination_Registry::build_page_sequence( 5, 8 ), 'Pagination avoids single-digit ellipsis gap.' );
	$expect( array( 1, 2, 3, 4, 5, '...', 10 ) === Pagination_Registry::build_page_sequence( 4, 10 ), 'Pagination page 4 of 10 expands page 2 instead of ellipsis.' );

	$fake_query_obj            = new WP_Query( array( 'post_type' => 'post', 'posts_per_page' => 1, 'paged' => 10 ) );
	// Force max_num_pages = 20 for rendering assertion.
	$fake_query_obj->max_num_pages = 20;
	$fake_query_obj->set( 'paged', 10 );
	$paged_html = Pagination_Registry::render( 'numbered', $fake_query_obj, array(), 99 );
	$expect( false !== strpos( $paged_html, 'gm-page-ellipsis' ), 'Rendered pagination contains .gm-page-ellipsis for large page counts.' );

	// 7. URL parameter extraction & sanitization.
	$extracted = Grid_Renderer::extract_url_runtime_params( array(
		'gm_q'        => '  Toronto ',
		'gm_paged'    => '3',
		'gm_sort'     => 'price-desc',
		'gm_min_price'=> '12.5',
		'gm_max_price'=> '99.9',
		'gm_rating'   => '4',
		'gm_sale'     => '1',
		'gm_stock'    => '1',
		'gm_featured' => '1',
		'gm_from'     => '2026-01-01',
		'gm_to'       => '2026-12-31',
		'gm_meta_gm_city' => 'Toronto',
		'gm_category' => $term_id . ',999',
	) );
	$expect( 'Toronto' === $extracted['search'], 'URL parser extracts gm_q.' );
	$expect( 3 === $extracted['paged'], 'URL parser extracts gm_paged.' );
	$expect( 'price-desc' === $extracted['orderby'], 'URL parser extracts gm_sort.' );
	$expect( 12.5 === $extracted['min_price'] && 99.9 === $extracted['max_price'], 'URL parser extracts price range.' );
	$expect( 4 === $extracted['min_rating'], 'URL parser extracts gm_rating.' );
	$expect( ! empty( $extracted['on_sale'] ) && ! empty( $extracted['in_stock'] ) && ! empty( $extracted['featured'] ), 'URL parser extracts boolean status flags.' );
	$expect( 'Toronto' === ( $extracted['meta']['gm_city'] ?? '' ), 'URL parser extracts gm_meta_* keys.' );
	$expect( array( $term_id, 999 ) === ( $extracted['facets']['category'] ?? array() ), 'URL parser extracts gm_<taxonomy> term IDs.' );

	// 8. REST metadata authors + Builder facet/query persistence & rendering.
	$meta_req  = new WP_REST_Request( 'GET', '/gridmaster/v2/meta-data' );
	$meta_resp = rest_do_request( $meta_req );
	$expect( 200 === $meta_resp->get_status() && ! empty( $meta_resp->get_data()['authors'] ), 'Metadata REST endpoint returns authors list.' );

	$config                        = Grid_Repository::default_config();
	$config['query']['author']     = $admins[0]->ID;
	$config['query']['meta_query'] = array(
		array( 'key' => 'gm_city', 'compare' => '=', 'value' => 'Toronto' ),
	);
	$config['facets']              = array(
		array( 'type' => 'taxonomy-tabs', 'taxonomy' => 'category', 'style' => 'chips', 'multi_select' => true, 'heading' => 'Topics' ),
		array( 'type' => 'taxonomy-radio', 'taxonomy' => 'category', 'heading' => 'Radio Topic' ),
		array( 'type' => 'meta-filter', 'meta_key' => 'gm_city', 'heading' => 'City' ),
	);
	$grid_id = Grid_Repository::save( array( 'title' => 'Part1 Grid ' . $slug, 'config' => $config ) );
	$expect( ! is_wp_error( $grid_id ) && $grid_id > 0, 'Saved grid with author, meta_query, radio, meta-filter, chips tabs.' );

	$rendered = Grid_Renderer::render_grid( $grid_id, array() );
	$expect( false !== strpos( $rendered, 'data-post-id="' . $post_id . '"' ), 'Saved grid query (author + meta_query) returns matching post.' );
	$expect( false !== strpos( $rendered, 'gm-facet-chips' ) && false !== strpos( $rendered, 'data-multi-select="1"' ), 'Taxonomy tabs renders chips + multi-select attributes.' );
	$expect( false !== strpos( $rendered, 'gm-filter-radio' ), 'Radio filter facet renders inside grid.' );
	$expect( false !== strpos( $rendered, 'gm-meta-input' ), 'Meta filter facet renders inside grid.' );

	echo "PASS: All {$assertions} Part 1 assertions passed.\n";
} finally {
	if ( $grid_id && ! is_wp_error( $grid_id ) ) {
		Grid_Repository::delete( $grid_id );
	}
	foreach ( $posts as $pid ) {
		wp_delete_post( $pid, true );
	}
	if ( $term_id ) {
		wp_delete_term( $term_id, 'category' );
	}
	wp_set_current_user( $original_user );
}

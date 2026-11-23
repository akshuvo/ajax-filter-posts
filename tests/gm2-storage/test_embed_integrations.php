<?php
/**
 * Page builder integrations and embed helper regression test suite.
 * Run: wp eval-file tests/gm2-storage/test_embed_integrations.php
 */

use GridMaster\Storage\Grid_Repository;
use GridMaster\Integrations\Embed_Helper;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

$original_user = get_current_user_id();
$posts         = array();
$grid_id       = 0;
$legacy_id     = 0;
$author_id     = 0;
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
	$expect( ! empty( $admins ), 'Administrator user available.' );
	wp_set_current_user( $admins[0]->ID );

	// 1. Create test posts
	$name = 'gm-embed-test-' . wp_generate_password( 8, false );
	for ( $i = 0; $i < 2; ++$i ) {
		$pid = wp_insert_post( array(
			'post_title'   => $name . ' ' . $i,
			'post_content' => 'Post content for embed test.',
			'post_status'  => 'publish',
		), true );
		$expect( ! is_wp_error( $pid ), 'Disposable post created.' );
		$posts[] = $pid;
	}

	// 2. Create modern v2 grid
	$config = Grid_Repository::default_config();
	$grid_id = Grid_Repository::save( array(
		'title'  => $name . ' Modern Grid',
		'config' => $config,
	) );
	$expect( ! is_wp_error( $grid_id ) && $grid_id > 0, 'Modern v2 test grid created.' );

	// 3. Create legacy grid
	$legacy_id = Grid_Repository::save( array(
		'title'            => $name . ' Legacy Grid',
		'migration_status' => 'legacy',
		'schema_version'   => 1,
		'attributes'       => array( 'grid_style' => 'default' ),
	) );
	$expect( ! is_wp_error( $legacy_id ) && $legacy_id > 0, 'Legacy test grid created.' );

	// 4. Test Embed_Helper::get_grid_choices()
	$choices = Embed_Helper::get_grid_choices();
	$expect( isset( $choices[0] ), 'Placeholder option 0 present in grid choices.' );
	$expect( isset( $choices[ $grid_id ] ), 'Modern grid is present in grid choices.' );
	$expect( isset( $choices[ $legacy_id ] ), 'Legacy grid is present in grid choices.' );
	$expect( false !== strpos( $choices[ $legacy_id ], '(Legacy)' ), 'Legacy grid label includes (Legacy) tag.' );

	// Test choices limit filter
	add_filter( 'gridmaster_embed_grid_choices_limit', function () { return 10; } );
	$choices_limited = Embed_Helper::get_grid_choices( false );
	$expect( count( $choices_limited ) <= 10, 'gridmaster_embed_grid_choices_limit filter is respected.' );
	remove_all_filters( 'gridmaster_embed_grid_choices_limit' );

	// 5. Test Embed_Helper::get_edit_url()
	$modern_edit_url = Embed_Helper::get_edit_url( $grid_id );
	$expect( false !== strpos( $modern_edit_url, 'page=gridmaster-builder&id=' . $grid_id ), 'v2 grid returns gridmaster-builder edit URL for admin.' );

	$legacy_edit_url = Embed_Helper::get_edit_url( $legacy_id );
	$expect( false !== strpos( $legacy_edit_url, 'page=gridmaster&path=build-grid&id=' . $legacy_id ), 'Legacy grid returns build-grid edit URL for admin.' );

	// 6. Test authorization for non-admin
	$author_id = wp_insert_user( array(
		'user_login' => 'gm_author_' . wp_generate_password( 6, false ),
		'user_pass'  => wp_generate_password( 12, false ),
		'role'       => 'author',
	) );
	$expect( ! is_wp_error( $author_id ), 'Author user created.' );

	wp_set_current_user( $author_id );
	$expect( '' === Embed_Helper::get_edit_url( $grid_id ), 'Non-admin receives empty string for edit URL.' );
	$expect( '' === Embed_Helper::get_create_url(), 'Non-admin receives empty string for create URL.' );

	// Switch back to admin
	wp_set_current_user( $admins[0]->ID );

	// 7. Test Embed_Helper::render()
	$frontend_rendered = Embed_Helper::render( $grid_id );
	$expect( false !== strpos( $frontend_rendered, 'gm-grid-wrapper' ) || false !== strpos( $frontend_rendered, 'am_ajax_post_grid_wrap' ), 'Grid renders valid container markup on frontend.' );

	$empty_rendered = Embed_Helper::render( 0 );
	$expect( '' === $empty_rendered, 'Empty grid ID on frontend returns empty output.' );

	// 8. Test Embed_Helper::render_with_assets()
	$with_assets = Embed_Helper::render_with_assets( $grid_id );
	$expect( ! empty( $with_assets['html'] ), 'render_with_assets returns non-empty HTML.' );
	$expect( is_array( $with_assets['styles'] ), 'render_with_assets returns styles array.' );
	$expect( is_array( $with_assets['scripts'] ), 'render_with_assets returns scripts array.' );
	$expect( ! empty( $with_assets['edit_url'] ), 'render_with_assets returns edit_url for administrator.' );

	// 9. Test REST API embed-preview endpoint
	$rest_server = rest_get_server();

	// Anonymous request -> rejected
	wp_set_current_user( 0 );
	$anon_req = new WP_REST_Request( 'GET', '/gridmaster/v2/grids/' . $grid_id . '/embed-preview' );
	$anon_res = $rest_server->dispatch( $anon_req );
	$expect( 401 === $anon_res->get_status() || 403 === $anon_res->get_status(), 'Anonymous request to embed-preview rejected.' );

	// Author request -> accepted, but edit_url is empty
	wp_set_current_user( $author_id );
	$author_req = new WP_REST_Request( 'GET', '/gridmaster/v2/grids/' . $grid_id . '/embed-preview' );
	$author_res = $rest_server->dispatch( $author_req );
	$expect( 200 === $author_res->get_status(), 'Author with edit_posts can preview grid.' );
	$author_data = $author_res->get_data();
	$expect( true === $author_data['exists'], 'Preview response confirms grid exists.' );
	$expect( '' === $author_data['edit_url'], 'Author receives empty edit_url in REST response.' );

	// Admin request -> accepted with edit_url populated
	wp_set_current_user( $admins[0]->ID );
	$admin_req = new WP_REST_Request( 'GET', '/gridmaster/v2/grids/' . $grid_id . '/embed-preview' );
	$admin_res = $rest_server->dispatch( $admin_req );
	$expect( 200 === $admin_res->get_status(), 'Admin request to embed-preview succeeds.' );
	$admin_data = $admin_res->get_data();
	$expect( ! empty( $admin_data['edit_url'] ), 'Admin receives valid edit_url in REST response.' );

	// 404 for deleted/invalid grid
	$missing_req = new WP_REST_Request( 'GET', '/gridmaster/v2/grids/9999999/embed-preview' );
	$missing_res = $rest_server->dispatch( $missing_req );
	$expect( 404 === $missing_res->get_status(), 'Non-existent grid returns 404.' );

	// 10. Check widget registrations
	global $wp_widget_factory;
	$expect( isset( $wp_widget_factory->widgets['GridMaster\Integrations\Widget\Grid_Widget'] ), 'Classic WP Widget is registered in widget factory.' );

	if ( class_exists( '\Elementor\Plugin' ) ) {
		$elementor_widgets = \Elementor\Plugin::$instance->widgets_manager->get_widget_types();
		$expect( isset( $elementor_widgets['gridmaster-grid'] ), 'Elementor widget gridmaster-grid is registered in Elementor.' );
	}

	if ( class_exists( 'FLBuilderModel' ) && isset( \FLBuilderModel::$modules ) ) {
		$expect( isset( \FLBuilderModel::$modules['Grid_Module'] ), 'Beaver Builder module is registered in Beaver Builder.' );
	}

	// 11. Conditional loading audit: ensure builders that are inactive were NOT included
	$included = get_included_files();
	$inactive_checks = array();
	if ( ! did_action( 'elementor/loaded' ) && ! defined( 'ELEMENTOR_VERSION' ) ) {
		$inactive_checks['Elementor'] = '#/inc/Integrations/Elementor/#';
	}
	if ( ! class_exists( 'FLBuilder' ) ) {
		$inactive_checks['Beaver'] = '#/inc/Integrations/Beaver/#';
	}
	if ( ! defined( 'BRICKS_VERSION' ) && 'bricks' !== get_template() ) {
		$inactive_checks['Bricks'] = '#/inc/Integrations/Bricks/#';
	}
	if ( ! defined( 'WPB_VC_VERSION' ) && ! function_exists( 'vc_map' ) ) {
		$inactive_checks['WPBakery'] = '#/inc/Integrations/WPBakery/#';
	}

	foreach ( $inactive_checks as $builder_name => $pattern ) {
		foreach ( $included as $file ) {
			if ( preg_match( $pattern, $file ) ) {
				throw new RuntimeException( "Strict conditional loading violation: {$file} was included when {$builder_name} was inactive!" );
			}
		}
	}
	$expect( true, 'Strict conditional loading verified: inactive builder files were NOT included.' );

	echo "\nAll {$assertions} integration assertions PASSED successfully!\n";
} finally {
	wp_set_current_user( $original_user );
	foreach ( $posts as $pid ) {
		wp_delete_post( $pid, true );
	}
	if ( $grid_id ) {
		Grid_Repository::delete( $grid_id );
	}
	if ( $legacy_id ) {
		Grid_Repository::delete( $legacy_id );
	}
	if ( $author_id ) {
		wp_delete_user( $author_id );
	}
}

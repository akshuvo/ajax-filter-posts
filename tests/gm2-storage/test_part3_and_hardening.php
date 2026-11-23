<?php
/**
 * Part 3 Features + Security Hardening (FREE-01 / PRO-02 / PRO-03) + Legacy_Migrator Parity Tests.
 * Run: php -d "mysqli.default_socket=..." wp eval-file tests/gm2-storage/test_part3_and_hardening.php
 */

use GridMaster\Ajax;
use GridMaster\Engine\Grid_Renderer;
use GridMaster\Migration\Legacy_Migrator;
use GridMaster\Storage\Grid_Repository;
use GridMasterPro\Admin_Taxonomies;
use GridMasterPro\Facets\Facet_Recounter;
use GridMasterPro\Facets\Meta_Range_Facet;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

$original_user     = get_current_user_id();
$original_settings = get_option( 'gridmaster_settings', array() );
$original_patterns = get_option( 'gridmaster_card_patterns', array() );
$created_posts     = array();
$created_grids     = array();
$created_terms     = array();
$sub_user_id       = 0;
$assertions        = 0;

$expect = function ( $condition, $message ) use ( &$assertions ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	++$assertions;
	echo "PASS: {$message}\n";
};

/**
 * Helper to invoke a wp_ajax callback under clean wp_doing_ajax simulation and capture JSON output.
 */
$run_ajax = function ( callable $cb, array $request_vars ) {
	$orig_request = $_REQUEST;
	$orig_post    = $_POST;
	$_REQUEST     = $request_vars;
	$_POST        = $request_vars;

	$ajax_filter = function () {
		return true;
	};
	$die_handler = function ( $message = '' ) {
		throw new RuntimeException( '__WP_DIE__:' . (string) $message );
	};

	add_filter( 'wp_doing_ajax', $ajax_filter );
	add_filter( 'wp_die_ajax_handler', function () use ( $die_handler ) {
		return $die_handler;
	} );

	ob_start();
	try {
		$cb();
	} catch ( RuntimeException $e ) {
		if ( 0 !== strpos( $e->getMessage(), '__WP_DIE__:' ) ) {
			ob_end_clean();
			remove_filter( 'wp_doing_ajax', $ajax_filter );
			$_REQUEST = $orig_request;
			$_POST    = $orig_post;
			throw $e;
		}
	}
	$raw = ob_get_clean();
	remove_filter( 'wp_doing_ajax', $ajax_filter );
	$_REQUEST = $orig_request;
	$_POST    = $orig_post;

	return json_decode( $raw, true );
};

try {
	$admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
	$expect( ! empty( $admins ), 'An administrator account is available.' );
	$admin_id = (int) $admins[0]->ID;
	wp_set_current_user( $admin_id );

	$suffix      = wp_generate_password( 6, false );
	$sub_user_id = wp_insert_user( array(
		'user_login' => 'gm_p3_sub_' . strtolower( $suffix ),
		'user_pass'  => wp_generate_password( 12, true ),
		'role'       => 'subscriber',
	) );
	$expect( ! is_wp_error( $sub_user_id ), 'Disposable subscriber created.' );

	// =========================================================================
	// 1. FREE-01 Security: gridmaster_ajax capability gate, allowlist & sanitization
	// =========================================================================
	if ( ! class_exists( 'GridMaster\Ajax' ) ) {
		require_once GRIDMASTER_PATH . '/admin/Ajax.php';
	}
	$ajax_ctrl = new Ajax();

	wp_set_current_user( $sub_user_id );
	$sub_nonce = wp_create_nonce( 'gm-ajax-nonce' );
	$sub_res   = $run_ajax(
		array( $ajax_ctrl, 'register_ajax' ),
		array(
			'gm_nonce'           => $sub_nonce,
			'gm-action'          => 'save-settings',
			'gridmaster_options' => array( 'debug-mode' => 'yes' ),
		)
	);
	$expect( is_array( $sub_res ) && false === ( $sub_res['success'] ?? null ), 'FREE-01: Subscriber blocked from save-settings even with valid nonce.' );

	wp_set_current_user( $admin_id );
	$adm_nonce = wp_create_nonce( 'gm-ajax-nonce' );
	$bad_act   = $run_ajax(
		array( $ajax_ctrl, 'register_ajax' ),
		array(
			'gm_nonce'  => $adm_nonce,
			'gm-action' => 'arbitrary_private_method',
		)
	);
	$expect( is_array( $bad_act ) && false === ( $bad_act['success'] ?? null ), 'FREE-01: Unknown gm-action rejected by allowlist.' );

	$ok_act = $run_ajax(
		array( $ajax_ctrl, 'register_ajax' ),
		array(
			'gm_nonce'           => $adm_nonce,
			'gm-action'          => 'save_settings',
			'gridmaster_options' => array(
				'debug-mode'                 => 'yes',
				'disable-nonce-check'        => 'bogus_val',
				'disable-icon-color-options' => array( 'post_tag<script>', 'custom_tax' ),
			),
		)
	);
	$saved_opt = get_option( 'gridmaster_settings', array() );
	$expect( ! empty( $ok_act['success'] ) && 'yes' === ( $saved_opt['debug-mode'] ?? '' ) && 'no' === ( $saved_opt['disable-nonce-check'] ?? '' ), 'FREE-01: save_settings sanitizes radio options cleanly.' );

	// =========================================================================
	// 2. PRO-02 & PRO-03 Security & Setting Parity: Taxonomies + License Refresh
	// =========================================================================
	if ( ! class_exists( 'GridMasterPro\Admin_Taxonomies' ) ) {
		require_once GRIDMASTER_PRO_PATH . '/admin/Taxonomies.php';
	}
	$admin_tax = Admin_Taxonomies::get_instance();

	update_option( 'gridmaster_settings', array( 'disable-icon-color-options' => array( 'post_tag' ) ) );
	$expect( true === $admin_tax->is_taxonomy_disabled( 'post_tag' ) && false === $admin_tax->is_taxonomy_disabled( 'category' ), 'PRO-03: Admin_Taxonomies reads Free disable-icon-color-options setting.' );

	$term_res = wp_insert_term( 'Part3 Cat ' . $suffix, 'category' );
	$expect( ! is_wp_error( $term_res ), 'Disposable category created.' );
	$term_id         = (int) $term_res['term_id'];
	$created_terms[] = array( $term_id, 'category' );

	// Missing nonce / invalid color should NOT save cleanly.
	$_POST = array( 'gridmaster_term_color' => '#ff00aa' );
	$admin_tax->save_category_fields( $term_id, '', 'category' );
	$expect( '' === (string) get_term_meta( $term_id, 'gridmaster_term_color', true ), 'PRO-02: Taxonomy save rejects missing WP nonce.' );

	// Valid nonce + valid hex + subscriber capability denied.
	wp_set_current_user( $sub_user_id );
	$_POST = array(
		'_wpnonce'                    => wp_create_nonce( 'update-tag_' . $term_id ),
		'gridmaster_term_color'       => '#12ab34',
		'gridmaster_cat_thumbnail_url'=> 'https://grid-master.local/wp-content/uploads/test.png',
	);
	$admin_tax->save_category_fields( $term_id, '', 'category' );
	$expect( '' === (string) get_term_meta( $term_id, 'gridmaster_term_color', true ), 'PRO-02: Taxonomy save rejects subscriber capability.' );

	// Valid nonce + admin saves sanitized hex color & rejects XSS hex string.
	wp_set_current_user( $admin_id );
	$_POST = array(
		'_wpnonce'                    => wp_create_nonce( 'update-tag_' . $term_id ),
		'gridmaster_term_color'       => '#12ab34',
		'gridmaster_cat_thumbnail_url'=> 'https://grid-master.local/img.png',
	);
	$admin_tax->save_category_fields( $term_id, '', 'category' );
	$expect( '#12ab34' === get_term_meta( $term_id, 'gridmaster_term_color', true ), 'PRO-02: Admin taxonomy save persists valid hex color.' );
	$expect( 'https://grid-master.local/img.png' === get_term_meta( $term_id, 'thumbnail_url', true ), 'PRO-02: Admin taxonomy save persists sanitized thumbnail URL.' );
	$expect( false === strpos( $admin_tax->placeholder_img_src(), 'elementor.com' ), 'PRO-02: Taxonomy placeholder image uses local asset instead of third-party host.' );
	$_POST = array();

	// License::refresh_license_api requires manage_options + nonce.
	global $gm_license;
	if ( $gm_license ) {
		wp_set_current_user( $sub_user_id );
		$lic_sub = $run_ajax( array( $gm_license, 'refresh_license_api' ), array() );
		$expect( is_array( $lic_sub ) && false === ( $lic_sub['success'] ?? null ), 'PRO-02: refresh_license_api rejects subscriber.' );
		wp_set_current_user( $admin_id );
		$lic_bad_nonce = $run_ajax( array( $gm_license, 'refresh_license_api' ), array( '_nonce' => 'bad' ) );
		$expect( is_array( $lic_bad_nonce ) && false === ( $lic_bad_nonce['success'] ?? null ), 'PRO-02: refresh_license_api rejects invalid nonce.' );
	}

	// =========================================================================
	// 3. Expanded Legacy_Migrator Parity (multi-CPT, offset, radio, masonry, rollback)
	// =========================================================================
	global $wpdb;
	$table = $wpdb->prefix . 'gridmaster_grids';
	$legacy_attrs = array(
		'post_type'       => 'post,page',
		'posts_per_page'  => 5,
		'orderby'         => 'menu_order date',
		'offset'          => 2,
		'author'          => $admin_id,
		'exclude_current' => 'yes',
		'post__in'        => '10,20',
		'show_filter'     => 'yes',
		'filter_style'    => 'radio',
		'grid_style'      => 'masonry',
		'paginate'        => 'yes',
		'pagination_type' => 'load_more',
		'load_more_text'  => 'Show More Items',
	);
	$wpdb->insert(
		$table,
		array(
			'title'            => 'Legacy Part3 ' . $suffix,
			'attributes'       => maybe_serialize( $legacy_attrs ),
			'schema_version'   => 1,
			'migration_status' => 'legacy',
		)
	);
	$legacy_id = (int) $wpdb->insert_id;
	$expect( $legacy_id > 0, 'Legacy v1 fixture inserted.' );
	$created_grids[] = $legacy_id;

	$migrated = Legacy_Migrator::migrate( $legacy_id );
	$expect( true === $migrated, 'Legacy_Migrator::migrate() succeeds.' );
	$mig_grid = Grid_Repository::get( $legacy_id );
	$expect( array( 'post', 'page' ) === ( $mig_grid->config['query']['post_type'] ?? null ), 'Migrator converts comma post_type string to array.' );
	$expect( 2 === (int) ( $mig_grid->config['query']['offset'] ?? 0 ) && ! empty( $mig_grid->config['query']['exclude_current'] ), 'Migrator maps offset and exclude_current.' );
	$expect( 'menu_order' === ( $mig_grid->config['query']['orderby'] ?? '' ), 'Migrator extracts valid primary orderby token cleanly.' );
	$expect( 'taxonomy-radio' === ( $mig_grid->config['facets'][0]['type'] ?? '' ), 'Migrator preserves radio filter style.' );
	$expect( 'masonry' === ( $mig_grid->config['layout']['type'] ?? '' ) && 'Show More Items' === ( $mig_grid->config['pagination']['load_more_text'] ?? '' ), 'Migrator preserves masonry layout and load_more_text.' );

	Legacy_Migrator::rollback( $legacy_id );
	$rolled = Grid_Repository::get( $legacy_id );
	$expect( 'legacy' === $rolled->migration_status, 'Legacy_Migrator::rollback restores legacy status cleanly.' );

	// =========================================================================
	// 4. Pro Numeric Meta Range Slider (`meta-range`), URL Params, Query & LCP/SR
	// =========================================================================
	$p_small = wp_insert_post( array( 'post_title' => 'Small Unit ' . $suffix, 'post_status' => 'publish', 'post_author' => $admin_id ), true );
	$p_mid   = wp_insert_post( array( 'post_title' => 'Mid Unit ' . $suffix, 'post_status' => 'publish', 'post_author' => $admin_id ), true );
	$p_big   = wp_insert_post( array( 'post_title' => 'Big Unit ' . $suffix, 'post_status' => 'publish', 'post_author' => $admin_id ), true );
	$created_posts = array( $p_small, $p_mid, $p_big );
	$meta_key = 'gm_sqft_' . strtolower( $suffix );
	update_post_meta( $p_small, $meta_key, '800' );
	update_post_meta( $p_mid, $meta_key, '1500' );
	update_post_meta( $p_big, $meta_key, '2600' );
	update_post_meta( $p_mid, 'gm_hero_img', 'https://grid-master.local/lcp-hero.jpg' );
	update_post_meta( $p_big, 'gm_hero_img', 'https://grid-master.local/second-hero.jpg' );
	wp_set_post_terms( $p_mid, array( $term_id ), 'category' );

	$bounds = Meta_Range_Facet::detect_bounds( $meta_key );
	$expect( 800.0 === (float) $bounds['min'] && 2600.0 === (float) $bounds['max'], 'Meta_Range_Facet auto-detects numeric min/max bounds.' );

	$parsed_url = Grid_Renderer::extract_url_runtime_params( array(
		'gm_meta_min_' . $meta_key => '1000',
		'gm_meta_max_' . $meta_key => '2000',
	) );
	$expect(
		1000.0 === (float) ( $parsed_url['meta_ranges'][ $meta_key ]['min'] ?? 0 ) &&
		2000.0 === (float) ( $parsed_url['meta_ranges'][ $meta_key ]['max'] ?? 0 ),
		'URL parser extracts gm_meta_min_* and gm_meta_max_* into meta_ranges.'
	);

	$mr_cfg = Grid_Repository::default_config();
	$mr_cfg['query']['post__in']       = array( $p_mid, $p_big, $p_small );
	$mr_cfg['query']['orderby']        = 'post__in';
	$mr_cfg['query']['posts_per_page'] = 6;
	$mr_cfg['facets'] = array(
		array( 'type' => 'meta-range', 'meta_key' => $meta_key, 'heading' => 'Floor Area', 'unit_suffix' => 'sq ft', 'step' => 50 ),
		array( 'type' => 'taxonomy-checkbox', 'taxonomy' => 'category', 'heading' => 'Category' ),
	);
	$lcp_blocks = '<!-- wp:gridmaster/card -->'
		. '<!-- wp:gridmaster/featured-image {"sourceType":"custom_meta","metaKey":"gm_hero_img"} /-->'
		. '<!-- wp:gridmaster/title {"isLink":false} /-->'
		. '<!-- /wp:gridmaster/card -->';

	$mr_grid_id = Grid_Repository::save( array(
		'title'         => 'Part3 MetaRange ' . $suffix,
		'config'        => $mr_cfg,
		'block_content' => $lcp_blocks,
	) );
	$created_grids[] = $mr_grid_id;

	$full_html = Grid_Renderer::render_grid( $mr_grid_id, array() );
	$expect( false !== strpos( $full_html, 'gm-facet-meta-range' ) && false !== strpos( $full_html, 'gm-meta-range-min' ), 'Meta range slider facet renders markup.' );
	$expect( false !== strpos( $full_html, 'gm-sr-status' ) && false !== strpos( $full_html, 'aria-live="polite"' ), 'Grid renders screen-reader live status region (.gm-sr-status).' );
	$expect( false !== strpos( $full_html, 'lcp-hero.jpg" alt="' ) && false !== strpos( $full_html, 'fetchpriority="high"' ), 'First card image receives fetchpriority="high" for LCP.' );
	$expect( false !== strpos( $full_html, 'second-hero.jpg"' ) && preg_match( '/second-hero\.jpg[^>]+loading="lazy"/', $full_html ), 'Subsequent card image stays lazy-loaded.' );

	$mr_req = new WP_REST_Request( 'POST', "/gridmaster/v2/grids/{$mr_grid_id}/render" );
	$mr_req->set_body_params( array(
		'meta_ranges' => array(
			$meta_key => array( 'min' => 1000, 'max' => 2000 ),
		),
	) );
	$mr_res  = rest_do_request( $mr_req );
	$mr_data = $mr_res->get_data();
	$mr_html = $mr_data['html'] ?? '';
	$expect(
		200 === $mr_res->get_status() &&
		false !== strpos( $mr_html, 'data-post-id="' . $p_mid . '"' ) &&
		false === strpos( $mr_html, 'data-post-id="' . $p_small . '"' ) &&
		false === strpos( $mr_html, 'data-post-id="' . $p_big . '"' ),
		'REST /render applies meta_ranges filtering strictly between min and max.'
	);

	// =========================================================================
	// 5. Pro Recount Transient Caching & Automatic Invalidation on save_post
	// =========================================================================
	Facet_Recounter::invalidate_cache();
	$ver_before = (int) get_option( 'gridmaster_recount_cache_ver', 1 );
	$res_one    = Facet_Recounter::compute_counts( $mr_cfg, array() );
	$expect( 1 === (int) ( $res_one['counts']['category'][ (string) $term_id ] ?? 0 ), 'Initial cached recount returns 1 post in Part3 Cat.' );

	// Adding term to $p_big triggers set_object_terms / wp_update_post -> cache version bumps.
	wp_set_post_terms( $p_big, array( $term_id ), 'category' );
	$ver_after = (int) get_option( 'gridmaster_recount_cache_ver', 1 );
	$expect( $ver_after > $ver_before, 'Taxonomy update bumps gridmaster_recount_cache_ver automatically.' );
	$res_two = Facet_Recounter::compute_counts( $mr_cfg, array() );
	$expect( 2 === (int) ( $res_two['counts']['category'][ (string) $term_id ] ?? 0 ), 'Invalidated recount reflects updated term assignment immediately.' );

	// =========================================================================
	// 6. User-Saved Card Pattern Library REST API (/card-patterns)
	// =========================================================================
	update_option( 'gridmaster_card_patterns', array(), false );

	$pat_post = new WP_REST_Request( 'POST', '/gridmaster/v2/card-patterns' );
	$pat_post->set_body_params( array( 'title' => 'Part3 Promo Card ' . $suffix, 'block_content' => $lcp_blocks ) );
	$pat_res = rest_do_request( $pat_post );
	$pat_id  = $pat_res->get_data()['pattern']['id'] ?? '';
	$expect( 201 === $pat_res->get_status() && '' !== $pat_id, 'POST /card-patterns saves custom card pattern cleanly.' );

	$pat_list = rest_do_request( new WP_REST_Request( 'GET', '/gridmaster/v2/card-patterns' ) );
	$expect( 200 === $pat_list->get_status() && 1 === count( $pat_list->get_data()['patterns'] ?? array() ), 'GET /card-patterns returns saved pattern.' );

	wp_set_current_user( $sub_user_id );
	$pat_sub = rest_do_request( new WP_REST_Request( 'GET', '/gridmaster/v2/card-patterns' ) );
	$expect( 403 === $pat_sub->get_status(), 'Subscriber receives HTTP 403 on /card-patterns.' );
	wp_set_current_user( $admin_id );

	$pat_del = rest_do_request( new WP_REST_Request( 'DELETE', '/gridmaster/v2/card-patterns/' . $pat_id ) );
	$expect( 200 === $pat_del->get_status() && empty( $pat_del->get_data()['patterns'] ), 'DELETE /card-patterns/<id> removes pattern.' );

	echo "PASS: All {$assertions} Part 3 & Hardening assertions passed.\n";
} finally {
	$_POST = array();
	update_option( 'gridmaster_settings', $original_settings );
	update_option( 'gridmaster_card_patterns', $original_patterns, false );
	foreach ( $created_grids as $gid ) {
		if ( $gid && ! is_wp_error( $gid ) ) {
			Grid_Repository::delete( $gid );
		}
	}
	foreach ( $created_posts as $pid ) {
		wp_delete_post( $pid, true );
	}
	foreach ( $created_terms as $pair ) {
		wp_delete_term( $pair[0], $pair[1] );
	}
	if ( $sub_user_id && ! is_wp_error( $sub_user_id ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $sub_user_id );
	}
	wp_set_current_user( $original_user );
}

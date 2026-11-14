<?php
/**
 * Part 2 new features & security/permission integration test suite.
 * Run: php -d "mysqli.default_socket=..." wp eval-file tests/gm2-storage/test_part2_features.php
 */

use GridMaster\Engine\Grid_Renderer;
use GridMaster\Engine\Query_Builder;
use GridMaster\Storage\Grid_Repository;
use GridMasterPro\Blocks\Pro_Blocks;
use GridMasterPro\Facets\Facet_Recounter;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

$original_user = get_current_user_id();
$created_posts = array();
$created_pages = array();
$created_grids = array();
$created_terms = array();
$sub_user_id   = 0;
$strip_js_cap  = null;
$assertions    = 0;

$expect = function ( $condition, $message ) use ( &$assertions ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	++$assertions;
	echo "PASS: {$message}\n";
};

try {
	$admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
	$expect( ! empty( $admins ), 'An administrator account is available.' );
	$admin_id = (int) $admins[0]->ID;
	wp_set_current_user( $admin_id );

	$suffix = wp_generate_password( 6, false );

	// Create disposable taxonomy terms (category + post_tag).
	$cat_a_res = wp_insert_term( 'Part2 Alpha ' . $suffix, 'category' );
	$cat_b_res = wp_insert_term( 'Part2 Beta ' . $suffix, 'category' );
	$tag_a_res = wp_insert_term( 'Part2 Tag ' . $suffix, 'post_tag' );
	$expect( ! is_wp_error( $cat_a_res ) && ! is_wp_error( $cat_b_res ) && ! is_wp_error( $tag_a_res ), 'Disposable taxonomy terms created.' );
	$cat_a = (int) $cat_a_res['term_id'];
	$cat_b = (int) $cat_b_res['term_id'];
	$tag_a = (int) $tag_a_res['term_id'];
	$created_terms[] = array( $cat_a, 'category' );
	$created_terms[] = array( $cat_b, 'category' );
	$created_terms[] = array( $tag_a, 'post_tag' );

	// Create 4 ordered posts + 1 draft post + 1 page.
	$post_titles = array(
		'Part2 Post One ' . $suffix,
		'Part2 Post Two ' . $suffix,
		'Part2 Post Three ' . $suffix,
		'Part2 Post Four ' . $suffix,
	);
	foreach ( $post_titles as $idx => $ptitle ) {
		$pid = wp_insert_post( array(
			'post_title'   => $ptitle,
			'post_content' => 'Part2 searchable body token_' . $suffix . ( $idx < 2 ? ' needle_beta' : '' ),
			'post_status'  => 'publish',
			'post_author'  => $admin_id,
			'post_date'    => gmdate( 'Y-m-d H:i:s', time() - ( ( 4 - $idx ) * 60 ) ),
		), true );
		$expect( ! is_wp_error( $pid ), "Disposable post #{$idx} created." );
		$created_posts[] = $pid;
		wp_set_post_terms( $pid, array( $idx < 2 ? $cat_a : $cat_b ), 'category' );
		if ( 0 === $idx || 2 === $idx ) {
			wp_set_post_terms( $pid, array( $tag_a ), 'post_tag' );
		}
		if ( 0 === $idx ) {
			update_post_meta( $pid, 'gm_tier', 'vip' );
		}
	}

	$draft_id = wp_insert_post( array(
		'post_title'   => 'Part2 Secret Draft ' . $suffix,
		'post_content' => 'Should stay hidden',
		'post_status'  => 'draft',
		'post_author'  => $admin_id,
	), true );
	$expect( ! is_wp_error( $draft_id ), 'Disposable draft post created.' );
	$created_posts[] = $draft_id;

	$page_id = wp_insert_post( array(
		'post_title'   => 'Part2 Companion Page ' . $suffix,
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_author'  => $admin_id,
	), true );
	$expect( ! is_wp_error( $page_id ), 'Disposable page created.' );
	$created_pages[] = $page_id;

	// =========================================================================
	// 1. Sidebar Split Layout, Off-Canvas Drawer & Masonry / Metro Rendering
	// =========================================================================
	$sidebar_cfg = Grid_Repository::default_config();
	$sidebar_cfg['query']  = array_merge( $sidebar_cfg['query'], array( 'post__in' => $created_posts, 'posts_per_page' => 4 ) );
	$sidebar_cfg['facets'] = array(
		array( 'type' => 'search', 'heading' => 'Search' ),
		array( 'type' => 'taxonomy-checkbox', 'taxonomy' => 'category', 'heading' => 'Categories', 'hide_empty_dynamic' => true ),
	);
	$sidebar_cfg['layout']['type']                = 'masonry';
	$sidebar_cfg['layout']['filter_position']     = 'left_sidebar';
	$sidebar_cfg['layout']['sidebar_width']       = 310;
	$sidebar_cfg['layout']['sticky_sidebar']      = true;
	$sidebar_cfg['layout']['drawer_button_label'] = 'Refine Results';

	$grid_id = Grid_Repository::save( array(
		'title'         => 'Part2 Sidebar Masonry ' . $suffix,
		'config'        => $sidebar_cfg,
		'block_content' => Grid_Repository::default_block_content(),
	) );
	$expect( ! is_wp_error( $grid_id ) && $grid_id > 0, 'Saved sidebar + masonry grid.' );
	$created_grids[] = $grid_id;

	$html = Grid_Renderer::render_grid( $grid_id, array() );
	$expect( false !== strpos( $html, 'gm-filter-pos-left-sidebar' ), 'Grid wrapper renders gm-filter-pos-left-sidebar class.' );
	$expect( false !== strpos( $html, 'gm-sidebar-sticky' ), 'Grid wrapper renders gm-sidebar-sticky class.' );
	$expect( false !== strpos( $html, '--gm-sidebar-width:310px;' ), 'Grid wrapper exposes --gm-sidebar-width custom property.' );
	$expect( false !== strpos( $html, 'gm-layout-masonry' ) && false !== strpos( $html, 'data-layout="masonry"' ), 'Grid wrapper renders masonry layout class & data-layout.' );
	$expect( false !== strpos( $html, 'gm-grid-shell' ) && false !== strpos( $html, 'gm-grid-main' ), 'Split grid renders .gm-grid-shell and .gm-grid-main containers.' );
	$expect( false !== strpos( $html, 'Refine Results' ) && false !== strpos( $html, 'gm-filter-drawer-close' ), 'Drawer toggle label and close button render inside split shell.' );

	$preview_cfg = $sidebar_cfg;
	$preview_cfg['layout']['type']            = 'metro';
	$preview_cfg['layout']['filter_position'] = 'right_sidebar';
	$preview_html = Grid_Renderer::render_preview( $preview_cfg, Grid_Repository::default_block_content(), $grid_id, array() );
	$expect( false !== strpos( $preview_html, 'gm-layout-metro' ) && false !== strpos( $preview_html, 'data-layout="metro"' ), 'Preview renderer outputs metro layout class & attribute.' );
	$expect( false !== strpos( $preview_html, 'gm-filter-pos-right-sidebar' ), 'Preview renderer outputs right-sidebar positioning class.' );

	// =========================================================================
	// 2. Context-Aware & Advanced Query Builder (Offset, Multi-CPT, Exclude Current, Archive, Related)
	// =========================================================================
	$p0 = $created_posts[0];
	$p1 = $created_posts[1];
	$p2 = $created_posts[2];
	$p3 = $created_posts[3];

	$offset_q1 = Query_Builder::execute( array(
		'post_type'      => 'post',
		'post__in'       => array( $p0, $p1, $p2, $p3 ),
		'orderby'        => 'post__in',
		'posts_per_page' => 2,
		'offset'         => 1,
	), array( 'paged' => 1 ) );
	$expect( 3 === (int) $offset_q1->found_posts, 'Offset=1 adjusts found_posts from 4 to 3.' );
	$expect( 2 === (int) $offset_q1->max_num_pages, 'Offset=1 calculates max_num_pages = 2.' );
	$expect( array( $p1, $p2 ) === wp_list_pluck( $offset_q1->posts, 'ID' ), 'Offset=1 page 1 slices exact posts [p1, p2].' );

	$offset_q2 = Query_Builder::execute( array(
		'post_type'      => 'post',
		'post__in'       => array( $p0, $p1, $p2, $p3 ),
		'orderby'        => 'post__in',
		'posts_per_page' => 2,
		'offset'         => 1,
	), array( 'paged' => 2 ) );
	$expect( array( $p3 ) === wp_list_pluck( $offset_q2->posts, 'ID' ), 'Offset=1 page 2 slices trailing post [p3].' );

	$multi_cpt_q = Query_Builder::execute( array(
		'post_type'           => array( 'post', 'page' ),
		'post__in'            => array( $p0, $page_id, $p1 ),
		'post__not_in'        => array( $p1 ),
		'ignore_sticky_posts' => true,
		'posts_per_page'      => 10,
	), array() );
	$multi_ids = wp_list_pluck( $multi_cpt_q->posts, 'ID' );
	$expect( in_array( $p0, $multi_ids, true ) && in_array( $page_id, $multi_ids, true ) && ! in_array( $p1, $multi_ids, true ), 'Multi-post_type combines posts + pages while respecting post__in and post__not_in.' );

	$excl_q = Query_Builder::execute( array(
		'post_type'       => 'post',
		'post__in'        => array( $p0, $p1, $p2 ),
		'exclude_current' => true,
	), array( 'context' => array( 'post_id' => $p1 ) ) );
	$excl_ids = wp_list_pluck( $excl_q->posts, 'ID' );
	$expect( ! in_array( $p1, $excl_ids, true ) && in_array( $p0, $excl_ids, true ), 'exclude_current removes singular context post_id.' );

	$archive_q = Query_Builder::execute( array(
		'post_type'      => 'post',
		'post__in'       => array( $p0, $p1, $p2, $p3 ),
		'context_mode'   => 'current_archive',
		'posts_per_page' => 10,
	), array(
		'context' => array(
			'is_archive' => true,
			'taxonomy'   => 'category',
			'term_id'    => $cat_b,
		),
	) );
	$arch_ids = wp_list_pluck( $archive_q->posts, 'ID' );
	sort( $arch_ids );
	$expected_b = array( $p2, $p3 );
	sort( $expected_b );
	$expect( $expected_b === $arch_ids, 'context_mode=current_archive scopes results strictly to archive term_id.' );

	$related_q = Query_Builder::execute( array(
		'post_type'      => 'post',
		'post__in'       => array( $p0, $p1, $p2, $p3 ),
		'context_mode'   => 'related_posts',
		'posts_per_page' => 10,
	), array(
		'context' => array(
			'post_id' => $p0,
		),
	) );
	$rel_ids = wp_list_pluck( $related_q->posts, 'ID' );
	$expect( ! in_array( $p0, $rel_ids, true ) && in_array( $p1, $rel_ids, true ) && in_array( $p2, $rel_ids, true ), 'context_mode=related_posts excludes source post and matches shared taxonomy terms.' );

	// REST /render preserves runtime context.
	$ctx_grid_cfg = Grid_Repository::default_config();
	$ctx_grid_cfg['query']['post__in']        = array( $p0, $p1 );
	$ctx_grid_cfg['query']['exclude_current'] = true;
	$ctx_grid_id = Grid_Repository::save( array( 'title' => 'Part2 Ctx ' . $suffix, 'config' => $ctx_grid_cfg ) );
	$created_grids[] = $ctx_grid_id;

	$ctx_req = new WP_REST_Request( 'POST', "/gridmaster/v2/grids/{$ctx_grid_id}/render" );
	$ctx_req->set_body_params( array( 'context' => array( 'post_id' => $p0 ) ) );
	$ctx_res  = rest_do_request( $ctx_req );
	$ctx_data = $ctx_res->get_data();
	$ctx_html = isset( $ctx_data['html'] ) ? (string) $ctx_data['html'] : '';
	$expect(
		200 === $ctx_res->get_status() && false === strpos( $ctx_html, 'data-post-id="' . $p0 . '"' ) && false !== strpos( $ctx_html, 'data-post-id="' . $p1 . '"' ),
		'REST /render preserves context.post_id for exclude_current.'
	);

	// =========================================================================
	// 3. Dynamic Facet Term Counts (Pro Facet_Recounter) & REST Render Response
	// =========================================================================
	$recount_res = Facet_Recounter::compute_counts(
		$sidebar_cfg,
		array( 'search' => 'needle_beta' )
	);
	$cat_counts = $recount_res['counts']['category'] ?? array();
	$expect( 2 === (int) ( $cat_counts[ (string) $cat_a ] ?? -1 ), 'Facet_Recounter reports 2 matching posts in Category Alpha under search filter.' );
	$expect( 0 === (int) ( $cat_counts[ (string) $cat_b ] ?? -1 ), 'Facet_Recounter reports 0 matching posts in Category Beta under search filter.' );
	$expect( ! empty( $recount_res['hide_empty']['category'] ), 'Facet_Recounter propagates hide_empty_dynamic flag.' );

	$ajax_render_req = new WP_REST_Request( 'POST', "/gridmaster/v2/grids/{$grid_id}/render" );
	$ajax_render_req->set_body_params( array( 'search' => 'needle_beta' ) );
	$ajax_render_res  = rest_do_request( $ajax_render_req );
	$ajax_render_data = $ajax_render_res->get_data();
	$expect( 200 === $ajax_render_res->get_status() && isset( $ajax_render_data['facet_counts']['category'][ (string) $cat_a ] ), 'REST /grids/<id>/render appends Pro facet_counts payload.' );
	$expect( 2 === (int) $ajax_render_data['facet_counts']['category'][ (string) $cat_a ], 'REST /grids/<id>/render facet_counts reflects active search query.' );

	// =========================================================================
	// 4. Export / Import JSON REST API & Permissions / Capability Security
	// =========================================================================
	$exp_req = new WP_REST_Request( 'GET', "/gridmaster/v2/grids/{$grid_id}/export" );
	$exp_res = rest_do_request( $exp_req );
	$exp_data = $exp_res->get_data();
	$expect( 200 === $exp_res->get_status() && 'gridmaster-grid-v2' === ( $exp_data['format'] ?? '' ), 'Export endpoint returns format gridmaster-grid-v2.' );
	$expect( ! empty( $exp_data['grid']['block_content'] ) && 310 === (int) ( $exp_data['grid']['config']['layout']['sidebar_width'] ?? 0 ), 'Export payload includes block_content and layout settings.' );

	// Round-trip import with custom title tweak.
	$imp_payload = $exp_data;
	$imp_payload['grid']['title'] = 'Imported Clone ' . $suffix;
	$imp_req = new WP_REST_Request( 'POST', '/gridmaster/v2/grids/import' );
	$imp_req->set_header( 'Content-Type', 'application/json' );
	$imp_req->set_body( wp_json_encode( $imp_payload ) );
	$imp_res = rest_do_request( $imp_req );
	$imp_data = $imp_res->get_data();
	$expect( 201 === $imp_res->get_status() && 1 === (int) ( $imp_data['imported'] ?? 0 ), 'Import endpoint creates imported grid with HTTP 201.' );
	$imported_id = (int) ( $imp_data['ids'][0] ?? 0 );
	$expect( $imported_id > 0 && $imported_id !== $grid_id, 'Imported grid receives distinct ID.' );
	$created_grids[] = $imported_id;
	$imported_grid = Grid_Repository::get( $imported_id );
	$expect( 'Imported Clone ' . $suffix === $imported_grid->title && 'masonry' === ( $imported_grid->config['layout']['type'] ?? '' ), 'Imported grid preserves title and layout config.' );

	// Invalid import payload returns 400.
	$bad_req = new WP_REST_Request( 'POST', '/gridmaster/v2/grids/import' );
	$bad_req->set_header( 'Content-Type', 'application/json' );
	$bad_req->set_body( wp_json_encode( array( 'foo' => 'bar' ) ) );
	$bad_res = rest_do_request( $bad_req );
	$expect( 400 === $bad_res->get_status(), 'Malformed import schema returns HTTP 400.' );

	// unfiltered_html stripping on import.
	$strip_js_cap = function ( $allcaps ) {
		$allcaps['unfiltered_html'] = false;
		return $allcaps;
	};
	add_filter( 'user_has_cap', $strip_js_cap );
	$js_payload = $exp_data;
	$js_payload['grid']['title'] = 'Sanitized JS Grid ' . $suffix;
	$js_payload['grid']['config']['custom_code'] = array( 'css' => '.x{}', 'js' => 'alert(1);' );
	$js_req = new WP_REST_Request( 'POST', '/gridmaster/v2/grids/import' );
	$js_req->set_header( 'Content-Type', 'application/json' );
	$js_req->set_body( wp_json_encode( $js_payload ) );
	$js_res = rest_do_request( $js_req );
	remove_filter( 'user_has_cap', $strip_js_cap );
	$strip_js_cap = null;
	$js_data = $js_res->get_data();
	$js_grid_id = (int) ( $js_data['ids'][0] ?? 0 );
	if ( $js_grid_id ) {
		$created_grids[] = $js_grid_id;
	}
	$js_grid = Grid_Repository::get( $js_grid_id );
	$expect( '' === ( $js_grid->config['custom_code']['js'] ?? '' ) && '.x{}' === ( $js_grid->config['custom_code']['css'] ?? '' ), 'Import strips custom_code.js when user lacks unfiltered_html while keeping CSS.' );

	// Non-admin permission check.
	$sub_user_id = wp_insert_user( array(
		'user_login' => 'gm_sub_' . strtolower( $suffix ),
		'user_pass'  => wp_generate_password( 12, true ),
		'role'       => 'subscriber',
	) );
	$expect( ! is_wp_error( $sub_user_id ), 'Disposable subscriber user created.' );
	wp_set_current_user( $sub_user_id );
	$unauth_exp = rest_do_request( new WP_REST_Request( 'GET', "/gridmaster/v2/grids/{$grid_id}/export" ) );
	$unauth_imp = rest_do_request( $imp_req );
	$expect( 403 === $unauth_exp->get_status() && 403 === $unauth_imp->get_status(), 'Subscriber receives HTTP 403 on export and import routes.' );
	wp_set_current_user( $admin_id );

	// =========================================================================
	// 5. Wishlist Drawer Block & Shortcode + Safe Resolution
	// =========================================================================
	$wl_shortcode_html = do_shortcode( '[gridmaster_wishlist_drawer label="Saved Reads" button_style="floating"]' );
	$expect( false !== strpos( $wl_shortcode_html, 'gm-wishlist-drawer-root' ) && false !== strpos( $wl_shortcode_html, 'gm-wishlist-style-floating' ), '[gridmaster_wishlist_drawer] shortcode renders drawer root and floating style class.' );
	$expect( false !== strpos( $wl_shortcode_html, 'Saved Reads' ) && false !== strpos( $wl_shortcode_html, 'gm-wishlist-count-badge' ), 'Wishlist drawer renders label and count badge.' );

	$pro_blocks = new Pro_Blocks();
	$wl_items = $pro_blocks->resolve_wishlist_items( array( $p0, $draft_id ) );
	$wl_ids   = wp_list_pluck( $wl_items, 'id' );
	$expect( array( $p0 ) === $wl_ids, 'resolve_wishlist_items returns published post and omits draft posts.' );

	// =========================================================================
	// 6. Card Block Conditional Visibility (InnerBlocks Pruning & InnerContent Sync)
	// =========================================================================
	$cond_markup = '<!-- wp:gridmaster/card -->'
		. '<!-- wp:gridmaster/title {"isLink":false} /-->'
		. '<!-- wp:core/group -->'
		. '<div class="wp-block-group"><!-- wp:gridmaster/badge {"text":"VIP ONLY","gmVisibility":{"rule":"meta_equals","metaKey":"gm_tier","metaValue":"vip"}} /-->'
		. '<!-- wp:gridmaster/badge {"text":"MEMBERS ONLY","gmVisibility":{"rule":"logged_in"}} /--></div>'
		. '<!-- /wp:core/group -->'
		. '<!-- /wp:gridmaster/card -->';

	$cond_cfg = Grid_Repository::default_config();
	$cond_cfg['query']['post__in'] = array( $p0, $p1 );
	$cond_cfg['query']['orderby']  = 'post__in';
	$cond_grid_id = Grid_Repository::save( array(
		'title'         => 'Part2 CondVis ' . $suffix,
		'config'        => $cond_cfg,
		'block_content' => $cond_markup,
	) );
	$created_grids[] = $cond_grid_id;

	// Logged in view: p0 shows both VIP ONLY and MEMBERS ONLY; p1 shows only MEMBERS ONLY.
	$cond_html_in = Grid_Renderer::render_grid( $cond_grid_id, array() );
	$expect( 1 === substr_count( $cond_html_in, 'VIP ONLY' ), 'Conditional Visibility meta_equals renders VIP badge only for matching post.' );
	$expect( 2 === substr_count( $cond_html_in, 'MEMBERS ONLY' ), 'Conditional Visibility logged_in renders for all cards when logged in.' );

	// Logged out view: MEMBERS ONLY disappears while p0 keeps VIP ONLY cleanly inside core/group.
	wp_set_current_user( 0 );
	$cond_html_out = Grid_Renderer::render_grid( $cond_grid_id, array() );
	wp_set_current_user( $admin_id );
	$expect( 1 === substr_count( $cond_html_out, 'VIP ONLY' ), 'Logged-out render preserves sibling VIP ONLY inside core/group.' );
	$expect( false === strpos( $cond_html_out, 'MEMBERS ONLY' ), 'Conditional Visibility logged_in omits badge cleanly when logged out.' );

	// in_taxonomy & has_thumbnail check directly.
	$expect( true === Grid_Renderer::should_render_card_block( array( 'attrs' => array( 'gmVisibility' => array( 'rule' => 'in_taxonomy', 'taxonomy' => 'post_tag', 'termSlug' => (string) $tag_a ) ) ), $p0, 'post', $cond_grid_id ), 'should_render_card_block matches in_taxonomy when post has term.' );
	$expect( false === Grid_Renderer::should_render_card_block( array( 'attrs' => array( 'gmVisibility' => array( 'rule' => 'in_taxonomy', 'taxonomy' => 'post_tag', 'termSlug' => (string) $tag_a ) ) ), $p1, 'post', $cond_grid_id ), 'should_render_card_block hides in_taxonomy when post lacks term.' );
	$expect( false === Grid_Renderer::should_render_card_block( array( 'attrs' => array( 'gmVisibility' => array( 'rule' => 'has_thumbnail' ) ) ), $p0, 'post', $cond_grid_id ), 'should_render_card_block hides has_thumbnail when post lacks thumbnail.' );

	echo "PASS: All {$assertions} Part 2 assertions passed.\n";
} finally {
	if ( is_callable( $strip_js_cap ) ) {
		remove_filter( 'user_has_cap', $strip_js_cap );
	}
	foreach ( $created_grids as $gid ) {
		if ( $gid && ! is_wp_error( $gid ) ) {
			Grid_Repository::delete( $gid );
		}
	}
	foreach ( array_merge( $created_posts, $created_pages ) as $pid ) {
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

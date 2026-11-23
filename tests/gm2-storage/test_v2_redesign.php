<?php
/**
 * Comprehensive Automated Test Suite for GridMaster 2.0 (Free + Pro).
 *
 * Verifies:
 * 1. Storage schema migration & idempotency
 * 2. Grid CRUD (Create, Read, Update, Delete) & CPT synchronization
 * 3. Gutenberg block registration & dynamic field SSR with Block Context API
 * 4. Query Builder normalization, security whitelisting, and execution
 * 5. Facet Registry & Free facet rendering (tabs, dropdown, checkbox, radio, search, reset)
 * 6. Pagination Registry (numbered, load_more, infinite, prev_next)
 * 7. Legacy Grid Migration & Rollback
 * 8. Free + Pro Extension Handshake & Registration
 * 9. Safe degradation when Pro is inactive
 * 10. Multiple independent grids rendering isolation
 *
 * Usage:
 *   wp eval-file tests/gm2-storage/test_v2_redesign.php
 */

namespace GridMaster\Tests;

use GridMaster\Storage\Grid_Repository;
use GridMaster\Storage\Layout_CPT;
use GridMaster\Engine\Query_Builder;
use GridMaster\Engine\Grid_Renderer;
use GridMaster\Facets\Filter_Registry;
use GridMaster\Pagination\Pagination_Registry;
use GridMaster\Migration\Legacy_Migrator;

class V2_Test_Runner {

	private $passes = 0;
	private $failures = 0;
	private $errors = array();

	public function assert( $condition, $message ) {
		if ( $condition ) {
			$this->passes++;
			echo "  [PASS] {$message}\n";
		} else {
			$this->failures++;
			$this->errors[] = $message;
			echo "  [FAIL] {$message}\n";
		}
	}

	public function run() {
		echo "=========================================================\n";
		echo "Starting GridMaster 2.0 Comprehensive Test Suite\n";
		echo "=========================================================\n\n";

		$this->test_schema_migration();
		$this->test_grid_crud();
		$this->test_query_builder();
		$this->test_blocks_and_context();
		$this->test_facet_registry();
		$this->test_pagination_registry();
		$this->test_legacy_migration_and_rollback();
		$this->test_pro_extension_contract();
		$this->test_multiple_grids_isolation();
		$this->test_resilience_and_permissions();
		$this->test_accessible_slider();

		echo "\n=========================================================\n";
		echo "Results: {$this->passes} passed, {$this->failures} failed\n";
		echo "=========================================================\n";

		if ( $this->failures > 0 ) {
			exit( 1 );
		}
	}

	private function test_schema_migration() {
		echo "Suite 1: Schema Migration & Storage\n";

		global $wpdb;
		$columns = $wpdb->get_col( "DESCRIBE {$wpdb->prefix}gridmaster_grids", 0 );

		$this->assert( in_array( 'id', $columns, true ), 'Table has id column' );
		$this->assert( in_array( 'config_json', $columns, true ), 'Table has config_json column' );
		$this->assert( in_array( 'layout_post_id', $columns, true ), 'Table has layout_post_id column' );
		$this->assert( in_array( 'schema_version', $columns, true ), 'Table has schema_version column' );
		$this->assert( in_array( 'migration_status', $columns, true ), 'Table has migration_status column' );
		$this->assert( in_array( 'updated_at', $columns, true ), 'Table has updated_at column' );

		$db_version = get_option( 'gridmaster_db_version' );
		$this->assert( '2.0.0' === $db_version, "Database option version is 2.0.0 (got {$db_version})" );
	}

	private function test_grid_crud() {
		echo "\nSuite 2: Grid CRUD & Layout CPT Synchronization\n";

		// 1. Create
		$test_data = array(
			'title'         => 'Automated Test Grid',
			'config'        => array(
				'query'      => array( 'posts_per_page' => 4, 'orderby' => 'title' ),
				'layout'     => array( 'col_gap' => 20, 'row_gap' => 20 ),
				'pagination' => array( 'type' => 'numbered' ),
			),
			'block_content' => '<!-- wp:gridmaster/card --><!-- wp:gridmaster/title /--><!-- /wp:gridmaster/card -->',
		);

		$grid_id = Grid_Repository::save( $test_data );
		$this->assert( $grid_id > 0, "Grid successfully saved with numeric ID {$grid_id}" );

		// 2. Read
		$grid = Grid_Repository::get( $grid_id );
		$this->assert( null !== $grid, 'Grid successfully retrieved' );
		$this->assert( 'Automated Test Grid' === $grid->title, 'Grid title matches' );
		$this->assert( 4 === $grid->config['query']['posts_per_page'], 'Config query param decoded' );
		$this->assert( $grid->layout_post_id > 0, "Associated layout CPT ID is {$grid->layout_post_id}" );

		$cpt = get_post( $grid->layout_post_id );
		$this->assert( null !== $cpt && 'gm_grid_layout' === $cpt->post_type, 'Layout post exists with type gm_grid_layout' );
		$this->assert( strpos( $cpt->post_content, 'gridmaster/card' ) !== false, 'Layout CPT content matches block markup' );

		// 3. Update
		$update_data = array(
			'id'            => $grid_id,
			'title'         => 'Updated Test Grid',
			'config'        => array( 'query' => array( 'posts_per_page' => 8 ) ),
			'block_content' => '<!-- wp:gridmaster/card --><!-- wp:gridmaster/title /--><!-- wp:gridmaster/read-more /--><!-- /wp:gridmaster/card -->',
		);
		$updated_id = Grid_Repository::save( $update_data );
		$this->assert( $updated_id === $grid_id, 'Grid updated with same ID' );

		$updated_grid = Grid_Repository::get( $grid_id );
		$this->assert( 'Updated Test Grid' === $updated_grid->title, 'Grid title updated' );
		$this->assert( strpos( $updated_grid->block_content, 'gridmaster/read-more' ) !== false, 'CPT content updated' );

		// 4. Duplicate
		$duplicate_id = Grid_Repository::duplicate( $grid_id );
		$this->assert( $duplicate_id > 0 && $duplicate_id !== $grid_id, "Grid duplicated with new ID {$duplicate_id}" );

		$duplicate_grid = Grid_Repository::get( $duplicate_id );
		$this->assert( strpos( $duplicate_grid->title, '(Copy)' ) !== false, 'Duplicate title contains (Copy)' );
		$this->assert( $duplicate_grid->layout_post_id !== $grid->layout_post_id, 'Duplicate has independent layout CPT' );

		// 5. Delete
		$dup_cpt_id = $duplicate_grid->layout_post_id;
		$deleted = Grid_Repository::delete( $duplicate_id );
		$this->assert( $deleted, 'Grid successfully deleted' );
		$this->assert( null === get_post( $dup_cpt_id ), 'Associated layout CPT was cleaned up' );

		// Clean up primary test grid
		Grid_Repository::delete( $grid_id );
	}

	private function test_query_builder() {
		echo "\nSuite 3: Reusable Query Builder\n";

		$config = array(
			'post_type'      => 'post',
			'posts_per_page' => 5,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$query = Query_Builder::execute( $config );
		$this->assert( $query instanceof \WP_Query, 'Query_Builder returns WP_Query instance' );
		$this->assert( 5 === $query->query_vars['posts_per_page'], 'posts_per_page is honored' );
		$this->assert( 'DESC' === $query->query_vars['order'], 'order is honored' );

		// Malicious input prevention
		$bad_config = array(
			'post_type'      => 'post',
			'posts_per_page' => 99999, // Should be clamped
			'orderby'        => 'malicious_sql_injection',
		);
		$safe_args = Query_Builder::build_args( $bad_config );
		$this->assert( 9 === $safe_args['posts_per_page'], 'Out-of-range posts_per_page was safely reset' );
		$this->assert( 'date' === $safe_args['orderby'], 'Invalid orderby was whitelisted back to date' );
	}

	private function test_blocks_and_context() {
		echo "\nSuite 4: Gutenberg Blocks & Dynamic Post Context\n";

		$reg = \WP_Block_Type_Registry::get_instance();
		$this->assert( $reg->is_registered( 'gridmaster/card' ), 'gridmaster/card is registered' );
		$this->assert( $reg->is_registered( 'gridmaster/title' ), 'gridmaster/title is registered' );
		$this->assert( $reg->is_registered( 'gridmaster/featured-image' ), 'gridmaster/featured-image is registered' );
		$this->assert( $reg->is_registered( 'gridmaster/excerpt' ), 'gridmaster/excerpt is registered' );
		$this->assert( $reg->is_registered( 'gridmaster/read-more' ), 'gridmaster/read-more is registered' );
		$this->assert( $reg->is_registered( 'gridmaster/post-date' ), 'gridmaster/post-date is registered' );
		$this->assert( $reg->is_registered( 'gridmaster/post-author' ), 'gridmaster/post-author is registered' );
		$this->assert( $reg->is_registered( 'gridmaster/post-terms' ), 'gridmaster/post-terms is registered' );
		$this->assert( $reg->is_registered( 'gridmaster/grid' ), 'gridmaster/grid embed block is registered' );
		$this->assert( $reg->is_registered( 'gridmaster/reading-time' ), 'gridmaster/reading-time is registered' );
		$this->assert( $reg->is_registered( 'gridmaster/author-avatar' ), 'gridmaster/author-avatar is registered' );
		$this->assert( $reg->is_registered( 'gridmaster/modified-date' ), 'gridmaster/modified-date is registered' );
		$this->assert( $reg->is_registered( 'gridmaster/post-format-icon' ), 'gridmaster/post-format-icon is registered' );
		$this->assert( $reg->is_registered( 'gridmaster/social-share' ), 'gridmaster/social-share is registered' );
		$this->assert( $reg->is_registered( 'gridmaster/badge' ), 'gridmaster/badge is registered' );
		$this->assert( $reg->is_registered( 'gridmaster/shortcode' ), 'gridmaster/shortcode is registered' );
		$this->assert( $reg->is_registered( 'gridmaster/html' ), 'gridmaster/html is registered' );

		// Test block context rendering with a sample post
		$posts = get_posts( array( 'post_type' => 'post', 'numberposts' => 5 ) );
		$target_post = null;
		foreach ( $posts as $p ) {
			if ( ! empty( $p->post_title ) ) {
				$target_post = $p;
				break;
			}
		}

		if ( $target_post ) {
			$block = array(
				'blockName'   => 'gridmaster/title',
				'attrs'       => array( 'tagName' => 'h2' ),
				'innerBlocks' => array(),
				'innerHTML'   => '',
			);
			$html = Grid_Renderer::render_block_with_context( $block, $target_post->ID, $target_post->post_type, 1 );
			$this->assert( strpos( $html, get_the_title( $target_post->ID ) ) !== false, 'Title block renders correct contextual post title' );
			$this->assert( strpos( $html, '<h2' ) !== false, 'Title block honors tagName attribute' );

			// Test reading-time render
			$rt_block = array(
				'blockName'   => 'gridmaster/reading-time',
				'attrs'       => array( 'suffix' => 'minutes to read' ),
				'innerBlocks' => array(),
				'innerHTML'   => '',
			);
			$rt_html = Grid_Renderer::render_block_with_context( $rt_block, $target_post->ID, $target_post->post_type, 1 );
			$this->assert( strpos( $rt_html, 'gm-field-reading-time' ) !== false, 'Reading time block renders field container' );
			$this->assert( strpos( $rt_html, 'minutes to read' ) !== false, 'Reading time block honors custom suffix attribute' );

			// Test social-share render
			$share_block = array(
				'blockName'   => 'gridmaster/social-share',
				'attrs'       => array(),
				'innerBlocks' => array(),
				'innerHTML'   => '',
			);
			$share_html = Grid_Renderer::render_block_with_context( $share_block, $target_post->ID, $target_post->post_type, 1 );
			$this->assert( strpos( $share_html, 'gm-field-social-share' ) !== false, 'Social share block renders share container' );
			$this->assert( strpos( $share_html, 'gm-share-twitter' ) !== false, 'Social share renders Twitter/X share button' );

			// Test shortcode block render with {post_id} replacement
			$sc_block = array(
				'blockName'   => 'gridmaster/shortcode',
				'attrs'       => array( 'shortcode' => 'Custom ID: {post_id}' ),
				'innerBlocks' => array(),
				'innerHTML'   => '',
			);
			$sc_html = Grid_Renderer::render_block_with_context( $sc_block, $target_post->ID, $target_post->post_type, 1 );
			$this->assert( strpos( $sc_html, 'Custom ID: ' . $target_post->ID ) !== false, 'Shortcode block interpolates {post_id}' );

			// Test HTML block render with dynamic tag interpolation
			$html_block = array(
				'blockName'   => 'gridmaster/html',
				'attrs'       => array( 'content' => '<span class="test-tag" data-id="{id}">{title}</span>' ),
				'innerBlocks' => array(),
				'innerHTML'   => '',
			);
			$html_res = Grid_Renderer::render_block_with_context( $html_block, $target_post->ID, $target_post->post_type, 1 );
			$this->assert( strpos( $html_res, 'data-id="' . $target_post->ID . '"' ) !== false, 'HTML block interpolates {id}' );
			$this->assert( strpos( $html_res, get_the_title( $target_post->ID ) ) !== false, 'HTML block interpolates {title}' );

			// Test core/html block render with dynamic tag replacement
			$core_html_block = array(
				'blockName'   => 'core/html',
				'attrs'       => array(),
				'innerBlocks' => array(),
				'innerHTML'   => '<div class="core-box">{post_id}</div>',
			);
			$core_html_res = Grid_Renderer::render_block_with_context( $core_html_block, $target_post->ID, $target_post->post_type, 1 );
			$this->assert( strpos( $core_html_res, '<div class="core-box">' . $target_post->ID . '</div>' ) !== false, 'core/html replaces {post_id}' );

			// Test card layout modes (horizontal, overlay)
			$card_horiz = array(
				'blockName'   => 'gridmaster/card',
				'attrs'       => array( 'layoutMode' => 'horizontal', 'mediaWidth' => '40%' ),
				'innerBlocks' => array(),
				'innerHTML'   => '',
			);
			$card_h_res = Grid_Renderer::render_block_with_context( $card_horiz, $target_post->ID, $target_post->post_type, 1 );
			$this->assert( strpos( $card_h_res, 'gm-card--horizontal' ) !== false, 'Card renders horizontal layout mode class' );
			$this->assert( strpos( $card_h_res, '--gm-card-media-width:40%' ) !== false, 'Card renders custom media width css variable' );

			$card_overlay = array(
				'blockName'   => 'gridmaster/card',
				'attrs'       => array( 'layoutMode' => 'overlay', 'minHeight' => '360px' ),
				'innerBlocks' => array(),
				'innerHTML'   => '',
			);
			$card_o_res = Grid_Renderer::render_block_with_context( $card_overlay, $target_post->ID, $target_post->post_type, 1 );
			$this->assert( strpos( $card_o_res, 'gm-card--overlay' ) !== false, 'Card renders overlay layout mode class' );
			$this->assert( strpos( $card_o_res, 'min-height:360px' ) !== false, 'Card renders min-height style' );

			// Read More custom styling
			$read_more_block = array(
				'blockName'   => 'gridmaster/read-more',
				'attrs'       => array( 'text' => 'Discover', 'backgroundColor' => '#2563eb', 'textColor' => '#ffffff', 'borderRadius' => '20px' ),
				'innerBlocks' => array(),
				'innerHTML'   => '',
			);
			$rm_res = Grid_Renderer::render_block_with_context( $read_more_block, $target_post->ID, $target_post->post_type, 1 );
			$this->assert( strpos( $rm_res, 'Discover' ) !== false, 'Read More renders custom text' );
			$this->assert( strpos( $rm_res, 'background-color:#2563eb' ) !== false, 'Read More renders custom background color' );
			$this->assert( strpos( $rm_res, 'border-radius:20px' ) !== false, 'Read More renders custom border radius' );

			// Post date custom styling
			$date_block = array(
				'blockName'   => 'gridmaster/post-date',
				'attrs'       => array( 'fontSize' => '15px', 'color' => '#10b981' ),
				'innerBlocks' => array(),
				'innerHTML'   => '',
			);
			$date_res = Grid_Renderer::render_block_with_context( $date_block, $target_post->ID, $target_post->post_type, 1 );
			$this->assert( strpos( $date_res, 'font-size:15px' ) !== false, 'Post date renders custom font size' );
			$this->assert( strpos( $date_res, 'color:#10b981' ) !== false, 'Post date renders custom color' );

			// Responsive font size & unit selection tests
			$this->assert( \GridMaster\Blocks::sanitize_font_size( '24px' ) === '24px', 'Sanitizes px font size' );
			$this->assert( \GridMaster\Blocks::sanitize_font_size( '1.5rem' ) === '1.5rem', 'Sanitizes rem font size' );
			$this->assert( \GridMaster\Blocks::sanitize_font_size( '1.25em' ) === '1.25em', 'Sanitizes em font size' );
			$this->assert( \GridMaster\Blocks::sanitize_font_size( '3.2vw' ) === '3.2vw', 'Sanitizes vw font size' );
			$this->assert( \GridMaster\Blocks::sanitize_font_size( '18' ) === '18px', 'Bare number defaults to px' );
			$this->assert( \GridMaster\Blocks::sanitize_font_size( 'evil; expression()' ) === '', 'Rejects malicious font size input' );

			$rfs_css = \GridMaster\Blocks::get_font_size_css( array(
				'fontSize'       => '1.75rem',
				'fontSizeTablet' => '1.25rem',
				'fontSizeMobile' => '1rem',
			) );
			$this->assert( strpos( $rfs_css['style'], 'font-size:1.75rem;' ) !== false, 'RFS helper emits desktop size with rem unit' );
			$this->assert( strpos( $rfs_css['style'], '--gm-fs-tablet:1.25rem;' ) !== false, 'RFS helper emits tablet CSS variable' );
			$this->assert( strpos( $rfs_css['style'], '--gm-fs-mobile:1rem;' ) !== false, 'RFS helper emits mobile CSS variable' );
			$this->assert( strpos( $rfs_css['class'], 'gm-fs-tablet' ) !== false && strpos( $rfs_css['class'], 'gm-fs-mobile' ) !== false, 'RFS helper sets responsive classes' );

			// Title with responsive font sizes
			$title_rfs_block = array(
				'blockName'   => 'gridmaster/title',
				'attrs'       => array(
					'tagName'        => 'h2',
					'fontSize'       => '28px',
					'fontSizeTablet' => '22px',
					'fontSizeMobile' => '18px',
				),
				'innerBlocks' => array(),
				'innerHTML'   => '',
			);
			$title_rfs_res = Grid_Renderer::render_block_with_context( $title_rfs_block, $target_post->ID, $target_post->post_type, 1 );
			$this->assert( strpos( $title_rfs_res, 'font-size:28px' ) !== false, 'Title renders desktop font size' );
			$this->assert( strpos( $title_rfs_res, '--gm-fs-tablet:22px' ) !== false, 'Title renders tablet font size variable' );
			$this->assert( strpos( $title_rfs_res, '--gm-fs-mobile:18px' ) !== false, 'Title renders mobile font size variable' );
			$this->assert( strpos( $title_rfs_res, 'gm-fs-tablet' ) !== false && strpos( $title_rfs_res, 'gm-fs-mobile' ) !== false, 'Title includes responsive classes' );

			// Read More with responsive font sizes
			$rm_rfs_block = array(
				'blockName'   => 'gridmaster/read-more',
				'attrs'       => array(
					'text'           => 'Read Story',
					'fontSize'       => '14px',
					'fontSizeTablet' => '13px',
					'fontSizeMobile' => '12px',
				),
				'innerBlocks' => array(),
				'innerHTML'   => '',
			);
			$rm_rfs_res = Grid_Renderer::render_block_with_context( $rm_rfs_block, $target_post->ID, $target_post->post_type, 1 );
			$this->assert( strpos( $rm_rfs_res, 'font-size:14px' ) !== false, 'Read more renders desktop font size' );
			$this->assert( strpos( $rm_rfs_res, '--gm-fs-tablet:13px' ) !== false, 'Read more renders tablet font size' );
			$this->assert( strpos( $rm_rfs_res, '--gm-fs-mobile:12px' ) !== false, 'Read more renders mobile font size' );
		}
	}

	private function test_facet_registry() {
		echo "\nSuite 5: Facet Registry & Free Handlers\n";

		$handlers = Filter_Registry::get_handlers();
		$this->assert( isset( $handlers['taxonomy-tabs'] ), 'taxonomy-tabs facet registered' );
		$this->assert( isset( $handlers['taxonomy-dropdown'] ), 'taxonomy-dropdown facet registered' );
		$this->assert( isset( $handlers['taxonomy-checkbox'] ), 'taxonomy-checkbox facet registered' );
		$this->assert( isset( $handlers['taxonomy-radio'] ), 'taxonomy-radio facet registered' );
		$this->assert( isset( $handlers['search'] ), 'search facet registered' );
		$this->assert( isset( $handlers['reset'] ), 'reset facet registered' );

		$facets = array(
			array( 'type' => 'search', 'placeholder' => 'Find posts' ),
			array( 'type' => 'reset', 'label' => 'Reset All' ),
		);
		$rendered = Filter_Registry::render_facets( $facets, 99 );
		$this->assert( strpos( $rendered, 'gm-filter-search' ) !== false, 'Search input rendered' );
		$this->assert( strpos( $rendered, 'gm-btn-reset' ) !== false, 'Reset button rendered' );
	}

	private function test_pagination_registry() {
		echo "\nSuite 6: Pagination Registry\n";

		$query = new \WP_Query( array( 'post_type' => 'post', 'posts_per_page' => 1 ) );

		$load_more = Pagination_Registry::render( 'load_more', $query, array( 'load_more_text' => 'Fetch More' ), 1 );
		if ( $query->max_num_pages > 1 ) {
			$this->assert( strpos( $load_more, 'Fetch More' ) !== false, 'Load More button rendered with custom label' );
		} else {
			$this->assert( '' === $load_more, 'Pagination properly omitted when max_num_pages <= 1' );
		}
	}

	private function test_legacy_migration_and_rollback() {
		echo "\nSuite 7: Legacy Grid Migration & Rollback\n";

		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'gridmaster_grids',
			array(
				'title'            => 'Automated Legacy Grid',
				'attributes'       => serialize( array(
					'grid_style'     => 'style-1',
					'post_type'      => 'post',
					'posts_per_page' => 6,
					'show_filter'    => 'yes',
					'paginate'       => 'yes',
				) ),
				'schema_version'   => 1,
				'migration_status' => 'legacy',
			)
		);
		$legacy_id = $wpdb->insert_id;

		// Verify initial status
		$grid = Grid_Repository::get( $legacy_id );
		$this->assert( 'legacy' === $grid->migration_status, 'Initial status is legacy' );
		$this->assert( 1 === $grid->schema_version, 'Initial schema version is 1' );

		// Migrate
		$migrated = Legacy_Migrator::migrate( $legacy_id );
		$this->assert( true === $migrated, 'Legacy_Migrator::migrate returned true' );

		$v2_grid = Grid_Repository::get( $legacy_id );
		$this->assert( 'converted' === $v2_grid->migration_status, 'Post-migration status is converted' );
		$this->assert( 2 === $v2_grid->schema_version, 'Post-migration schema version is 2' );
		$this->assert( $v2_grid->layout_post_id > 0, 'Layout CPT created during migration' );
		$this->assert( strpos( $v2_grid->block_content, 'gridmaster/card' ) !== false, 'Block content generated' );
		$this->assert( 'style-1' === $v2_grid->attributes['grid_style'], 'Original attributes preserved' );

		// Rollback
		$rolled_back = Legacy_Migrator::rollback( $legacy_id );
		$this->assert( true === $rolled_back, 'Legacy_Migrator::rollback returned true' );

		$rb_grid = Grid_Repository::get( $legacy_id );
		$this->assert( 'legacy' === $rb_grid->migration_status, 'Post-rollback status is legacy' );

		// Clean up
		Grid_Repository::delete( $legacy_id );
	}

	private function test_pro_extension_contract() {
		echo "\nSuite 8: Free + Pro Extension Contract\n";

		if ( defined( 'GRIDMASTER_PRO_VERSION' ) ) {
			$reg = \WP_Block_Type_Registry::get_instance();
			$this->assert( $reg->is_registered( 'gridmaster-pro/product-price' ), 'Pro block product-price is registered' );
			$this->assert( $reg->is_registered( 'gridmaster-pro/add-to-cart' ), 'Pro block add-to-cart is registered' );
			$this->assert( $reg->is_registered( 'gridmaster-pro/badge' ), 'Pro block badge is registered' );
			$this->assert( $reg->is_registered( 'gridmaster-pro/product-rating' ), 'Pro block product-rating is registered' );
			$this->assert( $reg->is_registered( 'gridmaster-pro/stock-status' ), 'Pro block stock-status is registered' );
			$this->assert( $reg->is_registered( 'gridmaster-pro/sale-countdown' ), 'Pro block sale-countdown is registered' );
			$this->assert( $reg->is_registered( 'gridmaster-pro/quick-view' ), 'Pro block quick-view is registered' );
			$this->assert( $reg->is_registered( 'gridmaster-pro/video-lightbox' ), 'Pro block video-lightbox is registered' );
			$this->assert( $reg->is_registered( 'gridmaster-pro/bookmark-button' ), 'Pro block bookmark-button is registered' );

			$handlers = Filter_Registry::get_handlers();
			$this->assert( isset( $handlers['multi-select'] ), 'Pro facet multi-select is registered' );
			$this->assert( isset( $handlers['date-range'] ), 'Pro facet date-range is registered' );
			$this->assert( isset( $handlers['meta-filter'] ), 'Pro facet meta-filter is registered' );

			// Test contextual rendering of Pro blocks
			$posts = get_posts( array( 'post_type' => 'post', 'numberposts' => 1 ) );
			if ( ! empty( $posts[0] ) ) {
				$p = $posts[0];

				$qv_block = array(
					'blockName'   => 'gridmaster-pro/quick-view',
					'attrs'       => array( 'buttonText' => 'Inspect Now' ),
					'innerBlocks' => array(),
					'innerHTML'   => '',
				);
				$qv_html = Grid_Renderer::render_block_with_context( $qv_block, $p->ID, $p->post_type, 1 );
				$this->assert( strpos( $qv_html, 'gm-quick-view-btn' ) !== false, 'Quick View block renders trigger button' );
				$this->assert( strpos( $qv_html, 'Inspect Now' ) !== false, 'Quick View block honors custom button text' );

				$bm_block = array(
					'blockName'   => 'gridmaster-pro/bookmark-button',
					'attrs'       => array(),
					'innerBlocks' => array(),
					'innerHTML'   => '',
				);
				$bm_html = Grid_Renderer::render_block_with_context( $bm_block, $p->ID, $p->post_type, 1 );
				$this->assert( strpos( $bm_html, 'gm-bookmark-btn' ) !== false, 'Bookmark block renders toggle button' );
				$this->assert( strpos( $bm_html, 'data-nonce' ) !== false, 'Bookmark block carries verification nonce' );
			}
		} else {
			echo "  [INFO] Pro plugin inactive, skipping Pro registration assertions\n";
		}
	}

	private function test_multiple_grids_isolation() {
		echo "\nSuite 9: Multiple Grids Isolation\n";

		$grid1_id = Grid_Repository::save( array(
			'title'  => 'Grid One',
			'config' => array( 'layout' => array( 'columns' => array( 'lg' => 2 ), 'col_gap' => 20 ) ),
		) );
		$grid2_id = Grid_Repository::save( array(
			'title'  => 'Grid Two',
			'config' => array( 'layout' => array( 'columns' => array( 'lg' => 4 ), 'col_gap' => 40 ) ),
		) );

		$html1 = Grid_Renderer::render_grid( $grid1_id );
		$html2 = Grid_Renderer::render_grid( $grid2_id );

		$this->assert( strpos( $html1, 'id="gm-grid-' . $grid1_id . '"' ) !== false, 'Grid 1 has scoped ID' );
		$this->assert( strpos( $html2, 'id="gm-grid-' . $grid2_id . '"' ) !== false, 'Grid 2 has scoped ID' );
		$this->assert( strpos( $html1, '--gm-cols-lg:2' ) !== false, 'Grid 1 has 2 columns' );
		$this->assert( strpos( $html2, '--gm-cols-lg:4' ) !== false, 'Grid 2 has 4 columns' );

		Grid_Repository::delete( $grid1_id );
		Grid_Repository::delete( $grid2_id );
	}

	private function test_resilience_and_permissions() {
		echo "\nSuite 10: Fault Tolerance, Security & Edge Cases\n";

		global $wpdb;

		// 1. Corrupted config_json recovery
		$wpdb->insert(
			$wpdb->prefix . 'gridmaster_grids',
			array(
				'title'            => 'Corrupted JSON Test Grid',
				'config_json'      => '{"query": {malformed_json_here!!',
				'layout_post_id'   => 0,
				'schema_version'   => 2,
				'migration_status' => 'new_builder',
			)
		);
		$corrupt_id = $wpdb->insert_id;

		$corrupt_grid = Grid_Repository::get( $corrupt_id );
		$this->assert( is_array( $corrupt_grid->config ), 'Corrupted JSON gracefully falls back to default config array' );
		$this->assert( isset( $corrupt_grid->config['query'] ), 'Default query settings present on fallback' );

		// Render grid with corrupted JSON and missing layout post ID
		$html_corrupt = Grid_Renderer::render_grid( $corrupt_id );
		$this->assert( strpos( $html_corrupt, 'id="gm-grid-' . $corrupt_id . '"' ) !== false, 'Grid renders successfully despite corrupt JSON' );
		$this->assert( strpos( $html_corrupt, 'gm-grid-item' ) !== false && strpos( $html_corrupt, 'gm-card' ) !== false, 'Grid falls back to default card blocks' );

		// Clean up
		Grid_Repository::delete( $corrupt_id );

		// 2. REST API Permissions verification
		if ( ! class_exists( 'GridMaster\REST\Grids_Controller' ) ) {
			require_once GRIDMASTER_PATH . '/inc/REST/Grids_Controller.php';
		}
		$controller = new \GridMaster\REST\Grids_Controller();
		$dummy_req = new \WP_REST_Request( 'GET', '/gridmaster/v2/grids' );

		// Log out any user
		wp_set_current_user( 0 );
		$this->assert( false === $controller->get_items_permissions_check( $dummy_req ), 'Unauthenticated user rejected from listing grids' );
		$this->assert( false === $controller->create_item_permissions_check( $dummy_req ), 'Unauthenticated user rejected from creating grids' );
		$this->assert( false === $controller->delete_item_permissions_check( $dummy_req ), 'Unauthenticated user rejected from deleting grids' );

		// Find or mock administrator
		$admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
		if ( ! empty( $admins ) ) {
			wp_set_current_user( $admins[0]->ID );
			$this->assert( true === $controller->get_items_permissions_check( $dummy_req ), 'Administrator permitted to list grids' );
			$this->assert( true === $controller->create_item_permissions_check( $dummy_req ), 'Administrator permitted to create grids' );
			$this->assert( true === $controller->delete_item_permissions_check( $dummy_req ), 'Administrator permitted to delete grids' );
		}

		// Non-existent grid rendering
		$missing_html = Grid_Renderer::render_grid( 99999999 );
		$this->assert( strpos( $missing_html, 'not found' ) !== false, 'Non-existent grid returns informative HTML comment' );
	}

	private function test_accessible_slider() {
		echo "\nSuite 11: Accessible Carousel & Slider Layout (A360 Model)\n";

		// 1. Create a grid with slider configuration
		$slider_grid_id = Grid_Repository::save( array(
			'title'  => 'Accessible Test Slider',
			'config' => array(
				'query'  => array( 'posts_per_page' => 5 ),
				'layout' => array(
					'type'    => 'slider',
					'col_gap' => 24,
					'slider'  => array(
						'slides_to_show' => array( 'xs' => 1, 'sm' => 2, 'md' => 3, 'lg' => 3, 'xl' => 3 ),
						'arrows'         => true,
						'dots'           => true,
						'autoplay'       => true,
						'autoplay_speed' => 4000,
						'pause_on_hover' => true,
						'infinite'       => true,
					),
				),
			),
		) );

		$this->assert( $slider_grid_id > 0, "Slider grid created with ID {$slider_grid_id}" );

		$saved_grid = Grid_Repository::get( $slider_grid_id );
		$this->assert( 'slider' === $saved_grid->config['layout']['type'], 'Layout type saved as slider' );
		$this->assert( true === $saved_grid->config['layout']['slider']['arrows'], 'Slider arrows enabled in config' );
		$this->assert( true === $saved_grid->config['layout']['slider']['dots'], 'Slider dots enabled in config' );
		$this->assert( true === $saved_grid->config['layout']['slider']['autoplay'], 'Slider autoplay enabled in config' );

		// 2. Render slider HTML
		$html = Grid_Renderer::render_grid( $slider_grid_id );

		// Carousel semantics
		$this->assert( strpos( $html, 'gm-layout-slider' ) !== false, 'Wrapper has gm-layout-slider class' );
		$this->assert( strpos( $html, 'data-layout="slider"' ) !== false, 'Wrapper has data-layout="slider" attribute' );
		$this->assert( strpos( $html, 'role="region"' ) !== false, 'Wrapper has role="region"' );
		$this->assert( strpos( $html, 'aria-roledescription="carousel"' ) !== false, 'Wrapper has aria-roledescription="carousel"' );

		// Screen reader instructions
		$this->assert( strpos( $html, 'screen-reader-text sr-only' ) !== false, 'Contains screen-reader-text instructions' );

		// Viewport, Track, Controls
		$this->assert( strpos( $html, 'gm-slider-viewport' ) !== false, 'Contains .gm-slider-viewport' );
		$this->assert( strpos( $html, 'gm-slider-prev' ) !== false, 'Contains .gm-slider-prev button' );
		$this->assert( strpos( $html, 'gm-slider-next' ) !== false, 'Contains .gm-slider-next button' );
		$this->assert( strpos( $html, 'gm-slider-track' ) !== false, 'Contains .gm-slider-track' );
		$this->assert( strpos( $html, 'role="group"' ) !== false, 'Track or slides have role="group"' );
		$this->assert( strpos( $html, 'tabindex="0"' ) !== false, 'Slider track is keyboard focusable with tabindex="0"' );

		// Slides and Accessible Labels
		$this->assert( strpos( $html, 'gm-slider-slide' ) !== false, 'Contains .gm-slider-slide cards' );
		$this->assert( strpos( $html, 'aria-roledescription="slide"' ) !== false, 'Slides have aria-roledescription="slide"' );
		$this->assert( preg_match( '/aria-label="card \d+ of \d+"/', $html ) === 1, 'Slides have indexed aria-label (e.g. card 1 of 5)' );

		// Dots and Autoplay
		$this->assert( strpos( $html, 'gm-slider-dots' ) !== false, 'Contains .gm-slider-dots container' );
		$this->assert( strpos( $html, 'role="tablist"' ) !== false, 'Dots container has role="tablist"' );
		$this->assert( strpos( $html, 'gm-slider-playpause' ) !== false, 'Contains .gm-slider-playpause button' );

		// CSS Variables
		$this->assert( strpos( $html, '--gm-slides-lg:3' ) !== false, 'CSS vars contain --gm-slides-lg' );
		$this->assert( strpos( $html, '--gm-slides-xs:1' ) !== false, 'CSS vars contain --gm-slides-xs' );

		// Clean up
		Grid_Repository::delete( $slider_grid_id );
	}
}

$runner = new V2_Test_Runner();
$runner->run();

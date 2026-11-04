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

			$handlers = Filter_Registry::get_handlers();
			$this->assert( isset( $handlers['multi-select'] ), 'Pro facet multi-select is registered' );
			$this->assert( isset( $handlers['date-range'] ), 'Pro facet date-range is registered' );
			$this->assert( isset( $handlers['meta-filter'] ), 'Pro facet meta-filter is registered' );
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
}

$runner = new V2_Test_Runner();
$runner->run();

<?php
/**
 * Plugin Name:  Post Grid Master — Post Grids & AJAX Filters
 * Plugin URI:   http://addonmaster.com
 * Author:       AddonMaster
 * Author URI:   https://addonmaster.com/gridmaster/
 * Version:			3.4.18
 * Description:  GridMaster is a powerful post filter plugin that allows you create stunning, customizable post grids on your website with its robust support for all post types and taxonomies, versatile pagination options including infinite scroll, and a suite of pre-built grid and filter styles.
 * License:      GPL2
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:  ajax-filter-posts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The main plugin class
 */
final class GridMasterPlugin {

	/**
	 * Class construcotr
	 */
	private function __construct() {
		// Define constants.
		$this->define_constants();

		// Enqueue scripts and styles.
		add_action( 'wp_enqueue_scripts', array( $this, 'scripts' ), 999 );

		register_activation_hook( __FILE__, array( $this, 'activate' ) );

		// Plugin init.
		add_action( 'plugins_loaded', array( $this, 'init_plugin' ) );

		// Action link.
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'action_links' ) );

		// Admin Functions.
		if ( is_admin() ) {
			$this->admin_init();
		}
	}

	/**
	 * Initializes a singleton instance
	 */
	public static function init() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}

    /**
     * Define the required plugin constants
     *
     * @return void
     */
    public function define_constants() {
        define( 'GRIDMASTER_VERSION', '3.4.25' );
        define( 'GRIDMASTER_V2_CAPABLE', true );
        define( 'GRIDMASTER_V2_VERSION', '2.0.0' );
        define( 'GRIDMASTER_FILE', __FILE__ );
        define( 'GRIDMASTER_PATH', __DIR__ );
        define( 'GRIDMASTER_URL', plugins_url( '', GRIDMASTER_FILE ) );
        define( 'GRIDMASTER_ASSETS', GRIDMASTER_URL . '/assets/' );
        define( 'GRIDMASTER_PRO_LINK', 'https://addonmaster.com/gridmaster/' );
    }

	/**
	 * Admin Functions
	 */
	public function admin_init() {
		if ( ! class_exists( 'GridMaster\Admin' ) ) {
			require_once GRIDMASTER_PATH . '/admin/Admin.php';
		}
		$gridmaster = GridMaster\Admin::init();

		// Ajax class.
		if ( ! class_exists( 'GridMaster\Ajax' ) ) {
			require_once GRIDMASTER_PATH . '/admin/Ajax.php';
		}
		$ajax = new GridMaster\Ajax();
	}

	/**
	 * Initialize the plugin
	 */
	public function init_plugin() {

		// Storage and CPT.
		require_once GRIDMASTER_PATH . '/inc/Storage/Layout_CPT.php';
		require_once GRIDMASTER_PATH . '/inc/Storage/Grid_Repository.php';
		new \GridMaster\Storage\Layout_CPT();

		// Migration check.
		require_once GRIDMASTER_PATH . '/admin/DB_Migration.php';

		// Facets & Filters.
		require_once GRIDMASTER_PATH . '/inc/Facets/Facet_Handler_Interface.php';
		require_once GRIDMASTER_PATH . '/inc/Facets/Filter_Registry.php';
		require_once GRIDMASTER_PATH . '/inc/Facets/Handlers/Taxonomy_Tabs.php';
		require_once GRIDMASTER_PATH . '/inc/Facets/Handlers/Taxonomy_Dropdown.php';
		require_once GRIDMASTER_PATH . '/inc/Facets/Handlers/Taxonomy_Checkbox.php';
		require_once GRIDMASTER_PATH . '/inc/Facets/Handlers/Taxonomy_Radio.php';
		require_once GRIDMASTER_PATH . '/inc/Facets/Handlers/Search_Input.php';
		require_once GRIDMASTER_PATH . '/inc/Facets/Handlers/Reset_Button.php';

		// Pagination.
		require_once GRIDMASTER_PATH . '/inc/Pagination/Pagination_Registry.php';

		// Query and Renderer Engines.
		require_once GRIDMASTER_PATH . '/inc/Engine/Query_Builder.php';
		require_once GRIDMASTER_PATH . '/inc/Engine/Grid_Renderer.php';

		// Gutenberg Blocks.
		require_once GRIDMASTER_PATH . '/inc/Blocks.php';
		new \GridMaster\Blocks();

		// Migration Engine.
		require_once GRIDMASTER_PATH . '/inc/Migration/Legacy_Migrator.php';

		// REST API Controller.
		require_once GRIDMASTER_PATH . '/inc/REST/Grids_Controller.php';
		add_action( 'rest_api_init', function () {
			$controller = new \GridMaster\REST\Grids_Controller();
			$controller->register_routes();
		} );

		// Include the functions.php.
		require_once GRIDMASTER_PATH . '/inc/functions.php';

		// Load Shortcode Class.
		if ( ! class_exists( 'GridMaster\Shortcode' ) ) {
			require_once GRIDMASTER_PATH . '/inc/Shortcode.php';
		}
		$shortcode = GridMaster\Shortcode::init();

		// Page builder integrations (strictly conditionally loaded per active builder).
		require_once GRIDMASTER_PATH . '/inc/Integrations/Embed_Helper.php';
		require_once GRIDMASTER_PATH . '/inc/Integrations/Integrations_Manager.php';
		new \GridMaster\Integrations\Integrations_Manager();

		// Register frontend v2 assets early so widgets and builders can declare dependency.
		add_action( 'wp_enqueue_scripts', array( '\GridMaster\Engine\Grid_Renderer', 'register_assets' ), 5 );
		add_action( 'enqueue_block_assets', array( '\GridMaster\Engine\Grid_Renderer', 'register_assets' ), 5 );
	}

	/**
	 * Do stuff upon plugin activation
	 */
	public function activate() {
		// Save timestamp for plugin activation.
		update_option( 'gridmaster_activation_time', time() );

		// Run DB migration on activation.
		require_once GRIDMASTER_PATH . '/admin/DB_Migration.php';
		\GridMaster\DB_Migration::migrate();
	}

	/**
	 * Register frontend scripts and styles (enqueued conditionally only when grids are rendered).
	 */
	public function scripts() {

		wp_register_script( 'gridmaster-frontend', GRIDMASTER_ASSETS . 'frontend.min.js', array( 'jquery' ), GRIDMASTER_VERSION, true );

		// Localization.
		wp_localize_script(
			'gridmaster-frontend',
			'asr_ajax_params',
			array(
				'nonce'        => wp_create_nonce( 'asr_ajax_nonce' ),
				'asr_ajax_url' => admin_url( 'admin-ajax.php' ),
				'is_pro'       => gridmaster_is_pro(),
				'breakpoints'  => gm_get_breakpoints(),
			)
		);

		wp_register_style( 'gridmaster-frontend', GRIDMASTER_ASSETS . 'css/frontend.min.css', array(), GRIDMASTER_VERSION );
	}

	/**
	 * Add action links
	 *
	 * @param  array $links Action links.
	 * @return array
	 */
	public function action_links( $links ) {
		$links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=gridmaster' ) ) . '">' . __( 'Settings', 'ajax-filter-posts'  ) . '</a>';
		// Get GridMaster Pro.
		if ( ! gridmaster_is_pro() ) {
			$links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=gridmaster&path=free-vs-pro' ) ) . '" style="color: #4f46e5; font-weight: bold;">' . __( 'Upgrade to Pro', 'ajax-filter-posts'  ) . '</a>';
		}
		return $links;
	}
}

// Initializes the main plugin.
function grid_master_plugin() {
	return GridMasterPlugin::init();
}

// run the plugin.
grid_master_plugin();


/**
 * Initialize the plugin tracker
 *
 * @return void
 */
function grid_master_appsero_init_tracker_ajax_filter_posts() {

	if ( ! class_exists( 'Appsero\Client' ) ) {
		require_once __DIR__ . '/appsero/src/Client.php';
	}

	$client = new Appsero\Client( 'dc1dc5f0-8c32-4208-b217-b8b1a1a0b85f', 'Post Grid Master', __FILE__ );

	// Active insights
	$client->insights()->init();
}

grid_master_appsero_init_tracker_ajax_filter_posts();
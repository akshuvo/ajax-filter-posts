<?php
/**
 * Plugin Name:  Post Grid Master — Post Grids & AJAX Filters
 * Plugin URI:   http://addonmaster.com
 * Author:       AddonMaster
 * Author URI:   https://addonmaster.com/gridmaster/
 * Version:			3.4.17
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
        define( 'GRIDMASTER_VERSION', '3.4.17' );
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


		// Include the functions.php.
		require_once GRIDMASTER_PATH . '/inc/functions.php';

		// Load Shortcode Class.
		if ( ! class_exists( 'GridMaster\Shortcode' ) ) {
			require_once GRIDMASTER_PATH . '/inc/Shortcode.php';
		}
		$shortcode = GridMaster\Shortcode::init();
	}

	/**
	 * Do stuff upon plugin activation
	 */
	public function activate() {
		// Save timestamp for plugin activation.
		update_option( 'gridmaster_activation_time', time() );
	}

	/**
	 * Enqueue scripts and styles
	 */
	public function scripts() {

		wp_enqueue_script( 'gridmaster-frontend', GRIDMASTER_ASSETS . 'frontend.min.js', array( 'jquery' ), GRIDMASTER_VERSION, true );

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

		wp_enqueue_style( 'gridmaster-frontend', GRIDMASTER_ASSETS . 'css/frontend.min.css', array(), GRIDMASTER_VERSION );
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
			$links[] = '<a href="' . gridmaster_website_url( 'gridmaster/free-vs-pro/' ) . '" target="_blank" style="color: #39b54a; font-weight: bold;">' . __( 'Get GridMaster Pro', 'ajax-filter-posts'  ) . '</a>';
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
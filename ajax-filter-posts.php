<?php
/**
 * Plugin Name:  GridMaster - Post Grid with Ajax Filter
 * Plugin URI:   http://addonmaster.com
 * Author:       AddonMaster 
 * Author URI:   http://addonmaster.com/plugins/post-grid-with-ajax-filter
 * Version: 	 3.3.0
 * Description:  Post Grid with Ajax Filter helps you filter your posts by category terms with Ajax. Infinite scroll function included.
 * License:      GPL2
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:  gridmaster
 * Domain Path:  /lang
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
        $this->define_constants();

        // Enqueue scripts and styles.
        // add_action( 'wp_enqueue_scripts', array( $this, 'wp_instance_scripts' ), 99999 );

        // register_activation_hook( __FILE__, [ $this, 'activate' ] );

        
        add_action( 'plugins_loaded', [ $this, 'init_plugin' ] );



        // Admin Functions
		// add_action( 'admin_init', [ $this, 'admin_init' ], 9 );
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

        define( 'GRIDMASTER_VERSION', '3.4.0' );
        define( 'GRIDMASTER_FILE', __FILE__ );
        define( 'GRIDMASTER_PATH', __DIR__ );
        define( 'GRIDMASTER_URL', plugins_url( '', GRIDMASTER_FILE ) );
        define( 'GRIDMASTER_ASSETS', GRIDMASTER_URL . '/assets' );

    }

	// Admin Functions
	public function admin_init() {
		if ( !class_exists( 'GridMaster\Admin' ) ) {
			require_once GRIDMASTER_PATH . '/admin/Admin.php';
		}
		GridMaster\Admin::init();
	}

    /**
     * Initialize the plugin
     *
     * @return void
     */
    public function init_plugin() {

		// Load Old Version
		require_once GRIDMASTER_PATH . '/older-version/ajax-filter-posts.php';

        // Include the functions.php
        // require_once GRIDMASTER_PATH . '/inc/functions.php';

	}


    /**
     * Do stuff upon plugin activation
     *
     * @return void
     */
    public function activate() {
  
    }

    // Enqueue scripts and styles.
    public function wp_instance_scripts() {
        // Register Bootstrap from CDN
        // wp_register_style( 'bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css', array(), GRIDMASTER_VERSION );

        wp_enqueue_script( 'wp-instance-script', GRIDMASTER_ASSETS . '/frontend.js', array( 'jquery' ), GRIDMASTER_VERSION, true );
        wp_localize_script( 'wp-instance-script', 'wp_instance_script', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'wp-instance-script-nonce' ),
        ) );

        wp_enqueue_style( 'wp-instance-style', GRIDMASTER_ASSETS . '/frontend.css', array(), GRIDMASTER_VERSION );
    }


}

// Initializes the main plugin
function GridMasterPlugin() {
    return GridMasterPlugin::init();
}

// run the plugin
GridMasterPlugin();

/**
 * Initialize the plugin tracker
 *
 * @return void
 */
function appsero_init_tracker_ajax_filter_posts() {

    if ( ! class_exists( 'Appsero\Client' ) ) {
      require_once __DIR__ . '/appsero/src/Client.php';
    }

	$client = new Appsero\Client( 'dc1dc5f0-8c32-4208-b217-b8b1a1a0b85f', 'Post Grid Ajax', __FILE__ );

    // Active insights
    $client->insights()->init();

}

appsero_init_tracker_ajax_filter_posts();

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
        // Define constants
        $this->define_constants();

        // Enqueue scripts and styles.
        add_action( 'wp_enqueue_scripts', array( $this, 'scripts' ), 999 );

        // register_activation_hook( __FILE__, [ $this, 'activate' ] );

        // Plugin init
        add_action( 'plugins_loaded', [ $this, 'init_plugin' ] );

        // Admin Functions
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
        if ( ! defined( 'GRIDMASTER_PRO_DIR' ) ) {
            define( 'GRIDMASTER_PRO_DIR', plugin_dir_path( __DIR__ ) . 'gridmaster-pro' );
        }
        if ( ! defined( 'GRIDMASTER_PRO_ASSETS_URL' ) ) {
            define( 'GRIDMASTER_PRO_ASSETS_URL', plugins_url('gridmaster-pro') . '/assets' );
        }

    }

	// Admin Functions
	public function admin_init() {
		if ( !class_exists( 'GridMaster\Admin' ) ) {
			require_once GRIDMASTER_PATH . '/admin/Admin.php';
		}
		$gridmaster = GridMaster\Admin::init();
	}

    /**
     * Initialize the plugin
     *
     * @return void
     */
    public function init_plugin() {
        
        // Include the functions.php
        require_once GRIDMASTER_PATH . '/inc/functions.php';
        
		// Load Shortcode Class
		if ( !class_exists( 'GridMaster\Shortcode' ) ) {
			require_once GRIDMASTER_PATH . '/inc/Shortcode.php';
		}
		$shortcode = GridMaster\Shortcode::init();

	}


    /**
     * Do stuff upon plugin activation
     *
     * @return void
     */
    public function activate() {
  
    }

    /**
     * Enqueue scripts and styles
     *
     * @return void
     */
    public function scripts() {

        wp_enqueue_script( 'gridmaster-frontend', GRIDMASTER_ASSETS . '/frontend.js', array( 'jquery' ), GRIDMASTER_VERSION, true );

		// Localization
		wp_localize_script( 'gridmaster-frontend', 'asr_ajax_params', array(
			'asr_ajax_nonce' => wp_create_nonce( 'asr_ajax_nonce' ),
			'asr_ajax_url' => admin_url( 'admin-ajax.php' ),
		) );


        wp_enqueue_style( 'gridmaster-frontend', GRIDMASTER_ASSETS . '/frontend.css', array(), GRIDMASTER_VERSION );

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

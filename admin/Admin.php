<?php
namespace GridMaster;

class Admin {

    /**
     * Class constructor
     */
    function __construct() {
        add_action( 'admin_menu', [ $this, 'admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'scripts' ] );
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
     * Admin menu
     *
     * @return void
     */
    public function admin_menu() {
        add_menu_page( __( 'GridMaster', 'gridmaster' ), __( 'GridMaster', 'gridmaster' ), 'manage_options', 'gridmaster', [ $this, 'plugin_page' ], 'dashicons-grid-view', 110 );
    }

    /**
     * Register the plugin settings
     *
     * @return void
     */
    public function register_settings() {
  
        register_setting( 'gridmaster', 'gridmaster_settings' );
    }

    /**
     * Plugin settings page
     *
     * @return void
     */
    public function plugin_page() {
        require_once GRIDMASTER_PATH . '/admin/views/admin.php';
    }

    // Enqueue scripts and styles.
    public function scripts() {

        wp_enqueue_script( 'gridmaster-admin-script', GRIDMASTER_URL . '/admin/assets/admin.js', array( 'jquery' ), GRIDMASTER_VERSION, true );
        wp_localize_script( 'gridmaster-admin-script', 'gridmaster_params', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'wp-instance-script-nonce' ),
            'home_url' => home_url(),
        ) );

        wp_enqueue_style( 'bootstrap-grid', GRIDMASTER_URL . '/admin/assets/bootstrap-grid.css', array(), GRIDMASTER_VERSION );
        wp_enqueue_style( 'bootstrap-utilities', GRIDMASTER_URL . '/admin/assets/bootstrap-utilities.css', array(), GRIDMASTER_VERSION );
        // wp_enqueue_style( 'bootstrap-css', GRIDMASTER_URL . '/admin/assets/bootstrap.min.css', array(), GRIDMASTER_VERSION );
        wp_enqueue_style( 'gridmaster-admin-style', GRIDMASTER_URL . '/admin/assets/admin.css', array(), GRIDMASTER_VERSION );

        if( !defined( 'GRIDMASTER_PRO_VERSION' ) ) {
            wp_enqueue_style( 'gridmaster-admin-pro-block-style', GRIDMASTER_URL . '/admin/assets/block-pro-admin.css', array(), GRIDMASTER_VERSION );
        }

    }


}
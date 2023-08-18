<?php
namespace GridMaster;

class Admin {

    /**
     * Class constructor
     */
    function __construct() {
        add_action( 'admin_menu', [ $this, 'admin_menu' ] );
        add_action( 'wp_ajax_gridmaster_ajax', [ $this, 'register_ajax' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'scripts' ] );

        // Admin Footer Text
        add_filter( 'admin_footer_text', [ $this, 'admin_footer_text' ], 9999 );

        // Change version text
        add_filter( 'update_footer', [ $this, 'update_footer' ], 9999 ); 
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
        add_menu_page( __( 'GridMaster', 'gridmaster' ), __( 'GridMaster', 'gridmaster' ), 'manage_options', 'gridmaster', [ $this, 'plugin_page' ], 'dashicons-forms', 110 );
    }

    /**
     * Admin Footer Text
     *
     * @return void
     */
    public function admin_footer_text( $text ) {
        if( !current_user_can( 'manage_options' ) ) {
            return $text;
        }

        $current_screen = get_current_screen();
        if( $current_screen->id != 'toplevel_page_gridmaster' ) {
            return $text;
        }

        $text = sprintf( __( 'If you like <strong>GridMaster</strong> please support us by giving it a %s rating. A huge thanks in advance!', 'gridmaster' ), '<a href="https://wordpress.org/support/plugin/ajax-filter-posts/reviews/?filter=5#new-post" target="_blank">&#9733;&#9733;&#9733;&#9733;&#9733;</a>' );

        return $text;
    }

    /**
     * Change version text
     *
     * @return void
     */
    public function update_footer( $text ) {
        if( !current_user_can( 'manage_options' ) ) {
            return $text;
        }

        $current_screen = get_current_screen();
        if( $current_screen->id != 'toplevel_page_gridmaster' ) {
            return $text;
        }

        $path = isset( $_GET['path'] ) ? sanitize_text_field( $_GET['path'] ) : '';
        if( $path != 'build-grid' ) {
            return '';
        }

        // Live chat offer for pro users
        $text = sprintf( 
            __( 'Stuck somewhere? Need help? %s or %s', 'gridmaster' ), 
            '<a href="'.gridmaster_website_url('live-chat/').'" target="_blank">' . __( 'Live chat(Pro only)', 'gridmaster' ) . '</a>',
            '<a href="'.gridmaster_website_url('submit-a-ticket/').'" target="_blank">' . __( 'Submit a ticket', 'gridmaster' ) . '</a>'
        );

        return $text;
    }

    /**
     * Register ajax
     *
     * @return void
     */
    public function register_ajax() {
        $nonce = isset( $_POST['gm_nonce'] ) ? sanitize_text_field( $_POST['gm_nonce'] ) : '';
        if( !wp_verify_nonce( $nonce, 'gm-ajax-nonce' ) ) {
            wp_send_json_error( __( 'Invalid nonce', 'gridmaster' ) );
        }

        $action = isset( $_POST['gm-action'] ) ? sanitize_title( $_POST['gm-action'] ) : '';
        if( !$action ) {
            wp_send_json_error( __( 'Invalid action', 'gridmaster' ) );
        }

        // Include the admin functions
        require_once( GRIDMASTER_PATH . '/admin/admin-functions.php' );

        $function = 'gridmaster_ajax_' . str_replace( '-', '_', $action );
        if( !function_exists( $function ) ) {
            wp_send_json_error( __( "Function {$function} doesn't exist", 'gridmaster' ) );
        }

        // Call the ajax function
        $response = $function( $_POST );

        
        wp_die();
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
            'nonce' => wp_create_nonce( 'gm-ajax-nonce' ),
            'home_url' => home_url(),
            'breakpoints' => gm_get_breakpoints(),
            'has_pro' => gridmaster_is_pro()
        ) );

        wp_enqueue_style( 'bootstrap-grid', GRIDMASTER_URL . '/admin/assets/bootstrap-grid.css', array(), GRIDMASTER_VERSION );
        // wp_enqueue_style( 'bootstrap-utilities', GRIDMASTER_URL . '/admin/assets/bootstrap-utilities.css', array(), GRIDMASTER_VERSION );
        // wp_enqueue_style( 'bootstrap-css', GRIDMASTER_URL . '/admin/assets/bootstrap.min.css', array(), GRIDMASTER_VERSION );
        wp_enqueue_style( 'gridmaster-admin-style', GRIDMASTER_URL . '/admin/assets/admin.css', array(), GRIDMASTER_VERSION );

        if( !defined( 'GRIDMASTER_PRO_VERSION' ) ) {
            wp_enqueue_style( 'gridmaster-admin-pro-block-style', GRIDMASTER_URL . '/admin/assets/block-pro-admin.css', array(), GRIDMASTER_VERSION );
        }

    }


}
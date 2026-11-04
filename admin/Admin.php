<?php
namespace GridMaster;

/**
 * Admin handler class.
 */
class Admin {

	/**
	 * Class constructor
	 */
	private function __construct() {
		// Menu.
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );

		// Admin scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'scripts' ) );

		// Admin Footer Text.
		add_filter( 'admin_footer_text', array( $this, 'admin_footer_text' ), 9999 );

		// Change version text.
		add_filter( 'update_footer', array( $this, 'update_footer' ), 9999 );
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
	 * Register admin menu
	 */
	public function admin_menu() {
		$parent_slug = 'gridmaster';
		$capability  = 'manage_options';

		add_menu_page( __( 'GridMaster', 'ajax-filter-posts'  ), __( 'GridMaster', 'ajax-filter-posts'  ), $capability, $parent_slug, array( $this, 'plugin_page' ), 'dashicons-forms', 110 );

		add_submenu_page( $parent_slug, __( 'All Grids', 'ajax-filter-posts' ), __( 'All Grids', 'ajax-filter-posts' ), $capability, $parent_slug, array( $this, 'plugin_page' ) );
		add_submenu_page( $parent_slug, __( 'Add New Grid', 'ajax-filter-posts' ), __( 'Add New Grid', 'ajax-filter-posts' ), $capability, 'gridmaster-builder', array( $this, 'builder_page' ) );
		add_submenu_page( $parent_slug, __( 'Legacy Builder', 'ajax-filter-posts' ), __( 'Legacy Builder', 'ajax-filter-posts' ), $capability, admin_url( 'admin.php?page=gridmaster&path=build-grid' ) );
		add_submenu_page( $parent_slug, __( 'Settings', 'ajax-filter-posts' ), __( 'Settings', 'ajax-filter-posts' ), $capability, admin_url( 'admin.php?page=gridmaster&path=settings' ) );
	}

	/**
	 * Gutenberg builder page
	 */
	public function builder_page() {
		require_once GRIDMASTER_PATH . '/admin/views/builder.php';
	}

	/**
	 * Admin Footer Text
	 *
	 * @param  string $text Footer text.
	 */
	public function admin_footer_text( $text ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $text;
		}

		$current_screen = get_current_screen();
		if ( 'toplevel_page_gridmaster' !== $current_screen->id ) {
			return $text;
		}
		/* translators: %s: review url. */
		$text = sprintf( __( 'If you like <strong>GridMaster</strong> please support us by giving it a %s rating. A huge thanks in advance!', 'ajax-filter-posts'  ), '<a href="https://wordpress.org/support/plugin/ajax-filter-posts/reviews/?filter=5#new-post" target="_blank">&#9733;&#9733;&#9733;&#9733;&#9733;</a>' );

		return $text;
	}

	/**
	 * Change version text
	 *
	 * @param  string $text Footer text.
	 */
	public function update_footer( $text ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $text;
		}

		$current_screen = get_current_screen();
		if ( 'toplevel_page_gridmaster' !== $current_screen->id ) {
			return $text;
		}

		$path = isset( $_GET['path'] ) ? sanitize_text_field( wp_unslash( $_GET['path'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'build-grid' !== $path ) {
			return '';
		}

		// Live chat offer for pro users.
		$text = sprintf(
			/* translators: 1: live chat. 2: ticket url. */
			__( 'Stuck somewhere? Need help? %1$s or %2$s', 'ajax-filter-posts'  ),
			'<a href="' . gridmaster_website_url( 'live-chat/' ) . '" target="_blank">' . __( 'Live chat(Pro only)', 'ajax-filter-posts'  ) . '</a>',
			'<a href="' . gridmaster_website_url( 'submit-a-ticket/' ) . '" target="_blank">' . __( 'Submit a ticket', 'ajax-filter-posts'  ) . '</a>'
		);

		return $text;
	}

	/**
	 * Plugin settings page
	 */
	public function plugin_page() {
		require_once GRIDMASTER_PATH . '/admin/views/admin.php';
	}

	/**
	 * Enqueue scripts and styles.
	 */
	public function scripts() {

		wp_enqueue_script( 'gridmaster-admin-script', GRIDMASTER_URL . '/admin/assets/admin.min.js', array( 'jquery' ), GRIDMASTER_VERSION, true );
		wp_localize_script(
			'gridmaster-admin-script',
			'gridmaster_params',
			array(
				'ajax_url'          => admin_url( 'admin-ajax.php' ),
				'nonce'             => wp_create_nonce( 'gm-ajax-nonce' ),
				'home_url'          => home_url(),
				'breakpoints'       => gm_get_breakpoints(),
				'has_pro'           => gridmaster_is_pro(),
				'demo_link'         => esc_url( 'https://plugins.addonmaster.com/gridmaster/' ),
				'filter_demo_links' => array(
					'pro-filter-1' => 'gridmaster-pro-filter-style-1',
					'pro-filter-2' => 'gridmaster-pro-filter-style-2',
				),
				'grid_demo_links'   => array(
					'pro-style-1'  => 'pro-grid-style-1',
					'pro-style-2'  => 'pro-grid-style-2',
					'pro-style-3'  => 'pro-grid-style-3',
					'pro-style-4'  => 'pro-grid-style-4',
					'pro-style-5'  => 'pro-grid-style-5',
					'pro-style-6'  => 'pro-grid-style-6',
					'pro-style-7'  => 'pro-grid-style-7',
					'pro-style-8'  => 'pro-grid-style-8',
					'pro-style-9'  => 'pro-grid-style-9',
					'pro-style-10' => 'pro-grid-style-10',
				),
			)
		);

		wp_enqueue_style( 'bootstrap-grid', GRIDMASTER_URL . '/admin/assets/bootstrap-grid.min.css', array(), GRIDMASTER_VERSION );
		wp_enqueue_style( 'gridmaster-admin-style', GRIDMASTER_URL . '/admin/assets/admin.css', array(), GRIDMASTER_VERSION );

		if ( ! defined( 'GRIDMASTER_PRO_VERSION' ) ) {
			wp_enqueue_style( 'gridmaster-admin-pro-block-style', GRIDMASTER_URL . '/admin/assets/block-pro-admin.min.css', array(), GRIDMASTER_VERSION );
		}

		$screen = get_current_screen();
		if ( $screen && false !== strpos( $screen->id, 'gridmaster-builder' ) ) {
			wp_enqueue_script( 'wp-blocks' );
			wp_enqueue_script( 'wp-element' );
			wp_enqueue_script( 'wp-block-editor' );
			wp_enqueue_script( 'wp-components' );
			wp_enqueue_script( 'wp-data' );
			wp_enqueue_script( 'wp-i18n' );
			wp_enqueue_script( 'wp-editor' );
			wp_enqueue_script( 'wp-block-library' );
			wp_enqueue_style( 'wp-block-editor' );
			wp_enqueue_style( 'wp-components' );
			wp_enqueue_style( 'wp-block-library' );
			wp_enqueue_style( 'wp-block-library-theme' );

			wp_enqueue_script( 'gridmaster-blocks-editor', GRIDMASTER_ASSETS . 'blocks/editor.js', array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-data', 'wp-i18n', 'wp-block-library' ), GRIDMASTER_VERSION, true );
			wp_enqueue_style( 'gridmaster-blocks-editor', GRIDMASTER_ASSETS . 'blocks/editor.css', array( 'wp-components', 'wp-block-library' ), GRIDMASTER_VERSION );

			$builder_ver = defined( 'WP_DEBUG' ) && WP_DEBUG ? time() : GRIDMASTER_VERSION;
			wp_enqueue_script( 'gridmaster-builder-app', GRIDMASTER_URL . '/admin/assets/builder.js', array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-data', 'wp-i18n', 'wp-block-library', 'wp-compose' ), $builder_ver, true );
			wp_enqueue_style( 'gridmaster-builder-css', GRIDMASTER_URL . '/admin/assets/builder.css', array( 'wp-components' ), $builder_ver );
			wp_enqueue_style( 'gridmaster-frontend-v2', GRIDMASTER_ASSETS . 'css/frontend-v2.css', array(), GRIDMASTER_VERSION );
		}
	}
}

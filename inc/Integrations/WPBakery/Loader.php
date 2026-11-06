<?php
namespace GridMaster\Integrations\WPBakery;

use GridMaster\Integrations\Embed_Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPBakery Page Builder integration loader.
 */
class Loader {

	/**
	 * Init.
	 */
	public static function init() {
		add_action( 'vc_before_init', array( __CLASS__, 'register_element' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_editor_assets' ) );
	}

	/**
	 * Maps the gridmaster shortcode into WPBakery Page Builder.
	 */
	public static function register_element() {
		if ( ! function_exists( 'vc_map' ) ) {
			return;
		}

		$choices = Embed_Helper::get_grid_choices();
		// WPBakery dropdown expects label => value
		$dropdown_values = array_flip( $choices );

		vc_map(
			array(
				'name'        => __( 'Post Grid Master', 'ajax-filter-posts' ),
				'base'        => 'gridmaster',
				'category'    => __( 'Post Grid Master', 'ajax-filter-posts' ),
				'description' => __( 'Display a saved Post Grid Master layout.', 'ajax-filter-posts' ),
				'icon'        => 'dashicons-grid-view',
				'params'      => array(
					array(
						'type'        => 'dropdown',
						'heading'     => __( 'Select Grid', 'ajax-filter-posts' ),
						'param_name'  => 'id',
						'value'       => $dropdown_values,
						'admin_label' => true,
						'description' => __( 'Choose a saved GridMaster layout to display.', 'ajax-filter-posts' ),
					),
				),
			)
		);
	}

	/**
	 * Enqueue assets when inside the WPBakery frontend editor.
	 */
	public static function enqueue_editor_assets() {
		if ( function_exists( 'vc_is_page_editable' ) && vc_is_page_editable() ) {
			\GridMaster\Engine\Grid_Renderer::enqueue_assets();

			wp_enqueue_style(
				'gridmaster-builder-preview',
				GRIDMASTER_ASSETS . 'integrations/builder-preview.css',
				array( 'gridmaster-frontend-v2' ),
				GRIDMASTER_VERSION
			);

			wp_enqueue_script(
				'gridmaster-builder-preview',
				GRIDMASTER_ASSETS . 'integrations/builder-preview.js',
				array( 'jquery', 'gridmaster-frontend-v2' ),
				GRIDMASTER_VERSION,
				true
			);
		}
	}
}

Loader::init();

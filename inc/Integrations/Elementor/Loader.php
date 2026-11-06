<?php
namespace GridMaster\Integrations\Elementor;

use GridMaster\Integrations\Embed_Helper;
use GridMaster\Storage\Grid_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Elementor integration loader.
 */
class Loader {

	/**
	 * Constructor.
	 */
	public static function init() {
		add_action( 'elementor/elements/categories_registered', array( __CLASS__, 'register_category' ) );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widgets' ) );
		add_action( 'elementor/editor/after_enqueue_scripts', array( __CLASS__, 'enqueue_editor_scripts' ) );
		add_action( 'elementor/preview/enqueue_scripts', array( __CLASS__, 'enqueue_preview_scripts' ) );
	}

	/**
	 * Registers the GridMaster widget category in Elementor.
	 *
	 * @param \Elementor\Elements_Manager $elements_manager
	 */
	public static function register_category( $elements_manager ) {
		$elements_manager->add_category(
			'gridmaster',
			array(
				'title' => __( 'Post Grid Master', 'ajax-filter-posts' ),
				'icon'  => 'eicon-posts-grid',
			)
		);
	}

	/**
	 * Registers the GridMaster widget.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager
	 */
	public static function register_widgets( $widgets_manager ) {
		require_once __DIR__ . '/Grid_Widget.php';
		$widgets_manager->register( new Grid_Widget() );
	}

	/**
	 * Enqueues scripts for Elementor editor panel.
	 */
	public static function enqueue_editor_scripts() {
		wp_enqueue_script(
			'gridmaster-elementor-editor',
			GRIDMASTER_ASSETS . 'integrations/elementor-editor.js',
			array( 'jquery' ),
			GRIDMASTER_VERSION,
			true
		);

		// Build a map of grid edit URLs for quick navigation from the panel
		$choices   = Embed_Helper::get_grid_choices( false );
		$edit_urls = array();
		if ( current_user_can( 'manage_options' ) ) {
			foreach ( array_keys( $choices ) as $grid_id ) {
				$edit_urls[ $grid_id ] = Embed_Helper::get_edit_url( $grid_id );
			}
		}

		wp_localize_script(
			'gridmaster-elementor-editor',
			'gridmasterElementorData',
			array(
				'editUrls'   => $edit_urls,
				'builderUrl' => Embed_Helper::get_create_url(),
				'i18n'       => array(
					'selectGridFirst' => __( 'Please select a grid first.', 'ajax-filter-posts' ),
				),
			)
		);
	}

	/**
	 * Enqueues preview scripts & styles in the Elementor canvas.
	 */
	public static function enqueue_preview_scripts() {
		\GridMaster\Engine\Grid_Renderer::register_assets();

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

Loader::init();

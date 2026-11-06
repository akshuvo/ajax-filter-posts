<?php
namespace GridMaster\Integrations\Beaver;

use FLBuilderModule;
use FLBuilderModel;
use GridMaster\Engine\Grid_Renderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Beaver Builder Module for Post Grid Master.
 */
class Grid_Module extends FLBuilderModule {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'name'            => __( 'Post Grid Master', 'ajax-filter-posts' ),
				'description'     => __( 'Display a saved Post Grid Master layout.', 'ajax-filter-posts' ),
				'category'        => __( 'Post Grid Master', 'ajax-filter-posts' ),
				'dir'             => __DIR__ . '/beaver-module/',
				'url'             => GRIDMASTER_URL . '/inc/Integrations/Beaver/beaver-module/',
				'partial_refresh' => true,
				'icon'            => 'layout.svg',
			)
		);
	}

	/**
	 * Enqueue frontend and builder assets.
	 */
	public function enqueue_scripts() {
		Grid_Renderer::enqueue_assets();

		if ( class_exists( 'FLBuilderModel' ) && FLBuilderModel::is_builder_active() ) {
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

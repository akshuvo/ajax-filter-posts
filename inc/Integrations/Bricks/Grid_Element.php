<?php
namespace GridMaster\Integrations\Bricks;

use GridMaster\Integrations\Embed_Helper;
use GridMaster\Engine\Grid_Renderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bricks custom element for Post Grid Master.
 */
class Grid_Element extends \Bricks\Element {

	/**
	 * Element category.
	 */
	public $category = 'gridmaster';

	/**
	 * Element unique name.
	 */
	public $name = 'gridmaster-grid';

	/**
	 * Element icon.
	 */
	public $icon = 'ti-layout-grid3';

	/**
	 * Scripts to re-run on builder update / re-render.
	 */
	public $scripts = array( 'gridmasterBricksInit' );

	/**
	 * Element display label.
	 */
	public function get_label() {
		return esc_html__( 'Post Grid Master', 'ajax-filter-posts' );
	}

	/**
	 * Enqueue assets.
	 */
	public function enqueue_scripts() {
		Grid_Renderer::enqueue_assets();

		if ( function_exists( 'bricks_is_builder' ) && ( bricks_is_builder() || bricks_is_builder_main() || bricks_is_builder_call() ) ) {
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

	/**
	 * Declare controls.
	 */
	public function set_controls() {
		$this->controls['gridId'] = array(
			'tab'         => 'content',
			'label'       => esc_html__( 'Select Grid', 'ajax-filter-posts' ),
			'type'        => 'select',
			'options'     => Embed_Helper::get_grid_choices(),
			'default'     => 0,
			'searchable'  => true,
			'description' => esc_html__( 'Choose a saved GridMaster layout to display.', 'ajax-filter-posts' ),
		);

		if ( current_user_can( 'manage_options' ) ) {
			$create_url = Embed_Helper::get_create_url();
			$manage_url = Embed_Helper::get_manage_url();

			$links_html = '<div style="margin-top:10px;font-size:12px;color:#64748b;line-height:1.6;">';
			if ( $create_url ) {
				$links_html .= sprintf(
					'<a href="%s" target="_blank" rel="noopener noreferrer" style="font-weight:600;color:#4f46e5;text-decoration:none;">+ %s</a><br>',
					esc_url( $create_url ),
					esc_html__( 'Create New Grid in GridMaster', 'ajax-filter-posts' )
				);
			}
			if ( $manage_url ) {
				$links_html .= sprintf(
					'<a href="%s" target="_blank" rel="noopener noreferrer" style="color:#64748b;text-decoration:underline;">%s</a>',
					esc_url( $manage_url ),
					esc_html__( 'Manage All Grids', 'ajax-filter-posts' )
				);
			}
			$links_html .= '</div>';

			$this->controls['gridHelperLinks'] = array(
				'tab'     => 'content',
				'type'    => 'info',
				'content' => $links_html,
			);
		}
	}

	/**
	 * Render element markup.
	 */
	public function render() {
		$grid_id = ! empty( $this->settings['gridId'] ) ? absint( $this->settings['gridId'] ) : 0;

		echo "<div {$this->render_attributes( '_root' )}>"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo Embed_Helper::render( $grid_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';
	}
}

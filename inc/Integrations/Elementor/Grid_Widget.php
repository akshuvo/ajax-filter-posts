<?php
namespace GridMaster\Integrations\Elementor;

use GridMaster\Integrations\Embed_Helper;
use Elementor\Widget_Base;
use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GridMaster Elementor Widget.
 */
class Grid_Widget extends Widget_Base {

	/**
	 * Widget slug.
	 */
	public function get_name() {
		return 'gridmaster-grid';
	}

	/**
	 * Widget display title.
	 */
	public function get_title() {
		return __( 'Post Grid Master', 'ajax-filter-posts' );
	}

	/**
	 * Widget Dashicon / Elementor icon.
	 */
	public function get_icon() {
		return 'eicon-posts-grid';
	}

	/**
	 * Widget category.
	 */
	public function get_categories() {
		return array( 'gridmaster' );
	}

	/**
	 * Search keywords.
	 */
	public function get_keywords() {
		return array( 'grid', 'post', 'filter', 'gridmaster', 'post grid master', 'blog', 'cards' );
	}

	/**
	 * Script dependencies.
	 */
	public function get_script_depends() {
		$scripts = array( 'gridmaster-frontend-v2' );
		if ( \Elementor\Plugin::$instance->editor->is_edit_mode() || \Elementor\Plugin::$instance->preview->is_preview_mode() ) {
			$scripts[] = 'gridmaster-builder-preview';
		}
		return $scripts;
	}

	/**
	 * Style dependencies.
	 */
	public function get_style_depends() {
		$styles = array( 'gridmaster-frontend-v2' );
		if ( \Elementor\Plugin::$instance->editor->is_edit_mode() || \Elementor\Plugin::$instance->preview->is_preview_mode() ) {
			$styles[] = 'gridmaster-builder-preview';
		}
		return $styles;
	}

	/**
	 * Register widget controls.
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'section_grid_settings',
			array(
				'label' => __( 'Grid Settings', 'ajax-filter-posts' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$choices = Embed_Helper::get_grid_choices();

		$this->add_control(
			'grid_id',
			array(
				'label'       => __( 'Select Grid', 'ajax-filter-posts' ),
				'type'        => Controls_Manager::SELECT2,
				'label_block' => true,
				'default'     => '0',
				'options'     => $choices,
				'description' => __( 'Choose a saved GridMaster layout to display.', 'ajax-filter-posts' ),
			)
		);

		if ( current_user_can( 'manage_options' ) ) {
			$this->add_control(
				'edit_grid_button',
				array(
					'type'      => Controls_Manager::BUTTON,
					'text'      => __( 'Edit Selected Grid ↗', 'ajax-filter-posts' ),
					'event'     => 'gridmaster:edit_grid',
					'button_type' => 'default',
					'condition' => array(
						'grid_id!' => array( '0', 0, '' ),
					),
				)
			);

			$create_url = Embed_Helper::get_create_url();
			$manage_url = Embed_Helper::get_manage_url();

			$links_html = '<div style="margin-top:14px;padding-top:10px;border-top:1px solid #e2e8f0;font-size:12px;color:#64748b;line-height:1.6;">';
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

			$this->add_control(
				'grid_manager_links',
				array(
					'type' => Controls_Manager::RAW_HTML,
					'raw'  => $links_html,
				)
			);
		}

		$this->end_controls_section();
	}

	/**
	 * Render widget output on frontend / canvas.
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();
		$grid_id  = ! empty( $settings['grid_id'] ) ? absint( $settings['grid_id'] ) : 0;

		echo Embed_Helper::render( $grid_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

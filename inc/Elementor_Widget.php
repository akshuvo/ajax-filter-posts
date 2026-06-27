<?php
namespace GridMaster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Elementor_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'gridmaster_grid';
	}

	public function get_title() {
		return __( 'GridMaster Grid', 'ajax-filter-posts' );
	}

	public function get_icon() {
		return 'eicon-posts-grid';
	}

	public function get_categories() {
		return array( 'general' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'gridmaster_content',
			array(
				'label' => __( 'GridMaster', 'ajax-filter-posts' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'grid_ref',
			array(
				'label'   => __( 'Grid', 'ajax-filter-posts' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => $this->get_grid_options(),
				'default' => '',
			)
		);

		$this->add_control(
			'filter_style',
			array(
				'label'   => __( 'Filter', 'ajax-filter-posts' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => $this->get_filter_options(),
				'default' => 'default',
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();

		echo Renderer::render_builder_selection(
			array(
				'gridRef'      => isset( $settings['grid_ref'] ) ? sanitize_text_field( $settings['grid_ref'] ) : '',
				'filterStyle'  => isset( $settings['filter_style'] ) ? sanitize_text_field( $settings['filter_style'] ) : 'default',
				'grid_id'      => 'gm-elementor-' . $this->get_id(),
			)
		); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	private function get_grid_options() {
		$options = array( '' => __( 'Select a grid', 'ajax-filter-posts' ) );

		$templates = get_posts(
			array(
				'post_type'      => array( 'gm_grid_template', 'gm_grid_style' ),
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);

		foreach ( $templates as $template ) {
			$options[ 'template:' . $template->ID ] = sprintf( __( 'Template: %s', 'ajax-filter-posts' ), $template->post_title );
		}

		if ( ! class_exists( 'GridMaster\Grids' ) ) {
			require_once GRIDMASTER_PATH . '/admin/Grids.php';
		}

		$legacy_grids = Grids::init()->list();
		if ( ! empty( $legacy_grids ) ) {
			foreach ( $legacy_grids as $grid ) {
				$options[ 'legacy:' . $grid->id ] = sprintf( __( 'Saved Grid: %s', 'ajax-filter-posts' ), $grid->title );
			}
		}

		return $options;
	}

	private function get_filter_options() {
		return apply_filters(
			'gridmaster_filter_styles',
			array(
				'default' => __( 'Style 1 (Default)', 'ajax-filter-posts' ),
				'style-2' => __( 'Style 2 (New)', 'ajax-filter-posts' ),
				'style-3' => __( 'Style 3 (New)', 'ajax-filter-posts' ),
				'style-4' => __( 'Style 4 (New)', 'ajax-filter-posts' ),
			)
		);
	}
}

<?php
namespace GridMaster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Blocks {

	public function __construct() {
		add_action( 'init', array( $this, 'register_template_post_type' ), 8 );
		add_action( 'init', array( $this, 'register_blocks' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'editor_assets' ) );
		add_filter( 'allowed_block_types_all', array( $this, 'allowed_block_types' ), 10, 2 );
	}

	public function register_template_post_type() {
		$labels = array(
			'name'          => __( 'GridMaster Grids', 'ajax-filter-posts' ),
			'singular_name' => __( 'GridMaster Grid', 'ajax-filter-posts' ),
			'menu_name'     => __( 'Grid Templates', 'ajax-filter-posts' ),
			'add_new_item'  => __( 'Add New Grid Template', 'ajax-filter-posts' ),
			'edit_item'     => __( 'Edit Grid Template', 'ajax-filter-posts' ),
		);

		register_post_type(
			'gm_grid_template',
			array(
				'labels'              => $labels,
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => true,
				'show_in_menu'        => 'gridmaster',
				'show_in_rest'        => true,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'hierarchical'        => false,
				'exclude_from_search' => true,
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => array( 'title', 'editor' ),
				'template'            => array(
					array( 'gridmaster/featured-image' ),
					array( 'gridmaster/title' ),
					array( 'gridmaster/excerpt' ),
					array( 'gridmaster/read-more' ),
				),
			)
		);
	}

	public function register_blocks() {
		$this->register_editor_script();

		register_block_type(
			'gridmaster/grid',
			array(
				'api_version'     => 3,
				'editor_script'   => 'gridmaster-block-editor',
				'render_callback' => array( $this, 'render_grid_block' ),
				'attributes'      => array(
					'templateId'        => array( 'type' => 'number', 'default' => 0 ),
					'gridRef'           => array( 'type' => 'string', 'default' => '' ),
					'filterStyle'       => array( 'type' => 'string', 'default' => 'default' ),
					'postType'          => array( 'type' => 'string', 'default' => 'post' ),
					'postsPerPage'      => array( 'type' => 'number', 'default' => 9 ),
					'orderby'           => array( 'type' => 'string', 'default' => 'date' ),
					'order'             => array( 'type' => 'string', 'default' => 'DESC' ),
					'taxonomy'          => array( 'type' => 'string', 'default' => 'category' ),
					'terms'             => array( 'type' => 'string', 'default' => '' ),
					'showFilter'        => array( 'type' => 'boolean', 'default' => true ),
					'paginationType'    => array( 'type' => 'string', 'default' => '' ),
					'infiniteScroll'    => array( 'type' => 'boolean', 'default' => false ),
					'responsiveColumns' => array( 'type' => 'object', 'default' => array( 'lg' => 3 ) ),
					'rowGap'            => array( 'type' => 'number', 'default' => 30 ),
					'columnGap'         => array( 'type' => 'number', 'default' => 30 ),
				),
			)
		);

		register_block_type(
			'gridmaster/post-template',
			array(
				'api_version'     => 3,
				'editor_script' => 'gridmaster-block-editor',
			)
		);

		register_block_type(
			'gridmaster/filter',
			array(
				'api_version'     => 3,
				'editor_script' => 'gridmaster-block-editor',
			)
		);

		register_block_type( 'gridmaster/featured-image', $this->field_block_args( 'featured-image' ) );
		register_block_type( 'gridmaster/title', $this->field_block_args( 'title' ) );
		register_block_type( 'gridmaster/excerpt', $this->field_block_args( 'excerpt' ) );
		register_block_type( 'gridmaster/terms', $this->field_block_args( 'terms' ) );
		register_block_type( 'gridmaster/date', $this->field_block_args( 'date' ) );
		register_block_type( 'gridmaster/author', $this->field_block_args( 'author' ) );
		register_block_type( 'gridmaster/comments', $this->field_block_args( 'comments' ) );
		register_block_type( 'gridmaster/read-more', $this->field_block_args( 'read-more' ) );
	}

	public function editor_assets() {
		$this->register_editor_script();
		wp_enqueue_script( 'gridmaster-block-editor' );
		wp_enqueue_style( 'gridmaster-block-editor', GRIDMASTER_ASSETS . 'blocks/editor.css', array(), GRIDMASTER_VERSION );
	}

	public function render_grid_block( $attributes ) {
		return Renderer::render_builder_selection( $attributes );
	}

	public function allowed_block_types( $allowed_block_types, $block_editor_context ) {
		$post_type = isset( $block_editor_context->post->post_type ) ? $block_editor_context->post->post_type : '';
		if ( 'gm_grid_template' === $post_type ) {
			return $allowed_block_types;
		}

		$template_only_blocks = array(
			'gridmaster/post-template',
			'gridmaster/filter',
			'gridmaster/featured-image',
			'gridmaster/title',
			'gridmaster/excerpt',
			'gridmaster/terms',
			'gridmaster/date',
			'gridmaster/author',
			'gridmaster/comments',
			'gridmaster/read-more',
		);

		if ( false === $allowed_block_types ) {
			return false;
		}

		if ( true === $allowed_block_types || ! is_array( $allowed_block_types ) ) {
			$registered = \WP_Block_Type_Registry::get_instance()->get_all_registered();
			return array_values( array_diff( array_keys( $registered ), $template_only_blocks ) );
		}

		return array_values( array_diff( $allowed_block_types, $template_only_blocks ) );
	}

	public function render_field_block( $attributes, $content, $block ) {
		$name = isset( $block->name ) ? str_replace( 'gridmaster/', '', $block->name ) : '';
		return Template_Renderer::field( $name, $attributes );
	}

	private function field_block_args( $name ) {
		return array(
			'api_version'     => 3,
			'editor_script'   => 'gridmaster-block-editor',
			'render_callback' => array( $this, 'render_field_block' ),
			'attributes'      => array(
				'text'     => array( 'type' => 'string', 'default' => '' ),
				'tagName'  => array( 'type' => 'string', 'default' => 'h3' ),
				'isLink'   => array( 'type' => 'boolean', 'default' => true ),
				'taxonomy' => array( 'type' => 'string', 'default' => '' ),
				'format'   => array( 'type' => 'string', 'default' => '' ),
				'size'     => array( 'type' => 'string', 'default' => 'full' ),
			),
		);
	}

	private function register_editor_script() {
		if ( wp_script_is( 'gridmaster-block-editor', 'registered' ) ) {
			return;
		}

		if ( ! function_exists( 'gm_get_post_types' ) || ! function_exists( 'gm_get_taxonomies' ) ) {
			require_once GRIDMASTER_PATH . '/admin/admin-functions.php';
		}

		wp_register_script(
			'gridmaster-block-editor',
			GRIDMASTER_ASSETS . 'blocks/editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render', 'wp-i18n' ),
			GRIDMASTER_VERSION,
			true
		);

		wp_localize_script(
			'gridmaster-block-editor',
			'gridmasterBlockData',
			array(
				'templates'  => $this->get_templates(),
				'grids'      => $this->get_grid_options(),
				'filters'    => $this->get_filter_options(),
				'postTypes'  => \gm_get_post_types(),
				'taxonomies' => \gm_get_taxonomies( true ),
			)
		);
	}

	private function get_templates() {
		$templates = get_posts(
			array(
				'post_type'      => array( 'gm_grid_template', 'gm_grid_style' ),
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);

		$options = array(
			array(
				'label' => __( 'Select a template', 'ajax-filter-posts' ),
				'value' => 0,
			),
		);

		foreach ( $templates as $template ) {
			$options[] = array(
				'label' => $template->post_title,
				'value' => $template->ID,
			);
		}

		return $options;
	}

	private function get_grid_options() {
		if ( ! class_exists( 'GridMaster\Grids' ) ) {
			require_once GRIDMASTER_PATH . '/admin/Grids.php';
		}

		$options = array(
			array(
				'label' => __( 'Select a grid', 'ajax-filter-posts' ),
				'value' => '',
			),
		);

		foreach ( $this->get_templates() as $template ) {
			if ( empty( $template['value'] ) ) {
				continue;
			}
			$options[] = array(
				'label' => sprintf( __( 'Template: %s', 'ajax-filter-posts' ), $template['label'] ),
				'value' => 'template:' . absint( $template['value'] ),
			);
		}

		$legacy_grids = Grids::init()->list();
		if ( ! empty( $legacy_grids ) ) {
			foreach ( $legacy_grids as $grid ) {
				$options[] = array(
					'label' => sprintf( __( 'Saved Grid: %s', 'ajax-filter-posts' ), $grid->title ),
					'value' => 'legacy:' . absint( $grid->id ),
				);
			}
		}

		return $options;
	}

	private function get_filter_options() {
		$styles = apply_filters(
			'gridmaster_filter_styles',
			array(
				'default' => __( 'Style 1 (Default)', 'ajax-filter-posts' ),
				'style-2' => __( 'Style 2 (New)', 'ajax-filter-posts' ),
				'style-3' => __( 'Style 3 (New)', 'ajax-filter-posts' ),
				'style-4' => __( 'Style 4 (New)', 'ajax-filter-posts' ),
			)
		);

		$options = array();
		foreach ( $styles as $value => $label ) {
			$options[] = array(
				'label' => $label,
				'value' => trim( $value ),
			);
		}

		return $options;
	}
}

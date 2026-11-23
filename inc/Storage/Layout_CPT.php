<?php
namespace GridMaster\Storage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages the private custom post type 'gm_grid_layout' for Gutenberg card templates.
 */
class Layout_CPT {

	/**
	 * Post type slug.
	 */
	const POST_TYPE = 'gm_grid_layout';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ), 5 );
	}

	/**
	 * Registers the private custom post type.
	 */
	public function register_post_type() {
		$labels = array(
			'name'               => _x( 'Grid Layouts', 'post type general name', 'ajax-filter-posts' ),
			'singular_name'      => _x( 'Grid Layout', 'post type singular name', 'ajax-filter-posts' ),
			'menu_name'          => _x( 'Grid Layouts', 'admin menu', 'ajax-filter-posts' ),
			'name_admin_bar'     => _x( 'Grid Layout', 'add new on admin bar', 'ajax-filter-posts' ),
			'add_new'            => _x( 'Add New', 'layout', 'ajax-filter-posts' ),
			'add_new_item'       => __( 'Add New Grid Layout', 'ajax-filter-posts' ),
			'new_item'           => __( 'New Grid Layout', 'ajax-filter-posts' ),
			'edit_item'          => __( 'Edit Grid Layout', 'ajax-filter-posts' ),
			'view_item'          => __( 'View Grid Layout', 'ajax-filter-posts' ),
			'all_items'          => __( 'All Grid Layouts', 'ajax-filter-posts' ),
			'search_items'       => __( 'Search Grid Layouts', 'ajax-filter-posts' ),
			'not_found'          => __( 'No grid layouts found.', 'ajax-filter-posts' ),
			'not_found_in_trash' => __( 'No grid layouts found in Trash.', 'ajax-filter-posts' ),
		);

		$args = array(
			'labels'              => $labels,
			'public'              => false,
			'publicly_queryable'  => false,
			'show_ui'             => false,
			'show_in_menu'        => false,
			'show_in_nav_menus'   => false,
			'show_in_admin_bar'   => false,
			'show_in_rest'        => true,
			'rest_base'           => 'gm-grid-layouts',
			'has_archive'         => false,
			'hierarchical'        => false,
			'exclude_from_search' => true,
			'rewrite'             => false,
			'query_var'           => false,
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'supports'            => array( 'title', 'editor', 'revisions' ),
		);

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Creates a new layout post.
	 *
	 * @param string $title Layout / grid title.
	 * @param string $block_content Gutenberg block markup.
	 * @param string $post_status 'publish' or 'draft'.
	 * @return int|\WP_Error
	 */
	public static function create_layout( $title = '', $block_content = '', $post_status = 'publish' ) {
		$post_data = array(
			'post_type'    => self::POST_TYPE,
			'post_title'   => sanitize_text_field( $title ? $title : __( 'Grid Layout', 'ajax-filter-posts' ) ),
			'post_content' => $block_content,
			'post_status'  => in_array( $post_status, array( 'publish', 'draft' ), true ) ? $post_status : 'publish',
		);

		return wp_insert_post( $post_data, true );
	}

	/**
	 * Updates an existing layout post.
	 *
	 * @param int         $post_id Post ID.
	 * @param string      $block_content Gutenberg block markup.
	 * @param string|null $title Optional new title.
	 * @param string|null $post_status Optional new status.
	 * @return int|\WP_Error
	 */
	public static function update_layout( $post_id, $block_content, $title = null, $post_status = null ) {
		$post = get_post( $post_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return new \WP_Error( 'invalid_layout_id', __( 'Invalid layout post ID.', 'ajax-filter-posts' ) );
		}

		$post_data = array(
			'ID'           => $post_id,
			'post_content' => $block_content,
		);

		if ( null !== $title ) {
			$post_data['post_title'] = sanitize_text_field( $title );
		}

		if ( null !== $post_status && in_array( $post_status, array( 'publish', 'draft' ), true ) ) {
			$post_data['post_status'] = $post_status;
		}

		return wp_update_post( $post_data, true );
	}

	/**
	 * Deletes a layout post.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function delete_layout( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return false;
		}

		return (bool) wp_delete_post( $post_id, true );
	}

	/**
	 * Retrieves block content for a layout.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function get_content( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return '';
		}

		return $post->post_content;
	}
}

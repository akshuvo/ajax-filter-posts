<?php
namespace GridMaster;

/**
 * Undocumented class
 */
class Grids {

	/**
	 * Default attributes
	 *
	 * @var defaults
	 */
	public static $defaults = array(
		'grid_style'            => 'default',
		'post_type'             => 'post',
		'post_type_selection'   => '',
		'posts_per_page'        => '10',
		'orderby'               => 'date',
		'order'                 => 'DESC',
		'content_from'          => 'excerpt',
		'excerpt_type'          => 'words',
		'excerpt_length'        => '15',
		'show_read_more'        => 'yes',
		'read_more_text'        => '',
		'grid_image_size'       => 'full',
		'grid_image_width'      => '350',
		'grid_image_height'     => '200',
		'link_thumbnail'        => 'no',
		'link_thumbnail_to'     => 'post',
		'title_tag'             => '',
		'heading_font_size'     => array(
			'xs' => '16px',
			'sm' => '18px',
			'md' => '20px',
			'lg' => '22px',
			'xl' => '24px',
		),
		'grid_col_gap'          => array(
			'xs' => '30',
			'sm' => '30',
			'md' => '30',
			'lg' => '30',
			'xl' => '30',
		),
		'grid_row_gap'          => array(
			'xs' => '30',
			'sm' => '30',
			'md' => '30',
			'lg' => '30',
			'xl' => '30',
		),
		'grid_item_per_row'     => array(
			'xs' => '1',
			'sm' => '2',
			'md' => '3',
			'lg' => '3',
			'xl' => '3',
		),
		'show_filter'           => 'yes',
		'filter_style'          => 'default',
		'btn_all'               => 'yes',
		'taxonomy'              => 'category',
		'hide_empty'            => '0',
		'initial_term'          => '',
		'multiple_select'       => 'no',
		'filter_heading'        => '',
		'toggle_filter_items'   => 'no',
		'enable_slider'         => '',
		'slider_slidesToShow'   => array(
			'xs' => '1',
			'sm' => '2',
			'md' => '3',
			'lg' => '3',
			'xl' => '3',
		),
		'slider_slidesToScroll' => array(
			'xs' => '1',
			'sm' => '1',
			'md' => '1',
			'lg' => '1',
			'xl' => '1',
		),
		'slider_arrows'         => '1',
		'slider_dots'           => '',
		'slider_autoplay'       => '',
		'slider_autoplaySpeed'  => '3000',
		'slider_pauseOnHover'   => '',
		'slider_infinite'       => '1',
		'slider_centerMode'     => '',
		'pagination_type'       => '',
		'id'                    => '',
	);

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
	 * List of grids
	 *
	 * @param  array $args List parameters.
	 */
	public function list( $args = array() ) {
		global $wpdb;
		$grids = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}gridmaster_grids ORDER BY id DESC LIMIT 100" );

		// Unserialize if found.
		if ( $grids ) {
			foreach ( $grids as $key => $grid ) {
				$grids[ $key ]->attributes = maybe_unserialize( $grid->attributes );
			}
		}

		return $grids;
	}

	/**
	 * Add/update grid
	 *
	 * @param array $data Form data.
	 * @return int
	 */
	public function save( $data ) {
		global $wpdb;

		$id         = isset( $data['id'] ) ? intval( $data['id'] ) : 0;
		$title      = isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : '';
		$attributes = isset( $data['attributes'] ) ? serialize( $data['attributes'] ) : null;

		if ( $id ) {
			$wpdb->update(
				"{$wpdb->prefix}gridmaster_grids",
				array(
					'title'      => $title,
					'attributes' => $attributes,
				),
				array( 'id' => $id )
			);
		} else {
			$wpdb->insert(
				"{$wpdb->prefix}gridmaster_grids",
				array(
					'title'      => $title,
					'attributes' => $attributes,
				)
			);
			$id = $wpdb->insert_id;
		}

		return $id;
	}

	/**
	 * Get grid by ID
	 *
	 * @param int     $id Grid id.
	 * @param boolean $defaults Show $defaults if true.
	 * @return object
	 */
	public static function get( $id, $defaults = false ) {
		// Defaults data.
		if ( ! $id && $defaults ) {
			$grid             = new \stdClass();
			/* translators: %s: random words */
			$grid->title      = sprintf( __( 'GridMaster #%s', 'ajax-filter-posts'  ), wp_generate_password( 8, false ) );
			$grid->attributes = self::$defaults;
			return $grid;
		}

		global $wpdb;
		$grid = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}gridmaster_grids WHERE id = %d", $id ) );

		// Unserialize if found.
		if ( $grid ) {
			$grid->attributes = maybe_unserialize( $grid->attributes );
		}
		return $grid;
	}

	/**
	 * Delete grid
	 *
	 * @param int $id Grid id.
	 * @return void
	 */
	public function delete( $id ) {
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'gridmaster_grids', array( 'id' => $id ) );
	}
}

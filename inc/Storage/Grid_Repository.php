<?php
namespace GridMaster\Storage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Repository for managing GridMaster grid records in {$wpdb->prefix}gridmaster_grids
 * and their associated Gutenberg card layout posts in 'gm_grid_layout'.
 */
class Grid_Repository {

	/**
	 * Table name without prefix.
	 */
	const TABLE_NAME = 'gridmaster_grids';

	/**
	 * Returns the full table name with WordPress prefix.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	/**
	 * Default configuration schema for GridMaster 2.0.
	 *
	 * @return array
	 */
	public static function default_config() {
		return array(
			'query'      => array(
				'post_type'      => 'post',
				'posts_per_page' => 9,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'tax_query'      => array(),
				'author'         => '',
				'search'         => '',
				'hide_empty'     => true,
			),
			'facets'     => array(),
			'pagination' => array(
				'type'            => 'numbered', // 'numbered', 'load_more', 'infinite', 'prev-next', 'none'
				'load_more_text'  => __( 'Load More', 'ajax-filter-posts' ),
				'loading_text'    => __( 'Loading...', 'ajax-filter-posts' ),
				'end_text'        => __( 'No more posts', 'ajax-filter-posts' ),
			),
			'layout'     => array(
				'type'       => 'grid', // 'grid' or 'slider'
				'columns'    => array(
					'xs' => 1,
					'sm' => 2,
					'md' => 3,
					'lg' => 3,
					'xl' => 3,
				),
				'col_gap'    => 30,
				'row_gap'    => 30,
				'slider'     => array(
					'slides_to_show' => array(
						'xs' => 1,
						'sm' => 2,
						'md' => 3,
						'lg' => 3,
						'xl' => 3,
					),
					'arrows'         => true,
					'dots'           => true,
					'autoplay'       => false,
					'autoplay_speed' => 3000,
					'pause_on_hover' => true,
					'infinite'       => true,
				),
				'card_style' => array(
					'background'    => '#ffffff',
					'border_radius' => '8px',
					'padding'       => '20px',
					'box_shadow'    => 'none',
				),
			),
		);
	}

	/**
	 * Default starter card block content.
	 *
	 * @return string
	 */
	public static function default_block_content() {
		return '<!-- wp:gridmaster/card {"borderRadius":"8px","padding":"20px","backgroundColor":"#ffffff"} -->' .
			'<!-- wp:gridmaster/featured-image {"size":"medium_large","aspectRatio":"16/9","borderRadius":"6px"} /-->' .
			'<!-- wp:gridmaster/title {"tagName":"h3","fontSize":"20px","isLink":true} /-->' .
			'<!-- wp:gridmaster/post-date {"format":"F j, Y"} /-->' .
			'<!-- wp:gridmaster/excerpt {"length":20,"type":"words"} /-->' .
			'<!-- wp:gridmaster/read-more {"text":"Read More"} /-->' .
			'<!-- /wp:gridmaster/card -->';
	}

	/**
	 * Retrieves a grid by its stable numeric ID.
	 *
	 * @param int $id Grid ID.
	 * @return object|null Standardized grid object or null if not found.
	 */
	public static function get( $id ) {
		global $wpdb;

		$id = absint( $id );
		if ( ! $id ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id )
		);

		if ( ! $row ) {
			return null;
		}

		$grid = new \stdClass();
		$grid->id               = (int) $row->id;
		$grid->title            = $row->title;
		$grid->dated            = $row->dated;
		$grid->updated_at       = isset( $row->updated_at ) ? $row->updated_at : $row->dated;
		$grid->schema_version   = isset( $row->schema_version ) ? (int) $row->schema_version : 1;
		$grid->migration_status = isset( $row->migration_status ) ? $row->migration_status : 'legacy';
		$grid->layout_post_id   = isset( $row->layout_post_id ) ? (int) $row->layout_post_id : 0;

		// Decode modern config_json if present.
		if ( ! empty( $row->config_json ) ) {
			$decoded_config = json_decode( $row->config_json, true );
			$grid->config   = is_array( $decoded_config ) ? $decoded_config : self::default_config();
		} else {
			$grid->config   = self::default_config();
		}

		// Legacy attributes deserialization.
		$grid->attributes = ! empty( $row->attributes ) ? maybe_unserialize( $row->attributes ) : array();

		// Load Gutenberg block tree from the layout CPT if present.
		if ( $grid->layout_post_id > 0 ) {
			$grid->block_content = Layout_CPT::get_content( $grid->layout_post_id );
		} else {
			$grid->block_content = '';
		}

		return $grid;
	}

	/**
	 * Saves or updates a grid definition atomically.
	 *
	 * @param array $data Grid data array.
	 * @return int|\WP_Error Grid ID on success, WP_Error on failure.
	 */
	public static function save( $data ) {
		global $wpdb;

		$id             = isset( $data['id'] ) ? absint( $data['id'] ) : 0;
		$title          = isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : '';
		if ( empty( $title ) ) {
			$title = sprintf( __( 'Grid #%s', 'ajax-filter-posts' ), wp_generate_password( 6, false ) );
		}

		$config         = isset( $data['config'] ) ? $data['config'] : null;
		if ( is_string( $config ) ) {
			$config = json_decode( $config, true );
		}
		if ( ! is_array( $config ) ) {
			$config = self::default_config();
		}
		$config_json    = wp_json_encode( $config );

		$block_content  = isset( $data['block_content'] ) ? wp_kses_post( $data['block_content'] ) : '';
		$status         = isset( $data['migration_status'] ) ? sanitize_key( $data['migration_status'] ) : 'new_builder';
		$schema_version = isset( $data['schema_version'] ) ? absint( $data['schema_version'] ) : 2;
		$attributes     = isset( $data['attributes'] ) ? serialize( $data['attributes'] ) : '';

		// If block_content is empty and this is a new grid, use default starter layout.
		if ( ! $id && empty( $block_content ) ) {
			$block_content = self::default_block_content();
		}

		if ( $id > 0 ) {
			// Updating existing grid.
			$existing = self::get( $id );
			if ( ! $existing ) {
				return new \WP_Error( 'grid_not_found', __( 'Grid not found.', 'ajax-filter-posts' ) );
			}

			$layout_post_id = $existing->layout_post_id;
			$prev_content   = $existing->block_content;
			$prev_title     = $existing->title;
			$created_cpt_id = 0;

			if ( $layout_post_id > 0 ) {
				$cpt_update = Layout_CPT::update_layout( $layout_post_id, $block_content, $title );
				if ( is_wp_error( $cpt_update ) ) {
					return $cpt_update;
				}
			} elseif ( ! empty( $block_content ) ) {
				$new_cpt_id = Layout_CPT::create_layout( $title, $block_content );
				if ( is_wp_error( $new_cpt_id ) ) {
					return $new_cpt_id;
				}
				$layout_post_id = $new_cpt_id;
				$created_cpt_id = $new_cpt_id;
			}

			$updated = $wpdb->update(
				self::table(),
				array(
					'title'            => $title,
					'config_json'      => $config_json,
					'layout_post_id'   => $layout_post_id,
					'schema_version'   => $schema_version,
					'migration_status' => $status,
					'updated_at'       => current_time( 'mysql' ),
				),
				array( 'id' => $id ),
				array( '%s', '%s', '%d', '%d', '%s', '%s' ),
				array( '%d' )
			);

			if ( false === $updated ) {
				// Roll back CPT changes if database update fails
				if ( $created_cpt_id > 0 ) {
					Layout_CPT::delete_layout( $created_cpt_id );
				} elseif ( $layout_post_id > 0 ) {
					Layout_CPT::update_layout( $layout_post_id, $prev_content, $prev_title );
				}
				return new \WP_Error( 'db_update_failed', __( 'Could not update grid in database.', 'ajax-filter-posts' ) );
			}

			return $id;
		}

		// Creating new grid.
		$new_cpt_id = Layout_CPT::create_layout( $title, $block_content );
		if ( is_wp_error( $new_cpt_id ) ) {
			return $new_cpt_id;
		}

		$inserted = $wpdb->insert(
			self::table(),
			array(
				'title'            => $title,
				'attributes'       => $attributes,
				'config_json'      => $config_json,
				'layout_post_id'   => $new_cpt_id,
				'schema_version'   => $schema_version,
				'migration_status' => $status,
				'dated'            => current_time( 'mysql' ),
				'updated_at'       => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			// Roll back CPT creation if table insert fails.
			Layout_CPT::delete_layout( $new_cpt_id );
			return new \WP_Error( 'db_insert_failed', __( 'Could not insert grid into database.', 'ajax-filter-posts' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Deletes a grid and its associated layout CPT.
	 *
	 * @param int $id Grid ID.
	 * @return bool True on success, false on failure.
	 */
	public static function delete( $id ) {
		global $wpdb;

		$grid = self::get( $id );
		if ( ! $grid ) {
			return false;
		}

		// Clean up layout CPT if exists.
		if ( $grid->layout_post_id > 0 ) {
			Layout_CPT::delete_layout( $grid->layout_post_id );
		}

		$deleted = $wpdb->delete(
			self::table(),
			array( 'id' => $id ),
			array( '%d' )
		);

		return (bool) $deleted;
	}

	/**
	 * Duplicates an existing grid with a new stable ID and a fresh layout CPT.
	 *
	 * @param int $id Grid ID to duplicate.
	 * @return int|\WP_Error New grid ID or WP_Error.
	 */
	public static function duplicate( $id ) {
		$source = self::get( $id );
		if ( ! $source ) {
			return new \WP_Error( 'grid_not_found', __( 'Source grid not found.', 'ajax-filter-posts' ) );
		}

		$duplicate_data = array(
			'id'               => 0,
			'title'            => sprintf( __( '%s (Copy)', 'ajax-filter-posts' ), $source->title ),
			'config'           => $source->config,
			'block_content'    => $source->block_content,
			'migration_status' => $source->migration_status,
			'schema_version'   => $source->schema_version,
			'attributes'       => $source->attributes,
		);

		return self::save( $duplicate_data );
	}

	/**
	 * Updates the migration status of a grid.
	 *
	 * @param int    $id Grid ID.
	 * @param string $status New status ('legacy', 'converted_draft', 'converted', 'new_builder').
	 * @return bool
	 */
	public static function update_status( $id, $status ) {
		global $wpdb;

		$id     = absint( $id );
		$status = sanitize_key( $status );

		$allowed = array( 'legacy', 'converted_draft', 'converted', 'new_builder' );
		if ( ! in_array( $status, $allowed, true ) ) {
			return false;
		}

		$schema_version = 'legacy' === $status ? 1 : 2;

		$updated = $wpdb->update(
			self::table(),
			array(
				'migration_status' => $status,
				'schema_version'   => $schema_version,
				'updated_at'       => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Lists grids with search, filtering, and pagination.
	 *
	 * @param array $args Query arguments.
	 * @return array Array containing 'items' and 'total'.
	 */
	public static function list_grids( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'per_page' => 20,
			'page'     => 1,
			'search'   => '',
			'status'   => '',
			'orderby'  => 'id',
			'order'    => 'DESC',
		);
		$params   = wp_parse_args( $args, $defaults );

		$per_page = max( 1, absint( $params['per_page'] ) );
		$page     = max( 1, absint( $params['page'] ) );
		$offset   = ( $page - 1 ) * $per_page;

		$where_clauses = array( '1=1' );
		$query_params  = array();

		if ( ! empty( $params['search'] ) ) {
			$where_clauses[] = 'title LIKE %s';
			$query_params[]  = '%' . $wpdb->esc_like( sanitize_text_field( $params['search'] ) ) . '%';
		}

		if ( ! empty( $params['status'] ) ) {
			$where_clauses[] = 'migration_status = %s';
			$query_params[]  = sanitize_key( $params['status'] );
		}

		$where_sql = implode( ' AND ', $where_clauses );

		$allowed_orderby = array( 'id', 'title', 'dated', 'updated_at', 'migration_status' );
		$orderby         = in_array( $params['orderby'], $allowed_orderby, true ) ? $params['orderby'] : 'id';
		$order           = 'ASC' === strtoupper( $params['order'] ) ? 'ASC' : 'DESC';

		// Count total matching.
		$count_sql = "SELECT COUNT(*) FROM " . self::table() . " WHERE {$where_sql}";
		if ( ! empty( $query_params ) ) {
			$count_sql = $wpdb->prepare( $count_sql, $query_params );
		}
		$total = (int) $wpdb->get_var( $count_sql );

		// Fetch items.
		$fetch_sql = "SELECT * FROM " . self::table() . " WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$query_params[] = $per_page;
		$query_params[] = $offset;
		$items = $wpdb->get_results( $wpdb->prepare( $fetch_sql, $query_params ) );

		$formatted = array();
		if ( ! empty( $items ) ) {
			foreach ( $items as $row ) {
				$formatted[] = self::get( $row->id );
			}
		}

		return array(
			'items' => $formatted,
			'total' => $total,
			'pages' => ceil( $total / $per_page ),
		);
	}
}

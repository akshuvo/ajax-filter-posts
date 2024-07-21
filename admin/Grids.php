<?php
namespace GridMaster;

class Grids {

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
	 * @return array
	 */
	public function list() {
		global $wpdb;
		$grids = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}gridmaster_grids" );
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
	 * @param int $id Grid id.
	 * @return object
	 */
	public static function get( $id ) {
		global $wpdb;
		$grid = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}gridmaster_grids WHERE id = %d", $id ) );

		// Unserialize if found
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

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
     * @param array $data
     * @return int
     */
    public function save( $data ) {
        global $wpdb;

        $id = isset( $data['id'] ) ? $data['id'] : 0;
        $title = isset( $data['title'] ) ? $data['title'] : '';
        $attributes = isset( $data['attributes'] ) ? $data['attributes'] : '';

        if ( $id ) {
            $wpdb->update( 
                "{$wpdb->prefix}gridmaster_grids", 
                [ 
                    'title' => $title,
                    'attributes' => $attributes
                ], 
                [ 'id' => $id ]
            );
        } else {
            $wpdb->insert( 
                "{$wpdb->prefix}gridmaster_grids", 
                [ 
                    'title' => $title,
                    'attributes' => $attributes
                ]
            );
            $id = $wpdb->insert_id;
        }

        return $id;
    }

    /**
     * Get grid by ID
     * 
     * @param int $id
     * @return object
     */
    public function get( $id ) {
        global $wpdb;
        $grid = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}gridmaster_grids WHERE id = %d", $id ) );
        return $grid;
    }

    /**
     * Delete grid
     * 
     * @param int $id
     * @return void
     */
    public function delete( $id ) {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'gridmaster_grids', [ 'id' => $id ] );
    }

}
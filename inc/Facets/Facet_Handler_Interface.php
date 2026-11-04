<?php
namespace GridMaster\Facets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface for all GridMaster facet / filter handlers.
 */
interface Facet_Handler_Interface {

	/**
	 * Returns human-readable label for the filter type.
	 *
	 * @return string
	 */
	public static function get_label();

	/**
	 * Renders the filter component HTML.
	 *
	 * @param array $facet Facet configuration array.
	 * @param int   $grid_id Grid ID.
	 * @param array $runtime_params Current runtime state (active filter values).
	 * @param bool  $is_preview True if rendering inside builder preview.
	 * @return string
	 */
	public static function render( $facet, $grid_id, $runtime_params = array(), $is_preview = false );
}

<?php
/**
 * Frontend display for Beaver Builder Post Grid Master module.
 *
 * @var object $settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$grid_id = ! empty( $settings->grid_id ) ? absint( $settings->grid_id ) : 0;
echo \GridMaster\Integrations\Embed_Helper::render( $grid_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

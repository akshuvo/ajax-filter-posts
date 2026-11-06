<?php
namespace GridMaster\Integrations\Widget;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classic WP Widget integration loader.
 */
class Loader {

	/**
	 * Init.
	 */
	public static function init() {
		add_action( 'widgets_init', array( __CLASS__, 'register_widget' ) );
	}

	/**
	 * Registers the widget.
	 */
	public static function register_widget() {
		require_once __DIR__ . '/Grid_Widget.php';
		register_widget( 'GridMaster\Integrations\Widget\Grid_Widget' );
	}
}

Loader::init();

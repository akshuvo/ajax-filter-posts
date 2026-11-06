<?php
namespace GridMaster\Integrations\Bricks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bricks Builder integration loader.
 */
class Loader {

	/**
	 * Init.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_element' ), 11 );
	}

	/**
	 * Registers the Bricks element.
	 */
	public static function register_element() {
		if ( ! class_exists( '\Bricks\Elements' ) ) {
			return;
		}

		\Bricks\Elements::register_element( __DIR__ . '/Grid_Element.php' );
	}
}

Loader::init();

<?php
namespace GridMaster\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Orchestrates page builder integrations with strict conditional loading.
 *
 * Each integration's loader is included ONLY if that builder is active on the current site.
 */
class Integrations_Manager {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'after_setup_theme', array( $this, 'load_active_integrations' ), 20 );
	}

	/**
	 * Returns the registry of supported builders, their detection test, and loader file path.
	 *
	 * @return array
	 */
	private function registry() {
		return array(
			'elementor' => array(
				'detect' => function () {
					return did_action( 'elementor/loaded' ) || defined( 'ELEMENTOR_VERSION' );
				},
				'file'   => 'Elementor/Loader.php',
			),
			'beaver'    => array(
				'detect' => function () {
					return class_exists( 'FLBuilder' );
				},
				'file'   => 'Beaver/Loader.php',
			),
			'bricks'    => array(
				'detect' => function () {
					return defined( 'BRICKS_VERSION' ) || 'bricks' === get_template();
				},
				'file'   => 'Bricks/Loader.php',
			),
			'wpbakery'  => array(
				'detect' => function () {
					return defined( 'WPB_VC_VERSION' ) || function_exists( 'vc_map' );
				},
				'file'   => 'WPBakery/Loader.php',
			),
			'widget'    => array(
				'detect' => function () {
					return function_exists( 'register_widget' );
				},
				'file'   => 'Widget/Loader.php',
			),
		);
	}

	/**
	 * Checks each builder and requires ONLY active builder loaders.
	 */
	public function load_active_integrations() {
		$registry = $this->registry();
		$enabled  = apply_filters( 'gridmaster_enabled_integrations', array_keys( $registry ) );

		foreach ( $registry as $key => $entry ) {
			if ( ! in_array( $key, $enabled, true ) ) {
				continue;
			}

			// Run detector callable.
			if ( ! is_callable( $entry['detect'] ) || ! call_user_func( $entry['detect'] ) ) {
				// Builder is inactive or not present; do not include any file from its folder.
				continue;
			}

			$loader_path = __DIR__ . '/' . $entry['file'];
			if ( file_exists( $loader_path ) ) {
				require_once $loader_path;
			}
		}
	}
}

<?php
namespace GridMaster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Elementor {

	private $registered = false;

	public function __construct() {
		add_action( 'elementor/widgets/register', array( $this, 'register_widget' ) );
		add_action( 'elementor/widgets/widgets_registered', array( $this, 'register_widget_legacy' ) );
	}

	public function register_widget( $widgets_manager ) {
		if ( $this->registered || ! class_exists( '\Elementor\Widget_Base' ) ) {
			return;
		}

		require_once GRIDMASTER_PATH . '/inc/Elementor_Widget.php';
		$widgets_manager->register( new Elementor_Widget() );
		$this->registered = true;
	}

	public function register_widget_legacy() {
		if ( $this->registered || ! class_exists( '\Elementor\Plugin' ) || ! class_exists( '\Elementor\Widget_Base' ) ) {
			return;
		}

		require_once GRIDMASTER_PATH . '/inc/Elementor_Widget.php';
		\Elementor\Plugin::instance()->widgets_manager->register_widget_type( new Elementor_Widget() );
		$this->registered = true;
	}
}

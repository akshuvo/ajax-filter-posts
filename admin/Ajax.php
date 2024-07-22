<?php
namespace GridMaster;

class Ajax {

	/**
	 * Class constructor.
	 */
	function __construct() {
		add_action( 'wp_ajax_gridmaster_ajax', array( $this, 'register_ajax' ) );
	}

	/**
	 * Register ajax
	 *
	 * @return void
	 */
	public function register_ajax() {
		$nonce = isset( $_REQUEST['gm_nonce'] ) ? sanitize_text_field( $_REQUEST['gm_nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'gm-ajax-nonce' ) ) {
			wp_send_json_error( __( 'Invalid nonce', 'gridmaster' ) );
		}

		$action = isset( $_REQUEST['gm-action'] ) ? sanitize_title( $_REQUEST['gm-action'] ) : '';
		if ( ! $action ) {
			wp_send_json_error( __( 'Invalid action', 'gridmaster' ) );
		}

		// Include the admin functions
		require_once GRIDMASTER_PATH . '/admin/admin-functions.php';

		// Make method.
		$method = str_replace( '-', '_', $action );
		// if ( ! method_exists( $method ) ) {
		// wp_send_json_error( __( 'Method doesn\'t exist', 'gridmaster' ) );
		// }

		// Call the ajax function
		$response = $this->$method( $_REQUEST );

		wp_die();
	}


	/**
	 * Save settings ajax handler
	 *
	 * @param  array $params Form data.
	 */
	function save_settings( $params ) {

		// Get Data.
		$data                               = isset( $params['gridmaster_options'] ) ? $params['gridmaster_options'] : array();
		$data['disable-icon-color-options'] = isset( $data['disable-icon-color-options'] ) ? $data['disable-icon-color-options'] : array();

		// Get Settings.
		$settings = gridmaster_get_settings();
		$settings = array_merge( $settings, $data );

		// Save Settings.
		update_option( 'gridmaster_settings', $settings );

		// Return json.
		wp_send_json_success(
			array(
				'message' => __( 'Settings Saved Successfully', 'gridmaster' ),
				'data'    => $settings,
			)
		);
	}

	/**
	 * Save grid ajax handler
	 *
	 * @param  array $params Form data.
	 */
	function save_grid( $params ) {

		// Get Data.
		$grid_title = isset( $params['title'] ) ? sanitize_text_field( $params['title'] ) : 'Sample Grid #' . wp_generate_password( 8, false );
		$grid_id    = isset( $params['id'] ) ? intval( $params['id'] ) : null;

		// Grid Handler Class.
		if ( ! class_exists( 'GridMaster\Grids' ) ) {
			require_once GRIDMASTER_PATH . '/admin/Grids.php';
		}

		// DB data array.
		$data = array(
			'title'      => $grid_title,
			'attributes' => $params,
		);

		// If has id
		if ( $grid_id ) {
			$data['id'] = $grid_id;
		}

		// Save/Update to DB.
		$grid_insert_id = Grids::init()->save( $data );

		// Return json.
		wp_send_json_success(
			array(
				'message' => __( 'Grid Saved Successfully', 'gridmaster' ),
				'grid_id' => $grid_insert_id,
			)
		);
	}

	/**
	 * List grids ajax handler
	 *
	 * @param  array $params Form data.
	 */
	function list_grids( $params ) {

		// Grid Handler Class.
		if ( ! class_exists( 'GridMaster\Grids' ) ) {
			require_once GRIDMASTER_PATH . '/admin/Grids.php';
		}

		// Save/Update to DB.
		$grids = Grids::init()->list();

		// Return json.
		wp_send_json_success(
			array(
				'grids' => $grids,
			)
		);
	}
}

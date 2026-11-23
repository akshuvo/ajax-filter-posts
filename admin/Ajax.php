<?php
namespace GridMaster;

/**
 * Ajax handler class.
 */
class Ajax {

	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_gridmaster_ajax', array( $this, 'register_ajax' ) );
	}

	/**
	 * Register ajax
	 *
	 * @return void
	 */
	public function register_ajax() {
		$nonce = isset( $_REQUEST['gm_nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['gm_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'gm-ajax-nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'ajax-filter-posts' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'ajax-filter-posts' ) ), 403 );
		}

		$action = isset( $_REQUEST['gm-action'] ) ? sanitize_key( wp_unslash( $_REQUEST['gm-action'] ) ) : '';
		$allowed_actions = array(
			'save_settings' => 'save_settings',
			'save-settings' => 'save_settings',
			'save_grid'     => 'save_grid',
			'save-grid'     => 'save_grid',
			'list_grids'    => 'list_grids',
			'list-grids'    => 'list_grids',
		);

		if ( ! $action || ! isset( $allowed_actions[ $action ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid action.', 'ajax-filter-posts' ) ), 400 );
		}

		// Include the admin functions.
		require_once GRIDMASTER_PATH . '/admin/admin-functions.php';

		$method = $allowed_actions[ $action ];
		$params = $this->sanitize_request_array( wp_unslash( $_REQUEST ) );

		$this->$method( $params );

		wp_die();
	}

	/**
	 * Recursively sanitizes scalar request parameters with sanitize_text_field.
	 *
	 * @param mixed $value Raw unslashed input value.
	 * @return mixed Sanitized value.
	 */
	private function sanitize_request_array( $value ) {
		if ( is_array( $value ) ) {
			$clean = array();
			foreach ( $value as $k => $v ) {
				$clean[ sanitize_text_field( (string) $k ) ] = $this->sanitize_request_array( $v );
			}
			return $clean;
		}
		if ( is_scalar( $value ) ) {
			return sanitize_text_field( (string) $value );
		}
		return '';
	}

	/**
	 * Save settings ajax handler
	 *
	 * @param  array $params Form data.
	 */
	private function save_settings( $params ) {
		$raw_options = isset( $params['gridmaster_options'] ) && is_array( $params['gridmaster_options'] )
			? $params['gridmaster_options']
			: array();

		$clean_options = array(
			'debug-mode'                 => ( isset( $raw_options['debug-mode'] ) && 'yes' === $raw_options['debug-mode'] ) ? 'yes' : 'no',
			'disable-nonce-check'        => ( isset( $raw_options['disable-nonce-check'] ) && 'yes' === $raw_options['disable-nonce-check'] ) ? 'yes' : 'no',
			'disable-icon-color-options' => array(),
		);

		if ( isset( $raw_options['disable-icon-color-options'] ) && is_array( $raw_options['disable-icon-color-options'] ) ) {
			$clean_options['disable-icon-color-options'] = array_values(
				array_unique(
					array_filter( array_map( 'sanitize_key', $raw_options['disable-icon-color-options'] ) )
				)
			);
		}

		// Preserve and sanitize any additional extension keys cleanly.
		foreach ( $raw_options as $opt_key => $opt_val ) {
			$safe_key = sanitize_key( (string) $opt_key );
			if ( '' !== $safe_key && ! array_key_exists( $safe_key, $clean_options ) ) {
				$clean_options[ $safe_key ] = $this->sanitize_request_array( $opt_val );
			}
		}

		// Get existing Settings and merge.
		$settings = gridmaster_get_settings();
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		$settings = array_merge( $settings, $clean_options );

		// Save Settings.
		update_option( 'gridmaster_settings', $settings );

		// Return json.
		wp_send_json_success(
			array(
				'message' => __( 'Settings Saved Successfully', 'ajax-filter-posts' ),
				'data'    => $settings,
			)
		);
	}

	/**
	 * Save grid ajax handler
	 *
	 * @param  array $params Form data.
	 */
	private function save_grid( $params ) {

		// Get Data.
		$grid_title = isset( $params['title'] ) ? sanitize_text_field( $params['title'] ) : 'Sample Grid #' . wp_generate_password( 8, false );
		$grid_id    = isset( $params['id'] ) ? intval( $params['id'] ) : null;

		// Grid Handler Class.
		if ( ! class_exists( 'GridMaster\Grids' ) ) {
			require_once GRIDMASTER_PATH . '/admin/Grids.php';
		}

		// Ignorable params.
		$ignored_params = array(
			'id',
			'title',
			'action',
			'gm-action',
			'gm_nonce',
			'_wp_http_referer',
		);

		// Unset params if ignorable.
		foreach ( $ignored_params as $value ) {
			if ( isset( $params[ $value ] ) ) {
				unset( $params[ $value ] );
			}
		}

		// DB data array.
		$data = array(
			'title'      => $grid_title,
			'attributes' => $params,
		);

		// If has id.
		if ( $grid_id ) {
			$data['id'] = $grid_id;
		}

		// Save/Update to DB.
		$grid_insert_id = Grids::init()->save( $data );

		// Return json.
		wp_send_json_success(
			array(
				'message' => __( 'Grid Saved Successfully', 'ajax-filter-posts'  ),
				'grid_id' => $grid_insert_id,
			)
		);
	}

	/**
	 * List grids ajax handler
	 *
	 * @param  array $params Form data.
	 */
	private function list_grids( $params ) {

		// Grid Handler Class.
		if ( ! class_exists( 'GridMaster\Grids' ) ) {
			require_once GRIDMASTER_PATH . '/admin/Grids.php';
		}

		// Save/Update to DB.
		$grids = Grids::init()->list( $params );

		// Return json.
		wp_send_json_success(
			array(
				'grids' => $grids,
			)
		);
	}
}

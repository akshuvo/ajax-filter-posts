<?php
namespace GridMaster\REST;

use GridMaster\Storage\Grid_Repository;
use GridMaster\Engine\Query_Builder;
use GridMaster\Engine\Grid_Renderer;
use GridMaster\Migration\Legacy_Migrator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API Controller for GridMaster 2.0.
 */
class Grids_Controller extends \WP_REST_Controller {

	/**
	 * Namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'gridmaster/v2';

	/**
	 * Rest base.
	 *
	 * @var string
	 */
	protected $rest_base = 'grids';

	/**
	 * Registers REST API routes.
	 */
	public function register_routes() {
		// List grids / create grid.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
				),
			)
		);

		// Single grid: read, update, delete.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => array(
						'id' => array(
							'validate_callback' => function( $param ) {
								return is_numeric( $param );
							},
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => array(
						'id' => array(
							'validate_callback' => function( $param ) {
								return is_numeric( $param );
							},
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
					'args'                => array(
						'id' => array(
							'validate_callback' => function( $param ) {
								return is_numeric( $param );
							},
						),
					),
				),
			)
		);

		// Duplicate grid.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/duplicate',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'duplicate_item' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
			)
		);

		// Migrate legacy grid.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/migrate',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'migrate_item' ),
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
			)
		);

		// Rollback migrated grid.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/rollback',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rollback_item' ),
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
			)
		);

		// Embed preview endpoint for Gutenberg and block editors.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/embed-preview',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_embed_preview' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'args'                => array(
					'id' => array(
						'validate_callback' => function( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);

		// Public dynamic rendering endpoint for frontend AJAX filtering/pagination.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/render',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'render_grid_ajax' ),
				'permission_callback' => '__return_true', // Publicly readable/renderable for frontend visitors
			)
		);

		// Live preview.
		register_rest_route(
			$this->namespace,
			'/preview',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'render_preview' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
			)
		);

		// Interactive preview AJAX rendering (preserves draft config & blocks during filtering/paging).
		register_rest_route(
			$this->namespace,
			'/preview/render',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'render_preview_ajax' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
			)
		);

		// Public taxonomies and post types helpers for builder.
		register_rest_route(
			$this->namespace,
			'/meta-data',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_builder_metadata' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
			)
		);

		// Export a grid configuration and Gutenberg card template as portable JSON.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/export',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'export_item' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
			)
		);

		// Import one or more grids from portable JSON.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/import',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'import_items' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
			)
		);
	}

	/**
	 * Permission check for reading grids (Administrators / Grid Managers only).
	 */
	public function get_items_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Permission check for creating grids.
	 */
	public function create_item_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Permission check for reading a single grid.
	 */
	public function get_item_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Permission check for updating a grid.
	 */
	public function update_item_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Permission check for deleting a grid.
	 */
	public function delete_item_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Retrieves a list of grids.
	 */
	public function get_items( $request ) {
		$params = array(
			'per_page' => $request->get_param( 'per_page' ) ? absint( $request->get_param( 'per_page' ) ) : 20,
			'page'     => $request->get_param( 'page' ) ? absint( $request->get_param( 'page' ) ) : 1,
			'search'   => $request->get_param( 'search' ) ? sanitize_text_field( $request->get_param( 'search' ) ) : '',
			'status'   => $request->get_param( 'status' ) ? sanitize_key( $request->get_param( 'status' ) ) : '',
			'orderby'  => $request->get_param( 'orderby' ) ? sanitize_key( $request->get_param( 'orderby' ) ) : 'id',
			'order'    => $request->get_param( 'order' ) ? sanitize_key( $request->get_param( 'order' ) ) : 'DESC',
		);

		$result = Grid_Repository::list_grids( $params );
		return rest_ensure_response( $result );
	}

	/**
	 * Retrieves a single grid.
	 */
	public function get_item( $request ) {
		$id   = absint( $request['id'] );
		$grid = Grid_Repository::get( $id );

		if ( ! $grid ) {
			return new \WP_Error( 'grid_not_found', __( 'Grid not found.', 'ajax-filter-posts' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( $grid );
	}

	/**
	 * Creates a new grid.
	 */
	public function create_item( $request ) {
		$config = $request->get_param( 'config' );
		if ( is_string( $config ) ) {
			$config = json_decode( $config, true );
		}
		if ( is_array( $config ) && ! current_user_can( 'unfiltered_html' ) ) {
			if ( isset( $config['custom_code']['js'] ) ) {
				$config['custom_code']['js'] = '';
			}
			if ( isset( $config['custom_js'] ) ) {
				$config['custom_js'] = '';
			}
		}

		$data = array(
			'id'               => 0,
			'title'            => $request->get_param( 'title' ),
			'config'           => $config,
			'block_content'    => $request->get_param( 'block_content' ),
			'migration_status' => 'new_builder',
			'schema_version'   => 2,
		);

		$grid_id = Grid_Repository::save( $data );
		if ( is_wp_error( $grid_id ) ) {
			return $grid_id;
		}

		$created = Grid_Repository::get( $grid_id );
		return rest_ensure_response( $created );
	}

	/**
	 * Updates an existing grid.
	 */
	public function update_item( $request ) {
		$id       = absint( $request['id'] );
		$existing = Grid_Repository::get( $id );

		if ( ! $existing ) {
			return new \WP_Error( 'grid_not_found', __( 'Grid not found.', 'ajax-filter-posts' ), array( 'status' => 404 ) );
		}

		$config = $request->has_param( 'config' ) ? $request->get_param( 'config' ) : $existing->config;
		if ( is_string( $config ) ) {
			$config = json_decode( $config, true );
		}
		if ( is_array( $config ) && ! current_user_can( 'unfiltered_html' ) ) {
			if ( isset( $config['custom_code']['js'] ) ) {
				$config['custom_code']['js'] = isset( $existing->config['custom_code']['js'] ) ? $existing->config['custom_code']['js'] : '';
			}
			if ( isset( $config['custom_js'] ) ) {
				$config['custom_js'] = isset( $existing->config['custom_js'] ) ? $existing->config['custom_js'] : '';
			}
		}

		$data = array(
			'id'               => $id,
			'title'            => $request->has_param( 'title' ) ? $request->get_param( 'title' ) : $existing->title,
			'config'           => $config,
			'block_content'    => $request->has_param( 'block_content' ) ? $request->get_param( 'block_content' ) : $existing->block_content,
			'migration_status' => $request->has_param( 'migration_status' ) ? $request->get_param( 'migration_status' ) : $existing->migration_status,
			'schema_version'   => 2,
		);

		$saved_id = Grid_Repository::save( $data );
		if ( is_wp_error( $saved_id ) ) {
			return $saved_id;
		}

		$updated = Grid_Repository::get( $id );
		return rest_ensure_response( $updated );
	}

	/**
	 * Deletes a grid.
	 */
	public function delete_item( $request ) {
		$id      = absint( $request['id'] );
		$deleted = Grid_Repository::delete( $id );

		if ( ! $deleted ) {
			return new \WP_Error( 'delete_failed', __( 'Could not delete grid.', 'ajax-filter-posts' ), array( 'status' => 400 ) );
		}

		return rest_ensure_response( array( 'deleted' => true, 'id' => $id ) );
	}

	/**
	 * Duplicates a grid.
	 */
	public function duplicate_item( $request ) {
		$id     = absint( $request['id'] );
		$new_id = Grid_Repository::duplicate( $id );

		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}

		$duplicate = Grid_Repository::get( $new_id );
		return rest_ensure_response( $duplicate );
	}

	/**
	 * Migrates a legacy grid to v2.
	 */
	public function migrate_item( $request ) {
		$id = absint( $request['id'] );
		if ( ! class_exists( 'GridMaster\Migration\Legacy_Migrator' ) ) {
			require_once GRIDMASTER_PATH . '/inc/Migration/Legacy_Migrator.php';
		}

		$result = Legacy_Migrator::migrate( $id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$grid = Grid_Repository::get( $id );
		return rest_ensure_response( $grid );
	}

	/**
	 * Rolls back a migrated grid to legacy.
	 */
	public function rollback_item( $request ) {
		$id = absint( $request['id'] );
		if ( ! class_exists( 'GridMaster\Migration\Legacy_Migrator' ) ) {
			require_once GRIDMASTER_PATH . '/inc/Migration/Legacy_Migrator.php';
		}

		$result = Legacy_Migrator::rollback( $id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$grid = Grid_Repository::get( $id );
		return rest_ensure_response( $grid );
	}

	/**
	 * Exports a grid's configuration and Gutenberg card markup as portable JSON.
	 *
	 * @param \WP_REST_Request $request REST request instance.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function export_item( $request ) {
		$id   = absint( $request['id'] );
		$grid = Grid_Repository::get( $id );

		if ( ! $grid ) {
			return new \WP_Error( 'grid_not_found', __( 'Grid not found.', 'ajax-filter-posts' ), array( 'status' => 404 ) );
		}

		$payload = array(
			'format'         => 'gridmaster-grid-v2',
			'schema_version' => 2,
			'exported_at'    => gmdate( 'c' ),
			'plugin_version' => defined( 'GRIDMASTER_VERSION' ) ? GRIDMASTER_VERSION : '2.0.0',
			'grid'           => array(
				'title'         => (string) $grid->title,
				'config'        => is_array( $grid->config ) ? $grid->config : Grid_Repository::default_config(),
				'block_content' => isset( $grid->block_content ) ? (string) $grid->block_content : '',
			),
		);

		return rest_ensure_response( $payload );
	}

	/**
	 * Imports one or more grids from portable JSON payloads.
	 *
	 * @param \WP_REST_Request $request REST request instance.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function import_items( $request ) {
		$raw = $request->get_json_params();
		if ( empty( $raw ) ) {
			$raw = $request->get_param( 'payload' );
			if ( is_string( $raw ) ) {
				$raw = json_decode( $raw, true );
			}
		}

		if ( ! is_array( $raw ) ) {
			return new \WP_Error( 'invalid_import_payload', __( 'Invalid JSON import payload.', 'ajax-filter-posts' ), array( 'status' => 400 ) );
		}

		$entries = array();
		if ( isset( $raw['grids'] ) && is_array( $raw['grids'] ) ) {
			$entries = $raw['grids'];
		} elseif ( isset( $raw['grid'] ) && is_array( $raw['grid'] ) ) {
			$entries = array( $raw['grid'] );
		} elseif ( isset( $raw['title'] ) || isset( $raw['config'] ) || isset( $raw['block_content'] ) ) {
			$entries = array( $raw );
		} else {
			return new \WP_Error( 'invalid_import_schema', __( 'Unrecognized GridMaster export schema.', 'ajax-filter-posts' ), array( 'status' => 400 ) );
		}

		if ( empty( $entries ) ) {
			return new \WP_Error( 'empty_import', __( 'Import payload does not contain any grids.', 'ajax-filter-posts' ), array( 'status' => 400 ) );
		}

		$defaults = Grid_Repository::default_config();
		$imported = array();

		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$config = isset( $entry['config'] ) ? $entry['config'] : array();
			if ( is_string( $config ) ) {
				$config = json_decode( $config, true );
			}
			$has_block_content = isset( $entry['block_content'] ) && is_string( $entry['block_content'] );

			if ( ! is_array( $config ) && ! $has_block_content ) {
				continue;
			}

			$config = $this->merge_config_defaults( $defaults, is_array( $config ) ? $config : array() );

			if ( ! current_user_can( 'unfiltered_html' ) ) {
				if ( isset( $config['custom_code']['js'] ) ) {
					$config['custom_code']['js'] = '';
				}
				if ( isset( $config['custom_js'] ) ) {
					$config['custom_js'] = '';
				}
			}

			$title = ! empty( $entry['title'] )
				? sanitize_text_field( (string) $entry['title'] )
				: __( 'Imported Grid', 'ajax-filter-posts' );

			$block_content = $has_block_content ? $entry['block_content'] : Grid_Repository::default_block_content();

			$saved_id = Grid_Repository::save(
				array(
					'id'               => 0,
					'title'            => $title,
					'config'           => $config,
					'block_content'    => $block_content,
					'migration_status' => 'new_builder',
					'schema_version'   => 2,
				)
			);

			if ( ! is_wp_error( $saved_id ) && $saved_id ) {
				$created = Grid_Repository::get( $saved_id );
				if ( $created ) {
					$imported[] = $created;
				}
			}
		}

		if ( empty( $imported ) ) {
			return new \WP_Error( 'invalid_grid_entry', __( 'No valid grid entries could be imported.', 'ajax-filter-posts' ), array( 'status' => 400 ) );
		}

		return new \WP_REST_Response(
			array(
				'imported' => count( $imported ),
				'ids'      => array_map(
					function ( $g ) {
						return (int) $g->id;
					},
					$imported
				),
				'grids'    => $imported,
			),
			201
		);
	}

	/**
	 * Recursively merges missing default config keys into imported configs.
	 *
	 * @param array $defaults Default associative config array.
	 * @param array $config   Imported config array.
	 * @return array Merged config.
	 */
	private function merge_config_defaults( array $defaults, array $config ) {
		foreach ( $defaults as $key => $default_val ) {
			if ( ! array_key_exists( $key, $config ) ) {
				$config[ $key ] = $default_val;
			} elseif ( is_array( $default_val ) && ! empty( $default_val ) && is_array( $config[ $key ] ) ) {
				$config[ $key ] = $this->merge_config_defaults( $default_val, $config[ $key ] );
			}
		}
		return $config;
	}

	/**
	 * Extracts and sanitizes runtime filter, sort, search, and pagination parameters from a REST request.
	 *
	 * @param \WP_REST_Request $request REST request instance.
	 * @return array Sanitized runtime parameters.
	 */
	private function extract_runtime_params( $request ) {
		$raw_facets = $request->get_param( 'facets' );
		$facets     = array();
		if ( is_array( $raw_facets ) ) {
			foreach ( $raw_facets as $tax => $terms ) {
				$clean_tax = sanitize_key( $tax );
				if ( '' !== $clean_tax ) {
					$facets[ $clean_tax ] = is_array( $terms ) ? array_map( 'absint', $terms ) : array( absint( $terms ) );
				}
			}
		}

		$raw_meta = $request->get_param( 'meta' );
		$meta     = array();
		if ( is_array( $raw_meta ) ) {
			foreach ( $raw_meta as $mk => $mv ) {
				$clean_mk = sanitize_key( $mk );
				if ( '' !== $clean_mk && is_scalar( $mv ) ) {
					$meta[ $clean_mk ] = sanitize_text_field( (string) $mv );
				}
			}
		}

		$raw_bookmarks = $request->get_param( 'bookmarks' );
		$bookmarks     = is_array( $raw_bookmarks ) ? array_values( array_filter( array_map( 'absint', $raw_bookmarks ) ) ) : array();

		$raw_min_price = $request->get_param( 'min_price' );
		$raw_max_price = $request->get_param( 'max_price' );

		$raw_context = $request->get_param( 'context' );
		$context     = array();
		if ( is_array( $raw_context ) ) {
			if ( ! empty( $raw_context['post_id'] ) ) {
				$context['post_id'] = absint( $raw_context['post_id'] );
			}
			if ( ! empty( $raw_context['post_type'] ) ) {
				$context['post_type'] = sanitize_key( $raw_context['post_type'] );
			}
			if ( ! empty( $raw_context['term_id'] ) ) {
				$context['term_id'] = absint( $raw_context['term_id'] );
			}
			if ( ! empty( $raw_context['taxonomy'] ) ) {
				$context['taxonomy'] = sanitize_key( $raw_context['taxonomy'] );
			}
			if ( ! empty( $raw_context['author_id'] ) ) {
				$context['author_id'] = absint( $raw_context['author_id'] );
			}
			if ( ! empty( $raw_context['search_query'] ) ) {
				$context['search_query'] = sanitize_text_field( $raw_context['search_query'] );
			}
		}

		return array(
			'paged'      => $request->get_param( 'page' ) ? absint( $request->get_param( 'page' ) ) : 1,
			'facets'     => $facets,
			'search'     => $request->get_param( 'search' ) ? sanitize_text_field( $request->get_param( 'search' ) ) : '',
			'orderby'    => $request->get_param( 'orderby' ) ? sanitize_key( $request->get_param( 'orderby' ) ) : '',
			'min_price'  => ( '' !== $raw_min_price && null !== $raw_min_price ) ? floatval( $raw_min_price ) : '',
			'max_price'  => ( '' !== $raw_max_price && null !== $raw_max_price ) ? floatval( $raw_max_price ) : '',
			'min_rating' => $request->get_param( 'min_rating' ) ? absint( $request->get_param( 'min_rating' ) ) : 0,
			'on_sale'    => ! empty( $request->get_param( 'on_sale' ) ),
			'in_stock'   => ! empty( $request->get_param( 'in_stock' ) ),
			'featured'   => ! empty( $request->get_param( 'featured' ) ),
			'meta'       => $meta,
			'date_from'  => $request->get_param( 'date_from' ) ? sanitize_text_field( $request->get_param( 'date_from' ) ) : '',
			'date_to'    => $request->get_param( 'date_to' ) ? sanitize_text_field( $request->get_param( 'date_to' ) ) : '',
			'bookmarks'  => $bookmarks,
			'context'    => $context,
		);
	}

	/**
	 * Public endpoint for frontend AJAX filtering & pagination.
	 */
	public function render_grid_ajax( $request ) {
		$grid_id = absint( $request['id'] );

		if ( ! class_exists( 'GridMaster\Engine\Grid_Renderer' ) ) {
			require_once GRIDMASTER_PATH . '/inc/Engine/Grid_Renderer.php';
		}

		$runtime_params = $this->extract_runtime_params( $request );

		$html = Grid_Renderer::render_grid( $grid_id, $runtime_params );

		$response_data = apply_filters(
			'gridmaster_rest_render_response',
			array(
				'html'    => $html,
				'success' => true,
			),
			$grid_id,
			$runtime_params,
			$request
		);

		return rest_ensure_response( $response_data );
	}

	/**
	 * Renders live repeated preview HTML for the builder.
	 */
	public function render_preview( $request ) {
		if ( ! class_exists( 'GridMaster\Engine\Grid_Renderer' ) ) {
			require_once GRIDMASTER_PATH . '/inc/Engine/Grid_Renderer.php';
		}

		$config        = $request->get_param( 'config' );
		$block_content = $request->get_param( 'block_content' );
		$grid_id       = $request->get_param( 'id' ) ? absint( $request->get_param( 'id' ) ) : 0;

		if ( is_string( $config ) ) {
			$config = json_decode( $config, true );
		}
		if ( ! is_array( $config ) ) {
			$config = Grid_Repository::default_config();
		}

		if ( empty( $block_content ) ) {
			$block_content = Grid_Repository::default_block_content();
		}

		$html = Grid_Renderer::render_preview( $config, $block_content, $grid_id );

		return rest_ensure_response( array(
			'html'    => $html,
			'success' => true,
		) );
	}

	/**
	 * Renders interactive preview AJAX updates (keeps draft settings during filtering/pagination).
	 */
	public function render_preview_ajax( $request ) {
		if ( ! class_exists( 'GridMaster\Engine\Grid_Renderer' ) ) {
			require_once GRIDMASTER_PATH . '/inc/Engine/Grid_Renderer.php';
		}

		$config        = $request->get_param( 'config' );
		$block_content = $request->get_param( 'block_content' );
		$grid_id       = $request->get_param( 'id' ) ? absint( $request->get_param( 'id' ) ) : 0;

		if ( is_string( $config ) ) {
			$config = json_decode( $config, true );
		}
		if ( ! is_array( $config ) ) {
			$config = Grid_Repository::default_config();
		}

		if ( empty( $block_content ) ) {
			$block_content = Grid_Repository::default_block_content();
		}

		$runtime_params = $this->extract_runtime_params( $request );

		$html = Grid_Renderer::render_preview( $config, $block_content, $grid_id, $runtime_params );

		return rest_ensure_response( array(
			'html'    => $html,
			'success' => true,
		) );
	}

	/**
	 * Returns post types and taxonomies for builder selectors.
	 */
	public function get_builder_metadata( $request ) {
		$selected_taxonomy = $request->get_param( 'taxonomy' );
		$selected_taxonomy = is_string( $selected_taxonomy ) ? sanitize_key( $selected_taxonomy ) : '';
		$term_search = $request->get_param( 'term_search' );
		$term_search = is_string( $term_search ) ? substr( sanitize_text_field( $term_search ), 0, 200 ) : '';
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		$formatted_pts = array();
		foreach ( $post_types as $pt ) {
			if ( 'attachment' === $pt->name ) {
				continue;
			}
			$formatted_pts[] = array(
				'name'  => $pt->name,
				'label' => $pt->labels->singular_name ? $pt->labels->singular_name : $pt->label,
			);
		}

		$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
		$formatted_tax = array();
		foreach ( $taxonomies as $tax ) {
			if ( $selected_taxonomy && $tax->name !== $selected_taxonomy ) {
				continue;
			}
			$terms = get_terms( array(
				'taxonomy'   => $tax->name,
				'hide_empty' => false,
				'number'     => 100,
				'search'     => $term_search,
			) );

			$term_list = array();
			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$term_list[] = array(
						'id'    => $term->term_id,
						'name'  => $term->name,
						'slug'  => $term->slug,
						'count' => $term->count,
					);
				}
			}

			$formatted_tax[] = array(
				'name'       => $tax->name,
				'label'      => $tax->labels->singular_name ? $tax->labels->singular_name : $tax->label,
				'post_types' => $tax->object_type,
				'terms'      => $term_list,
			);
		}

		$author_users = get_users( array(
			'capability' => array( 'edit_posts' ),
			'orderby'    => 'display_name',
			'order'      => 'ASC',
			'number'     => 100,
			'fields'     => array( 'ID', 'display_name' ),
		) );
		$formatted_authors = array();
		if ( is_array( $author_users ) ) {
			foreach ( $author_users as $u ) {
				$formatted_authors[] = array(
					'id'   => (int) $u->ID,
					'name' => $u->display_name,
				);
			}
		}

		return rest_ensure_response( array(
			'post_types'  => $formatted_pts,
			'taxonomies'  => $formatted_tax,
			'authors'     => $formatted_authors,
			'pro_active'  => defined( 'GM_PRO_VERSION' ) || defined( 'GRIDMASTER_PRO_VERSION' ),
			'pro_css_url' => defined( 'GM_PRO_URL' ) ? GM_PRO_URL . 'assets/css/frontend.css' : ( defined( 'GRIDMASTER_PRO_ASSETS_URL' ) ? GRIDMASTER_PRO_ASSETS_URL . '/css/frontend.css' : '' ),
			'pro_js_url'  => defined( 'GM_PRO_URL' ) ? GM_PRO_URL . 'assets/frontend.js' : ( defined( 'GRIDMASTER_PRO_ASSETS_URL' ) ? GRIDMASTER_PRO_ASSETS_URL . '/frontend.js' : '' ),
		) );
	}

	/**
	 * Returns rendered HTML, stylesheets, and scripts for embedding a grid into an editor iframe (Gutenberg).
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_embed_preview( $request ) {
		$id   = absint( $request->get_param( 'id' ) );
		$grid = Grid_Repository::get( $id );

		if ( ! $grid ) {
			return new \WP_Error(
				'grid_not_found',
				__( 'Grid not found.', 'ajax-filter-posts' ),
				array(
					'status' => 404,
					'exists' => false,
				)
			);
		}

		$rendered_data = \GridMaster\Integrations\Embed_Helper::render_with_assets( $id );

		return rest_ensure_response(
			array(
				'id'       => $id,
				'title'    => ! empty( $grid->title ) ? $grid->title : sprintf( __( 'Grid #%d', 'ajax-filter-posts' ), $id ),
				'engine'   => isset( $grid->migration_status ) && 'legacy' === $grid->migration_status ? 'legacy' : 'v2',
				'exists'   => true,
				'html'     => $rendered_data['html'],
				'styles'   => $rendered_data['styles'],
				'scripts'  => $rendered_data['scripts'],
				'edit_url' => current_user_can( 'manage_options' ) ? \GridMaster\Integrations\Embed_Helper::get_edit_url( $id ) : '',
			)
		);
	}
}

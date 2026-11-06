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
	 * Public endpoint for frontend AJAX filtering & pagination.
	 */
	public function render_grid_ajax( $request ) {
		$grid_id = absint( $request['id'] );

		if ( ! class_exists( 'GridMaster\Engine\Grid_Renderer' ) ) {
			require_once GRIDMASTER_PATH . '/inc/Engine/Grid_Renderer.php';
		}

		$runtime_params = array(
			'paged'  => $request->get_param( 'page' ) ? absint( $request->get_param( 'page' ) ) : 1,
			'facets' => $request->get_param( 'facets' ) && is_array( $request->get_param( 'facets' ) ) ? $request->get_param( 'facets' ) : array(),
			'search' => $request->get_param( 'search' ) ? sanitize_text_field( $request->get_param( 'search' ) ) : '',
		);

		$html = Grid_Renderer::render_grid( $grid_id, $runtime_params );

		return rest_ensure_response( array(
			'html'    => $html,
			'success' => true,
		) );
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

		$runtime_params = array(
			'paged'  => $request->get_param( 'page' ) ? absint( $request->get_param( 'page' ) ) : 1,
			'facets' => $request->get_param( 'facets' ) && is_array( $request->get_param( 'facets' ) ) ? $request->get_param( 'facets' ) : array(),
			'search' => $request->get_param( 'search' ) ? sanitize_text_field( $request->get_param( 'search' ) ) : '',
		);

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

		return rest_ensure_response( array(
			'post_types' => $formatted_pts,
			'taxonomies' => $formatted_tax,
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

<?php
namespace GridMaster\Integrations;

use GridMaster\Storage\Grid_Repository;
use GridMaster\Engine\Grid_Renderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared helper for embedding GridMaster grids across all page builders.
 */
class Embed_Helper {

	/**
	 * Retrieves an array of saved grids suitable for select/dropdown controls.
	 *
	 * @param bool $include_placeholder Whether to prepend a default empty choice.
	 * @return array Key-value pairs of grid ID => label.
	 */
	public static function get_grid_choices( $include_placeholder = true ) {
		$limit = apply_filters( 'gridmaster_embed_grid_choices_limit', 500 );
		$limit = max( 10, absint( $limit ) );

		$grids_result = Grid_Repository::list_grids(
			array(
				'per_page' => $limit,
				'orderby'  => 'id',
				'order'    => 'DESC',
			)
		);

		$choices = array();
		if ( $include_placeholder ) {
			$choices[0] = __( '— Select a Grid —', 'ajax-filter-posts' );
		}

		if ( ! empty( $grids_result['items'] ) ) {
			foreach ( $grids_result['items'] as $grid ) {
				$status_tag = '';
				if ( isset( $grid->migration_status ) && 'legacy' === $grid->migration_status ) {
					$status_tag = ' (' . __( 'Legacy', 'ajax-filter-posts' ) . ')';
				}

				$display_title = ! empty( $grid->title ) ? $grid->title : sprintf( __( 'Grid #%d', 'ajax-filter-posts' ), $grid->id );
				$choices[ (int) $grid->id ] = sprintf( '#%d — %s%s', (int) $grid->id, $display_title, $status_tag );
			}
		}

		return $choices;
	}

	/**
	 * Gets the direct editor URL to modify the specified grid.
	 *
	 * Only returns a URL if the current user has manage_options capability.
	 *
	 * @param int $grid_id Grid ID.
	 * @return string Admin URL or empty string if unauthorized / not found.
	 */
	public static function get_edit_url( $grid_id ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return '';
		}

		$grid_id = absint( $grid_id );
		if ( ! $grid_id ) {
			return '';
		}

		$grid = Grid_Repository::get( $grid_id );
		if ( ! $grid ) {
			return '';
		}

		$is_legacy = ( isset( $grid->migration_status ) && 'legacy' === $grid->migration_status );
		if ( $is_legacy ) {
			return admin_url( 'admin.php?page=gridmaster&path=build-grid&id=' . $grid_id );
		}

		return admin_url( 'admin.php?page=gridmaster-builder&id=' . $grid_id );
	}

	/**
	 * Gets the URL to create a new grid.
	 *
	 * @return string Admin URL or empty string.
	 */
	public static function get_create_url() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return '';
		}

		return admin_url( 'admin.php?page=gridmaster-builder' );
	}

	/**
	 * Gets the URL to manage all grids.
	 *
	 * @return string Admin URL or empty string.
	 */
	public static function get_manage_url() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return '';
		}

		return admin_url( 'admin.php?page=gridmaster' );
	}

	/**
	 * Determines whether the current request is being evaluated inside a page builder editor / preview canvas.
	 *
	 * @return bool
	 */
	public static function is_builder_preview() {
		// Elementor edit or preview mode.
		if ( did_action( 'elementor/loaded' ) && class_exists( '\Elementor\Plugin' ) ) {
			if ( \Elementor\Plugin::$instance->editor && \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				return true;
			}
			if ( \Elementor\Plugin::$instance->preview && \Elementor\Plugin::$instance->preview->is_preview_mode() ) {
				return true;
			}
		}

		// Beaver Builder active canvas.
		if ( class_exists( 'FLBuilderModel' ) && method_exists( 'FLBuilderModel', 'is_builder_active' ) ) {
			if ( \FLBuilderModel::is_builder_active() ) {
				return true;
			}
		}

		// Bricks Builder canvas.
		if ( function_exists( 'bricks_is_builder' ) && bricks_is_builder() ) {
			return true;
		}
		if ( function_exists( 'bricks_is_builder_main' ) && bricks_is_builder_main() ) {
			return true;
		}
		if ( function_exists( 'bricks_is_builder_call' ) && bricks_is_builder_call() ) {
			return true;
		}

		// WPBakery frontend editor.
		if ( function_exists( 'vc_is_page_editable' ) && vc_is_page_editable() ) {
			return true;
		}

		// Gutenberg REST embed preview.
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST && isset( $_SERVER['REQUEST_URI'] ) && false !== strpos( $_SERVER['REQUEST_URI'], 'embed-preview' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			return true;
		}

		return false;
	}

	/**
	 * Renders a grid by numeric ID with builder-aware empty states and edit links.
	 *
	 * @param int   $grid_id Grid ID.
	 * @param array $args Optional rendering arguments.
	 * @return string HTML output.
	 */
	public static function render( $grid_id, $args = array() ) {
		$grid_id    = absint( $grid_id );
		$is_preview = self::is_builder_preview();

		if ( ! $grid_id ) {
			if ( $is_preview ) {
				$create_url = self::get_create_url();
				$btn_html   = $create_url ? sprintf(
					'<a href="%s" target="_blank" rel="noopener noreferrer" class="gm-builder-btn"><span class="dashicons dashicons-plus-alt2"></span> %s</a>',
					esc_url( $create_url ),
					esc_html__( 'Create New Grid', 'ajax-filter-posts' )
				) : '';

				return sprintf(
					'<div class="gm-builder-placeholder"><span class="dashicons dashicons-grid-view"></span><strong>%s</strong><p>%s</p>%s</div>',
					esc_html__( 'Post Grid Master', 'ajax-filter-posts' ),
					esc_html__( 'Please select a grid from the widget settings panel.', 'ajax-filter-posts' ),
					$btn_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				);
			}
			return '';
		}

		$grid = Grid_Repository::get( $grid_id );
		if ( ! $grid ) {
			if ( $is_preview ) {
				return sprintf(
					'<div class="gm-builder-placeholder gm-builder-placeholder-error"><span class="dashicons dashicons-warning"></span><strong>%s</strong><p>%s</p></div>',
					sprintf( esc_html__( 'Grid #%d Not Found', 'ajax-filter-posts' ), $grid_id ),
					esc_html__( 'This grid may have been deleted or the ID is invalid.', 'ajax-filter-posts' )
				);
			}
			return '';
		}

		// Render the grid markup.
		$rendered = Grid_Renderer::render_grid( $grid_id );

		// In builder preview mode, provide an overlay container with an "Edit in GridMaster" badge for administrators.
		if ( $is_preview ) {
			$edit_url   = self::get_edit_url( $grid_id );
			$badge_html = '';
			if ( ! empty( $edit_url ) ) {
				$badge_html = sprintf(
					'<div class="gm-builder-edit-badge"><a href="%s" target="_blank" rel="noopener noreferrer" title="%s"><span class="dashicons dashicons-edit"></span> %s</a></div>',
					esc_url( $edit_url ),
					esc_attr__( 'Edit in GridMaster (opens in new tab)', 'ajax-filter-posts' ),
					esc_html__( 'Edit Grid', 'ajax-filter-posts' )
				);
			}

			return sprintf(
				'<div class="gm-builder-preview-wrapper" data-grid-id="%d">%s%s</div>',
				$grid_id,
				$badge_html, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$rendered // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			);
		}

		return $rendered;
	}

	/**
	 * Renders grid markup and collects stylesheet & script URLs needed to display it.
	 * Useful for Gutenberg iframe or headless rendering contexts.
	 *
	 * @param int $grid_id Grid ID.
	 * @return array
	 */
	public static function render_with_assets( $grid_id ) {
		global $wp_styles, $wp_scripts;

		$orig_styles  = is_a( $wp_styles, 'WP_Styles' ) ? $wp_styles->queue : array();
		$orig_scripts = is_a( $wp_scripts, 'WP_Scripts' ) ? $wp_scripts->queue : array();

		// Always ensure frontend assets are registered.
		Grid_Renderer::register_assets();

		$html = self::render( $grid_id );

		$new_styles = array();
		if ( is_a( $wp_styles, 'WP_Styles' ) ) {
			$diff = array_diff( $wp_styles->queue, $orig_styles );
			// Also include core frontend-v2 stylesheet.
			if ( ! in_array( 'gridmaster-frontend-v2', $diff, true ) && isset( $wp_styles->registered['gridmaster-frontend-v2'] ) ) {
				$diff[] = 'gridmaster-frontend-v2';
			}
			foreach ( $diff as $handle ) {
				if ( isset( $wp_styles->registered[ $handle ]->src ) && ! empty( $wp_styles->registered[ $handle ]->src ) ) {
					$new_styles[] = $wp_styles->registered[ $handle ]->src;
				}
			}
		}

		$new_scripts = array();
		if ( is_a( $wp_scripts, 'WP_Scripts' ) ) {
			$diff = array_diff( $wp_scripts->queue, $orig_scripts );
			if ( ! in_array( 'gridmaster-frontend-v2', $diff, true ) && isset( $wp_scripts->registered['gridmaster-frontend-v2'] ) ) {
				$diff[] = 'gridmaster-frontend-v2';
			}
			foreach ( $diff as $handle ) {
				if ( isset( $wp_scripts->registered[ $handle ]->src ) && ! empty( $wp_scripts->registered[ $handle ]->src ) ) {
					$new_scripts[] = $wp_scripts->registered[ $handle ]->src;
				}
			}
		}

		return array(
			'html'     => $html,
			'styles'   => array_values( array_unique( $new_styles ) ),
			'scripts'  => array_values( array_unique( $new_scripts ) ),
			'edit_url' => self::get_edit_url( $grid_id ),
		);
	}
}

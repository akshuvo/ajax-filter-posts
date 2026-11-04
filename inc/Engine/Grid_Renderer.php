<?php
namespace GridMaster\Engine;

use GridMaster\Storage\Grid_Repository;
use GridMaster\Facets\Filter_Registry;
use GridMaster\Pagination\Pagination_Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Server-Side Grid Rendering Engine for GridMaster 2.0.
 */
class Grid_Renderer {

	/**
	 * Renders a grid by numeric ID.
	 *
	 * @param int   $grid_id Grid ID.
	 * @param array $runtime_params Optional runtime params (page, active facets, search).
	 * @return string HTML output.
	 */
	public static function render_grid( $grid_id, $runtime_params = array() ) {
		static $rendering_grids = array();

		$grid_id = absint( $grid_id );
		if ( isset( $rendering_grids[ $grid_id ] ) ) {
			return '<!-- GridMaster: Recursion prevented for Grid #' . $grid_id . ' -->';
		}

		$grid = Grid_Repository::get( $grid_id );
		if ( ! $grid ) {
			return '<!-- GridMaster: Grid #' . $grid_id . ' not found -->';
		}

		// Backward compatibility: If rolled back to legacy status, route to legacy shortcode renderer.
		if ( 'legacy' === $grid->migration_status && ! empty( $grid->attributes ) ) {
			if ( class_exists( 'GridMaster\Shortcode' ) ) {
				$rendering_grids[ $grid_id ] = true;
				$shortcode = \GridMaster\Shortcode::init();
				ob_start();
				$shortcode->render_shortcode( array( 'id' => $grid_id ) );
				$output = ob_get_clean();
				unset( $rendering_grids[ $grid_id ] );
				return $output;
			}
		}

		$rendering_grids[ $grid_id ] = true;

		// Enqueue frontend runtime assets.
		self::enqueue_assets();

		$config        = ! empty( $grid->config ) ? $grid->config : Grid_Repository::default_config();
		$default_cfg   = Grid_Repository::default_config();
		if ( empty( $config['query'] ) ) {
			$config['query'] = $default_cfg['query'];
		}
		if ( empty( $config['layout'] ) ) {
			$config['layout'] = $default_cfg['layout'];
		}
		if ( empty( $config['pagination'] ) ) {
			$config['pagination'] = $default_cfg['pagination'];
		}
		$block_content = ! empty( $grid->block_content ) ? $grid->block_content : Grid_Repository::default_block_content();

		// Execute query.
		$query = Query_Builder::execute( $config['query'], $runtime_params );

		// Compile CSS custom properties for responsive columns, gaps, and styles.
		$css_vars = self::compile_css_vars( $config['layout'] );

		ob_start();
		?>
		<div class="gm-grid-wrapper gm-v2-grid" id="gm-grid-<?php echo esc_attr( $grid_id ); ?>" data-grid-id="<?php echo esc_attr( $grid_id ); ?>" style="<?php echo esc_attr( $css_vars ); ?>">
			<?php
			// Render Top Filters Area.
			if ( ! empty( $config['facets'] ) ) {
				echo Filter_Registry::render_facets( $config['facets'], $grid_id, $runtime_params ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			?>

			<div class="gm-grid-items" aria-live="polite">
				<?php
				if ( $query->have_posts() ) :
					$parsed_blocks = parse_blocks( $block_content );

					while ( $query->have_posts() ) :
						$query->the_post();
						global $post;

						$post_id   = get_the_ID();
						$post_type = get_post_type();
						?>
						<article class="gm-grid-item gm-post-<?php echo esc_attr( $post_id ); ?>" data-post-id="<?php echo esc_attr( $post_id ); ?>">
							<?php
							foreach ( $parsed_blocks as $parsed_block ) {
								echo self::render_block_with_context( $parsed_block, $post_id, $post_type, $grid_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							}
							?>
						</article>
						<?php
					endwhile;
					wp_reset_postdata();
				else :
					?>
					<div class="gm-no-results">
						<p><?php esc_html_e( 'No posts found matching the criteria.', 'ajax-filter-posts' ); ?></p>
					</div>
					<?php
				endif;
				?>
			</div>

			<?php
			// Render Pagination Area.
			$pagination_type = ! empty( $config['pagination']['type'] ) ? $config['pagination']['type'] : 'numbered';
			if ( 'none' !== $pagination_type && $query->max_num_pages > 1 ) {
				echo Pagination_Registry::render( $pagination_type, $query, $config['pagination'], $grid_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			?>
		</div>
		<?php
		unset( $rendering_grids[ $grid_id ] );
		return ob_get_clean();
	}

	/**
	 * Renders live repeated preview HTML for builder editing.
	 *
	 * @param array  $config Grid config.
	 * @param string $block_content Gutenberg card markup.
	 * @param int    $grid_id Optional grid ID.
	 * @param array  $runtime_params Optional runtime params (page, facets, search).
	 * @return string HTML output.
	 */
	public static function render_preview( $config, $block_content, $grid_id = 0, $runtime_params = array() ) {
		// Cap preview query to 6 posts for responsive performance.
		$preview_query_config = isset( $config['query'] ) ? $config['query'] : array();
		$posts_per_page = isset( $preview_query_config['posts_per_page'] ) ? absint( $preview_query_config['posts_per_page'] ) : 6;
		$preview_query_config['posts_per_page'] = min( 6, max( 1, $posts_per_page ) );

		$query    = Query_Builder::execute( $preview_query_config, $runtime_params );
		$css_vars = self::compile_css_vars( isset( $config['layout'] ) ? $config['layout'] : array() );

		ob_start();
		?>
		<div class="gm-grid-wrapper gm-v2-grid gm-preview-grid" data-grid-id="<?php echo esc_attr( $grid_id ); ?>" data-preview-mode="1" style="<?php echo esc_attr( $css_vars ); ?>">
			<?php
			if ( ! empty( $config['facets'] ) ) {
				echo Filter_Registry::render_facets( $config['facets'], $grid_id, $runtime_params, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			?>
			<div class="gm-grid-items">
				<?php
				if ( $query->have_posts() ) :
					$parsed_blocks = parse_blocks( $block_content );

					while ( $query->have_posts() ) :
						$query->the_post();
						global $post;

						$post_id   = get_the_ID();
						$post_type = get_post_type();
						?>
						<article class="gm-grid-item gm-post-<?php echo esc_attr( $post_id ); ?>">
							<?php
							foreach ( $parsed_blocks as $parsed_block ) {
								echo self::render_block_with_context( $parsed_block, $post_id, $post_type, $grid_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							}
							?>
						</article>
						<?php
					endwhile;
					wp_reset_postdata();
				else :
					// Real empty state when no posts match query.
					?>
					<div class="gm-empty-results" style="grid-column: 1 / -1; padding: 32px; text-align: center; color: #64748b;">
						<p><?php esc_html_e( 'No posts found matching the criteria.', 'ajax-filter-posts' ); ?></p>
					</div>
					<?php
				endif;
				?>
			</div>

			<?php
			// Render Pagination Area in preview if configured.
			$pagination_type = ! empty( $config['pagination']['type'] ) ? $config['pagination']['type'] : 'numbered';
			if ( 'none' !== $pagination_type && class_exists( 'GridMaster\Pagination\Pagination_Registry' ) && $query->max_num_pages > 1 ) {
				echo Pagination_Registry::render( $pagination_type, $query, $config['pagination'], $grid_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			?>
		</div>
		<?php
		unset( $rendering_grids[ $grid_id ] );
		return ob_get_clean();
	}

	/**
	 * Recursively renders a block while injecting post context.
	 *
	 * @param array  $parsed_block Parsed block structure.
	 * @param int    $post_id Post ID.
	 * @param string $post_type Post Type.
	 * @param int    $grid_id Grid ID.
	 * @return string HTML.
	 */
	public static function render_block_with_context( $parsed_block, $post_id, $post_type, $grid_id ) {
		if ( empty( $parsed_block['blockName'] ) && empty( $parsed_block['innerBlocks'] ) ) {
			return $parsed_block['innerHTML'];
		}

		$context = array(
			'postId'            => $post_id,
			'postType'          => $post_type,
			'gridmaster/gridId' => $grid_id,
		);

		$block = new \WP_Block( $parsed_block, $context );
		return $block->render();
	}

	/**
	 * Compiles layout configuration into CSS custom properties.
	 *
	 * @param array $layout Layout config array.
	 * @return string CSS string.
	 */
	public static function compile_css_vars( $layout = array() ) {
		$cols = isset( $layout['columns'] ) ? $layout['columns'] : array();
		$xs   = isset( $cols['xs'] ) ? absint( $cols['xs'] ) : 1;
		$sm   = isset( $cols['sm'] ) ? absint( $cols['sm'] ) : 2;
		$md   = isset( $cols['md'] ) ? absint( $cols['md'] ) : 3;
		$lg   = isset( $cols['lg'] ) ? absint( $cols['lg'] ) : 3;
		$xl   = isset( $cols['xl'] ) ? absint( $cols['xl'] ) : 3;

		$col_gap = isset( $layout['col_gap'] ) && '' !== $layout['col_gap'] ? absint( $layout['col_gap'] ) : 30;
		$row_gap = isset( $layout['row_gap'] ) && '' !== $layout['row_gap'] ? absint( $layout['row_gap'] ) : 30;

		return sprintf(
			'--gm-cols-xs:%d;--gm-cols-sm:%d;--gm-cols-md:%d;--gm-cols-lg:%d;--gm-cols-xl:%d;--gm-col-gap:%dpx;--gm-row-gap:%dpx;',
			$xs,
			$sm,
			$md,
			$lg,
			$xl,
			$col_gap,
			$row_gap
		);
	}

	/**
	 * Enqueues frontend scripts and stylesheets.
	 */
	public static function enqueue_assets() {
		wp_enqueue_script( 'gridmaster-frontend-v2', GRIDMASTER_ASSETS . 'js/frontend-v2.js', array(), GRIDMASTER_VERSION, true );
		wp_localize_script(
			'gridmaster-frontend-v2',
			'gridmasterV2',
			array(
				'restUrl' => esc_url_raw( rest_url( 'gridmaster/v2/' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			)
		);

		wp_enqueue_style( 'gridmaster-frontend-v2', GRIDMASTER_ASSETS . 'css/frontend-v2.css', array(), GRIDMASTER_VERSION );
	}
}

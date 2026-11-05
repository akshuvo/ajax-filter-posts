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

		$layout_type = ! empty( $config['layout']['type'] ) ? $config['layout']['type'] : 'grid';
		$is_slider   = 'slider' === $layout_type || 'carousel' === $layout_type;
		$slider_cfg  = ! empty( $config['layout']['slider'] ) ? $config['layout']['slider'] : array();

		$wrapper_classes = array( 'gm-grid-wrapper', 'gm-v2-grid' );
		$wrapper_attrs   = array(
			'id'           => 'gm-grid-' . $grid_id,
			'data-grid-id' => (string) $grid_id,
			'style'        => $css_vars,
		);

		if ( $is_slider ) {
			$wrapper_classes[]                    = 'gm-layout-slider';
			$wrapper_attrs['data-layout']        = 'slider';
			$wrapper_attrs['data-slider-config'] = wp_json_encode( $slider_cfg );
			$wrapper_attrs['role']                = 'region';
			$wrapper_attrs['aria-roledescription'] = 'carousel';
			$wrapper_attrs['aria-label']          = ! empty( $grid->title ) ? $grid->title : __( 'Post Carousel', 'ajax-filter-posts' );
		}

		$attrs_rendered = '';
		foreach ( $wrapper_attrs as $attr_k => $attr_v ) {
			$attrs_rendered .= ' ' . esc_attr( $attr_k ) . '="' . esc_attr( $attr_v ) . '"';
		}

		ob_start();
		?>
		<div class="<?php echo esc_attr( implode( ' ', $wrapper_classes ) ); ?>"<?php echo $attrs_rendered; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php
			// Render Top Filters Area.
			if ( ! empty( $config['facets'] ) ) {
				echo Filter_Registry::render_facets( $config['facets'], $grid_id, $runtime_params ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}

			echo self::render_grid_items_markup( $query, $block_content, $config['layout'], $grid_id, ! empty( $grid->title ) ? $grid->title : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			// Render Pagination Area (only for standard grid).
			if ( ! $is_slider ) {
				$pagination_type = ! empty( $config['pagination']['type'] ) ? $config['pagination']['type'] : 'numbered';
				if ( 'none' !== $pagination_type && $query->max_num_pages > 1 ) {
					echo Pagination_Registry::render( $pagination_type, $query, $config['pagination'], $grid_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
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
		$layout   = isset( $config['layout'] ) ? $config['layout'] : array();
		$css_vars = self::compile_css_vars( $layout );

		$layout_type = ! empty( $layout['type'] ) ? $layout['type'] : 'grid';
		$is_slider   = 'slider' === $layout_type || 'carousel' === $layout_type;
		$slider_cfg  = ! empty( $layout['slider'] ) ? $layout['slider'] : array();

		$wrapper_classes = array( 'gm-grid-wrapper', 'gm-v2-grid', 'gm-preview-grid' );
		$wrapper_attrs   = array(
			'data-grid-id'      => (string) $grid_id,
			'data-preview-mode' => '1',
			'style'             => $css_vars,
		);

		if ( $is_slider ) {
			$wrapper_classes[]                    = 'gm-layout-slider';
			$wrapper_attrs['data-layout']        = 'slider';
			$wrapper_attrs['data-slider-config'] = wp_json_encode( $slider_cfg );
			$wrapper_attrs['role']                = 'region';
			$wrapper_attrs['aria-roledescription'] = 'carousel';
			$wrapper_attrs['aria-label']          = __( 'Live Carousel Preview', 'ajax-filter-posts' );
		}

		$attrs_rendered = '';
		foreach ( $wrapper_attrs as $attr_k => $attr_v ) {
			$attrs_rendered .= ' ' . esc_attr( $attr_k ) . '="' . esc_attr( $attr_v ) . '"';
		}

		ob_start();
		?>
		<div class="<?php echo esc_attr( implode( ' ', $wrapper_classes ) ); ?>"<?php echo $attrs_rendered; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php
			if ( ! empty( $config['facets'] ) ) {
				echo Filter_Registry::render_facets( $config['facets'], $grid_id, $runtime_params, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}

			echo self::render_grid_items_markup( $query, $block_content, $layout, $grid_id, __( 'Preview Carousel', 'ajax-filter-posts' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			// Render Pagination Area in preview if configured (only for grid).
			if ( ! $is_slider ) {
				$pagination_type = ! empty( $config['pagination']['type'] ) ? $config['pagination']['type'] : 'numbered';
				if ( 'none' !== $pagination_type && class_exists( 'GridMaster\Pagination\Pagination_Registry' ) && $query->max_num_pages > 1 ) {
					echo Pagination_Registry::render( $pagination_type, $query, $config['pagination'], $grid_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
			}
			?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Renders grid items or accessible slider track markup.
	 *
	 * @param \WP_Query $query Query instance.
	 * @param string    $block_content Gutenberg card markup.
	 * @param array     $layout Layout configuration.
	 * @param int       $grid_id Grid ID.
	 * @param string    $grid_title Optional grid title for ARIA label.
	 * @return string HTML output.
	 */
	public static function render_grid_items_markup( $query, $block_content, $layout, $grid_id, $grid_title = '' ) {
		$layout_type = ! empty( $layout['type'] ) ? $layout['type'] : 'grid';
		$is_slider   = 'slider' === $layout_type || 'carousel' === $layout_type;
		$slider_cfg  = ! empty( $layout['slider'] ) ? $layout['slider'] : array();

		$show_arrows = ! isset( $slider_cfg['arrows'] ) || ! empty( $slider_cfg['arrows'] );
		$show_dots   = ! isset( $slider_cfg['dots'] ) || ! empty( $slider_cfg['dots'] );
		$autoplay    = ! empty( $slider_cfg['autoplay'] );

		ob_start();

		if ( ! $query->have_posts() ) {
			?>
			<div class="gm-no-results gm-empty-results" style="grid-column: 1 / -1; padding: 32px; text-align: center; color: #64748b;">
				<p><?php esc_html_e( 'No posts found matching the criteria.', 'ajax-filter-posts' ); ?></p>
			</div>
			<?php
			return ob_get_clean();
		}

		$parsed_blocks = parse_blocks( $block_content );
		$total_posts   = $query->post_count;
		$slide_index   = 0;

		if ( $is_slider ) {
			?>
			<p class="screen-reader-text sr-only"><?php esc_html_e( 'This is a carousel with post cards. Use the previous and next buttons or arrow keys to navigate.', 'ajax-filter-posts' ); ?></p>
			<div class="gm-slider-viewport">
				<?php if ( $show_arrows ) : ?>
					<button class="gm-slider-btn gm-slider-prev" type="button" aria-label="<?php esc_attr_e( 'Previous cards', 'ajax-filter-posts' ); ?>">
						<span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
						<span class="screen-reader-text"><?php esc_html_e( 'Previous cards', 'ajax-filter-posts' ); ?></span>
					</button>
				<?php endif; ?>

				<div class="gm-grid-items gm-slider-track" role="group" aria-label="<?php esc_attr_e( 'Carousel slides', 'ajax-filter-posts' ); ?>" tabindex="0">
					<?php
					while ( $query->have_posts() ) :
						$query->the_post();
						global $post;
						$post_id   = get_the_ID();
						$post_type = get_post_type();
						$slide_index++;
						?>
						<article class="gm-grid-item gm-slider-slide gm-post-<?php echo esc_attr( $post_id ); ?>" data-post-id="<?php echo esc_attr( $post_id ); ?>" role="group" aria-roledescription="slide" aria-label="<?php echo esc_attr( sprintf( __( 'card %1$d of %2$d', 'ajax-filter-posts' ), $slide_index, $total_posts ) ); ?>">
							<?php
							foreach ( $parsed_blocks as $parsed_block ) {
								echo self::render_block_with_context( $parsed_block, $post_id, $post_type, $grid_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							}
							?>
						</article>
						<?php
					endwhile;
					wp_reset_postdata();
					?>
				</div>

				<?php if ( $show_arrows ) : ?>
					<button class="gm-slider-btn gm-slider-next" type="button" aria-label="<?php esc_attr_e( 'Next cards', 'ajax-filter-posts' ); ?>">
						<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
						<span class="screen-reader-text"><?php esc_html_e( 'Next cards', 'ajax-filter-posts' ); ?></span>
					</button>
				<?php endif; ?>
			</div><!-- /.gm-slider-viewport -->

			<?php if ( $show_dots ) : ?>
				<div class="gm-slider-dots" role="tablist" aria-label="<?php esc_attr_e( 'Slide selection', 'ajax-filter-posts' ); ?>"></div>
			<?php endif; ?>

			<?php if ( $autoplay ) : ?>
				<div class="gm-slider-autoplay-control">
					<button class="gm-slider-playpause" type="button" aria-label="<?php esc_attr_e( 'Pause carousel autoplay', 'ajax-filter-posts' ); ?>">
						<span class="dashicons dashicons-controls-pause" aria-hidden="true"></span>
						<span class="screen-reader-text"><?php esc_html_e( 'Pause autoplay', 'ajax-filter-posts' ); ?></span>
					</button>
				</div>
			<?php endif; ?>
			<?php
		} else {
			?>
			<div class="gm-grid-items" aria-live="polite">
				<?php
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
				?>
			</div>
			<?php
		}

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

		// Gracefully omit Pro blocks if Pro is not active
		if ( ! empty( $parsed_block['blockName'] ) && 0 === strpos( $parsed_block['blockName'], 'gridmaster-pro/' ) && ! class_exists( 'GridMasterPro\Blocks\Pro_Blocks' ) ) {
			return '';
		}

		// Contextual evaluation for core/shortcode block
		if ( 'core/shortcode' === $parsed_block['blockName'] ) {
			$raw = ! empty( $parsed_block['attrs']['text'] ) ? $parsed_block['attrs']['text'] : ( ! empty( $parsed_block['innerHTML'] ) ? strip_tags( $parsed_block['innerHTML'] ) : '' );
			if ( empty( $raw ) ) {
				return '';
			}
			$raw = str_replace( array( '{post_id}', '{id}' ), array( $post_id, $post_id ), $raw );
			global $post;
			$orig_post = $post;
			$post      = get_post( $post_id );
			if ( $post ) {
				setup_postdata( $post );
			}
			$output = do_shortcode( $raw );
			if ( $orig_post ) {
				$post = $orig_post;
				setup_postdata( $post );
			} else {
				wp_reset_postdata();
			}
			return '<div class="gm-field gm-field-shortcode">' . $output . '</div>';
		}

		// Contextual evaluation for core/html block
		if ( 'core/html' === $parsed_block['blockName'] ) {
			$raw = ! empty( $parsed_block['innerHTML'] ) ? $parsed_block['innerHTML'] : ( ! empty( $parsed_block['attrs']['content'] ) ? $parsed_block['attrs']['content'] : '' );
			if ( empty( $raw ) ) {
				return '';
			}
			$rendered = str_replace(
				array( '{post_id}', '{id}', '{title}', '{permalink}' ),
				array( $post_id, $post_id, esc_html( get_the_title( $post_id ) ), esc_url( get_permalink( $post_id ) ) ),
				$raw
			);
			return '<div class="gm-field gm-field-html">' . $rendered . '</div>';
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

		$slider_slides = isset( $layout['slider']['slides_to_show'] ) ? $layout['slider']['slides_to_show'] : $cols;
		$s_xs   = isset( $slider_slides['xs'] ) ? absint( $slider_slides['xs'] ) : $xs;
		$s_sm   = isset( $slider_slides['sm'] ) ? absint( $slider_slides['sm'] ) : $sm;
		$s_md   = isset( $slider_slides['md'] ) ? absint( $slider_slides['md'] ) : $md;
		$s_lg   = isset( $slider_slides['lg'] ) ? absint( $slider_slides['lg'] ) : $lg;
		$s_xl   = isset( $slider_slides['xl'] ) ? absint( $slider_slides['xl'] ) : $xl;

		return sprintf(
			'--gm-cols-xs:%d;--gm-cols-sm:%d;--gm-cols-md:%d;--gm-cols-lg:%d;--gm-cols-xl:%d;--gm-slides-xs:%d;--gm-slides-sm:%d;--gm-slides-md:%d;--gm-slides-lg:%d;--gm-slides-xl:%d;--gm-col-gap:%dpx;--gm-row-gap:%dpx;',
			$xs,
			$sm,
			$md,
			$lg,
			$xl,
			$s_xs,
			$s_sm,
			$s_md,
			$s_lg,
			$s_xl,
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
					'errorText' => __( 'Could not load posts. Please retry.', 'ajax-filter-posts' ),
					'retryText' => __( 'Retry', 'ajax-filter-posts' ),
			)
		);

		wp_enqueue_style( 'gridmaster-frontend-v2', GRIDMASTER_ASSETS . 'css/frontend-v2.css', array( 'dashicons' ), GRIDMASTER_VERSION );
	}
}

<?php
namespace GridMaster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Renderer {

	public static function defaults() {
		return array(
			'template_id'        => 0,
			'templateId'         => 0,
			'post_type'          => 'post',
			'postType'           => 'post',
			'posts_per_page'     => 9,
			'postsPerPage'       => 9,
			'orderby'            => 'date',
			'order'              => 'DESC',
			'taxonomy'           => 'category',
			'terms'              => '',
			'show_filter'        => 'yes',
			'showFilter'         => true,
			'btn_all'            => 'yes',
			'hide_empty'         => 0,
			'filter_heading'     => '',
			'toggle_filter_items'=> 'no',
			'filter_style'       => 'default',
			'filterStyle'        => '',
			'gridRef'            => '',
			'legacy_grid_id'     => 0,
			'gridId'             => 0,
			'pagination_type'    => '',
			'paginationType'     => '',
			'infinite_scroll'    => '',
			'infiniteScroll'     => false,
			'animation'          => '',
			'grid_id'            => '',
			'grid_style'         => 'gutenberg',
			'grid_image_size'    => 'full',
			'excerpt_length'     => 15,
			'excerpt_type'       => 'words',
			'content_from'       => 'excerpt',
			'show_read_more'     => 'yes',
			'read_more_text'     => '',
			'responsiveColumns'  => array(
				'xs' => 1,
				'sm' => 2,
				'md' => 3,
				'lg' => 3,
				'xl' => 3,
			),
			'rowGap'             => 30,
			'columnGap'          => 30,
		);
	}

	public static function normalize_args( $args = array() ) {
		$args = wp_parse_args( $args, self::defaults() );

		$args['template_id']     = absint( $args['template_id'] ? $args['template_id'] : $args['templateId'] );
		$args['post_type']       = sanitize_key( $args['post_type'] ? $args['post_type'] : $args['postType'] );
		$args['posts_per_page']  = intval( $args['posts_per_page'] ? $args['posts_per_page'] : $args['postsPerPage'] );
		$args['pagination_type'] = sanitize_text_field( $args['pagination_type'] ? $args['pagination_type'] : $args['paginationType'] );
		$args['filter_style']    = sanitize_text_field( $args['filterStyle'] ? $args['filterStyle'] : $args['filter_style'] );
		$args['show_filter']     = ( isset( $args['showFilter'] ) && false === $args['showFilter'] ) ? 'no' : $args['show_filter'];
		$args['infinite_scroll'] = ( ! empty( $args['infiniteScroll'] ) || 'true' === $args['infinite_scroll'] ) ? 'true' : $args['infinite_scroll'];

		if ( ! empty( $args['gridRef'] ) ) {
			$grid_ref = sanitize_text_field( $args['gridRef'] );
			if ( 0 === strpos( $grid_ref, 'template:' ) ) {
				$args['template_id'] = absint( substr( $grid_ref, 9 ) );
			} elseif ( 0 === strpos( $grid_ref, 'legacy:' ) ) {
				$args['legacy_grid_id'] = absint( substr( $grid_ref, 7 ) );
			}
		}

		$args['legacy_grid_id'] = absint( $args['legacy_grid_id'] ? $args['legacy_grid_id'] : $args['gridId'] );

		if ( empty( $args['grid_id'] ) ) {
			$args['grid_id'] = 'gm-block-' . wp_generate_password( 8, false );
		}

		return apply_filters( 'gridmaster_gutenberg_render_args', $args );
	}

	public static function render_builder_selection( $args = array() ) {
		$args = self::normalize_args( $args );

		if ( ! empty( $args['legacy_grid_id'] ) ) {
			return Shortcode::init()->render_shortcode(
				array(
					'id'           => absint( $args['legacy_grid_id'] ),
					'filter_style' => $args['filter_style'],
				)
			);
		}

		return self::render( $args );
	}

	public static function render( $args = array() ) {
		$args = self::normalize_args( $args );

		if ( ! $args['template_id'] || ! Template_Renderer::is_template( $args['template_id'] ) ) {
			if ( current_user_can( 'edit_posts' ) ) {
				return '<div class="gm-admin-notice">' . esc_html__( 'Select a valid GridMaster Gutenberg template.', 'ajax-filter-posts' ) . '</div>';
			}
			return '';
		}

		$public_atts = self::public_attributes( $args );
		$classes     = array(
			'am_ajax_post_grid_wrap',
			'gm-gutenberg-grid',
			'gridmaster-gutenberg',
			$args['grid_id'],
		);

		$style = self::inline_grid_style( $args );

		ob_start();
		?>
		<div id="<?php echo esc_attr( $args['grid_id'] ); ?>"
			class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
			data-grid-style="gutenberg"
			data-pagination_type="<?php echo esc_attr( $args['pagination_type'] ); ?>"
			data-am_ajax_post_grid="<?php echo esc_attr( wp_json_encode( $public_atts ) ); ?>"
			<?php echo $style ? 'style="' . esc_attr( $style ) . '"' : ''; ?>>
			<?php echo self::render_filter( $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<div class="asr-ajax-container">
				<div class="asr-loader"><div class="lds-dual-ring"></div></div>
				<div class="asrafp-filter-result">
					<?php echo self::render_posts( $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			</div>
		</div>
		<?php
		return apply_filters( 'gridmaster_gutenberg_output', ob_get_clean(), $args );
	}

	public static function render_posts( $args = array() ) {
		$args = self::normalize_args( $args );

		add_filter(
			'gridmaster_excerpt_length',
			function ( $length ) use ( $args ) {
				return intval( $args['excerpt_length'] );
			}
		);

		add_filter(
			'gridmaster_post_thumb_size',
			function ( $thumb_size ) use ( $args ) {
				return sanitize_text_field( $args['grid_image_size'] );
			}
		);

		$args = apply_filters( 'gridmaster_render_grid_args', $args );

		add_filter(
			'gridmaster_get_render_grid_args',
			function ( $arguments ) use ( $args ) {
				return $args;
			}
		);

		$query_args = Query_Builder::build( $args );
		$query      = new \WP_Query( $query_args );
		$paged      = isset( $args['paged'] ) ? max( 1, absint( $args['paged'] ) ) : 1;

		ob_start();
		echo ( 'load_more' === $args['pagination_type'] ) ? '<div class="am-postgrid-wrapper">' : '';

		if ( $query->have_posts() ) :
			?>
			<div class="<?php echo esc_attr( 'am_post_grid gm-block-post-grid ' . $args['animation'] ); ?>">
				<?php
				while ( $query->have_posts() ) :
					$query->the_post();
					?>
					<div class="am_grid_col gm-block-grid-col">
						<?php echo Template_Renderer::render_post( $args['template_id'], $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
					<?php
				endwhile;
				?>
			</div>
			<?php echo self::render_pagination( $query, $args, $paged ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php
		else :
			echo '<div class="gm-no-posts-found">' . apply_filters( 'gridmaster-no-posts-found', esc_html__( 'No posts found', 'ajax-filter-posts' ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		endif;

		wp_reset_postdata();
		echo ( 'load_more' === $args['pagination_type'] ) ? '</div>' : '';

		return apply_filters( 'gridmaster_gutenberg_posts_output', ob_get_clean(), $args, $query );
	}

	private static function render_pagination( $query, $args, $paged ) {
		$big        = 999999999;
		$next       = $paged + 1;
		$pagination = paginate_links(
			array(
				'base'      => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
				'format'    => '?paged=%#%',
				'current'   => max( 1, $paged ),
				'prev_next' => true,
				'mid_size'  => 2,
				'total'     => $query->max_num_pages,
				'type'      => 'plain',
				'prev_text' => '«',
				'next_text' => '»',
			)
		);

		ob_start();
		?>
		<div class="am_posts_navigation">
			<?php if ( 'load_more' === $args['pagination_type'] ) : ?>
				<?php if ( $pagination && $paged < $query->max_num_pages ) : ?>
					<button type="button" data-paged="<?php echo esc_attr( $paged ); ?>" data-next="<?php echo esc_attr( $next ); ?>" class="<?php echo esc_attr( $args['infinite_scroll'] . ' am-post-grid-load-more' ); ?>"><?php esc_html_e( 'Load More', 'ajax-filter-posts' ); ?></button>
				<?php endif; ?>
			<?php else : ?>
				<div class="am_posts_navigation_init"><?php echo $pagination; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function render_filter( $args ) {
		if ( 'yes' !== $args['show_filter'] ) {
			return '';
		}

		$include = ! empty( $args['terms'] ) ? $args['terms'] : '';

		$filter_args = array(
			'tax_args'     => array(
				'hide_empty' => $args['hide_empty'],
				'taxonomy'   => $args['taxonomy'],
				'include'    => $include,
			),
			'grid_id'      => $args['grid_id'],
			'btn_all'      => $args['btn_all'],
			'filter_style' => $args['filter_style'],
			'input_type'   => apply_filters( 'gridmaster_filter_input_type', 'radio', $args ),
		);

		ob_start();
		?>
		<div class="asr-filter-div">
			<div class="gm-filter-wrap <?php echo esc_attr( 'gm-filter-toggle-' . sanitize_text_field( $args['toggle_filter_items'] ) ); ?>">
				<?php if ( ! empty( $args['filter_heading'] ) ) : ?>
					<div class="gm-filter-heading">
						<span><?php echo esc_html( $args['filter_heading'] ); ?></span>
						<?php if ( 'yes' === $args['toggle_filter_items'] ) : ?>
							<span class="gm-filter-caret"><svg aria-hidden="true" focusable="false" class="icon icon-caret" viewBox="0 0 10 6"><path fill-rule="evenodd" clip-rule="evenodd" d="M9.354.646a.5.5 0 00-.708 0L5 4.293 1.354.646a.5.5 0 00-.708.708l4 4a.5.5 0 00.708 0l4-4a.5.5 0 000-.708z" fill="currentColor"></path></svg></span>
						<?php endif; ?>
					</div>
				<?php endif; ?>
				<div class="gm-single-filter">
					<?php Shortcode::init()->get_template_part( $args['filter_style'], 'filter', $filter_args ); ?>
				</div>
			</div>
		</div>
		<?php
		return apply_filters( 'gridmaster_gutenberg_filter_output', ob_get_clean(), $args );
	}

	private static function public_attributes( $args ) {
		$public = array(
			'template_id'      => absint( $args['template_id'] ),
			'post_type'        => $args['post_type'],
			'posts_per_page'   => intval( $args['posts_per_page'] ),
			'orderby'          => $args['orderby'],
			'order'            => $args['order'],
			'taxonomy'         => $args['taxonomy'],
			'terms'            => $args['terms'],
			'show_filter'      => $args['show_filter'],
			'btn_all'          => $args['btn_all'],
			'hide_empty'       => $args['hide_empty'],
			'filter_heading'   => $args['filter_heading'],
			'filter_style'     => $args['filter_style'],
			'pagination_type'  => $args['pagination_type'],
			'infinite_scroll'  => $args['infinite_scroll'],
			'animation'        => $args['animation'],
			'grid_id'          => $args['grid_id'],
			'grid_image_size'  => $args['grid_image_size'],
			'excerpt_length'   => $args['excerpt_length'],
			'excerpt_type'     => $args['excerpt_type'],
			'content_from'     => $args['content_from'],
			'show_read_more'   => $args['show_read_more'],
			'read_more_text'   => $args['read_more_text'],
		);

		return apply_filters( 'gridmaster_gutenberg_public_atts', $public, $args );
	}

	private static function inline_grid_style( $args ) {
		$columns = isset( $args['responsiveColumns']['lg'] ) ? absint( $args['responsiveColumns']['lg'] ) : 3;
		$row_gap = is_array( $args['rowGap'] ) ? absint( $args['rowGap']['lg'] ) : absint( $args['rowGap'] );
		$col_gap = is_array( $args['columnGap'] ) ? absint( $args['columnGap']['lg'] ) : absint( $args['columnGap'] );

		return '--gm-grid-columns:' . max( 1, $columns ) . ';--gm-grid-row-gap:' . $row_gap . 'px;--gm-grid-column-gap:' . $col_gap . 'px;';
	}
}

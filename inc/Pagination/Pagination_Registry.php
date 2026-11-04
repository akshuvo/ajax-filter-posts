<?php
namespace GridMaster\Pagination;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles rendering for various pagination mechanisms in GridMaster 2.0.
 */
class Pagination_Registry {

	/**
	 * Renders pagination markup according to type.
	 *
	 * @param string    $type Pagination type.
	 * @param \WP_Query $query The active WP_Query instance.
	 * @param array     $config Pagination config.
	 * @param int       $grid_id Grid ID.
	 * @return string HTML.
	 */
	public static function render( $type, $query, $config = array(), $grid_id = 0 ) {
		$max_pages = $query->max_num_pages;
		if ( $max_pages <= 1 ) {
			return '';
		}

		$current_page = max( 1, $query->get( 'paged' ) ? (int) $query->get( 'paged' ) : 1 );

		switch ( $type ) {
			case 'numbered':
				return self::render_numbered( $current_page, $max_pages, $grid_id );

			case 'load_more':
				return self::render_load_more( $current_page, $max_pages, $config, $grid_id );

			case 'infinite':
				return self::render_infinite( $current_page, $max_pages, $config, $grid_id );

			case 'prev_next':
				return self::render_prev_next( $current_page, $max_pages, $grid_id );

			default:
				return apply_filters( 'gridmaster_render_custom_pagination', '', $type, $query, $config, $grid_id );
		}
	}

	/**
	 * Renders numbered pagination links.
	 */
	private static function render_numbered( $current, $max, $grid_id ) {
		ob_start();
		?>
		<nav class="gm-pagination gm-pagination-numbered" role="navigation" aria-label="<?php esc_attr_e( 'Grid Pagination', 'ajax-filter-posts' ); ?>">
			<?php if ( $current > 1 ) : ?>
				<button type="button" class="gm-page-btn gm-prev-btn" data-page="<?php echo esc_attr( $current - 1 ); ?>" aria-label="<?php esc_attr_e( 'Previous Page', 'ajax-filter-posts' ); ?>">&laquo; <?php esc_html_e( 'Prev', 'ajax-filter-posts' ); ?></button>
			<?php endif; ?>

			<?php for ( $i = 1; $i <= $max; $i++ ) :
				$is_current = ( $i === $current );
				?>
				<button type="button" class="gm-page-btn<?php echo $is_current ? ' gm-current' : ''; ?>" data-page="<?php echo esc_attr( $i ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Page %d', 'ajax-filter-posts' ), $i ) ); ?>" <?php echo $is_current ? 'aria-current="page"' : ''; ?>>
					<?php echo esc_html( $i ); ?>
				</button>
			<?php endfor; ?>

			<?php if ( $current < $max ) : ?>
				<button type="button" class="gm-page-btn gm-next-btn" data-page="<?php echo esc_attr( $current + 1 ); ?>" aria-label="<?php esc_attr_e( 'Next Page', 'ajax-filter-posts' ); ?>"><?php esc_html_e( 'Next', 'ajax-filter-posts' ); ?> &raquo;</button>
			<?php endif; ?>
		</nav>
		<?php
		return ob_get_clean();
	}

	/**
	 * Renders Load More button.
	 */
	private static function render_load_more( $current, $max, $config, $grid_id ) {
		$text    = ! empty( $config['load_more_text'] ) ? sanitize_text_field( $config['load_more_text'] ) : __( 'Load More', 'ajax-filter-posts' );
		$loading = ! empty( $config['loading_text'] ) ? sanitize_text_field( $config['loading_text'] ) : __( 'Loading...', 'ajax-filter-posts' );

		ob_start();
		?>
		<div class="gm-pagination gm-pagination-load-more" data-current-page="<?php echo esc_attr( $current ); ?>" data-max-pages="<?php echo esc_attr( $max ); ?>">
			<?php if ( $current < $max ) : ?>
				<button type="button" class="gm-btn gm-btn-load-more" data-page="<?php echo esc_attr( $current + 1 ); ?>" data-loading-text="<?php echo esc_attr( $loading ); ?>">
					<span class="gm-btn-text"><?php echo esc_html( $text ); ?></span>
					<span class="gm-spinner" aria-hidden="true"></span>
				</button>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Renders Infinite Scroll observer trigger.
	 */
	private static function render_infinite( $current, $max, $config, $grid_id ) {
		$loading = ! empty( $config['loading_text'] ) ? sanitize_text_field( $config['loading_text'] ) : __( 'Loading more posts...', 'ajax-filter-posts' );

		ob_start();
		?>
		<div class="gm-pagination gm-pagination-infinite" data-current-page="<?php echo esc_attr( $current ); ?>" data-max-pages="<?php echo esc_attr( $max ); ?>" data-page="<?php echo esc_attr( $current + 1 ); ?>">
			<div class="gm-infinite-spinner" aria-live="polite">
				<span class="gm-spinner" aria-hidden="true"></span>
				<span class="gm-spinner-text"><?php echo esc_html( $loading ); ?></span>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Renders Prev / Next links.
	 */
	private static function render_prev_next( $current, $max, $grid_id ) {
		ob_start();
		?>
		<nav class="gm-pagination gm-pagination-prev-next" role="navigation">
			<?php if ( $current > 1 ) : ?>
				<button type="button" class="gm-page-btn gm-prev-btn" data-page="<?php echo esc_attr( $current - 1 ); ?>">&laquo; <?php esc_html_e( 'Previous', 'ajax-filter-posts' ); ?></button>
			<?php endif; ?>
			<?php if ( $current < $max ) : ?>
				<button type="button" class="gm-page-btn gm-next-btn" data-page="<?php echo esc_attr( $current + 1 ); ?>"><?php esc_html_e( 'Next', 'ajax-filter-posts' ); ?> &raquo;</button>
			<?php endif; ?>
		</nav>
		<?php
		return ob_get_clean();
	}
}

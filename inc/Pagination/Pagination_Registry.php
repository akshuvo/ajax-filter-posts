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
	 * Builds the ordered sequence of page numbers and ellipsis markers for numbered pagination.
	 *
	 * @param int $current Current page number (1-indexed).
	 * @param int $max     Total pages.
	 * @return array<int|string> Sequence containing integers and '...' strings.
	 */
	public static function build_page_sequence( $current, $max ) {
		$current = max( 1, (int) $current );
		$max     = max( 1, (int) $max );

		if ( $max <= 7 ) {
			return range( 1, $max );
		}

		$candidates = array( 1, $max );

		$win_start = max( 1, $current - 1 );
		$win_end   = min( $max, $current + 1 );
		for ( $i = $win_start; $i <= $win_end; $i++ ) {
			$candidates[] = $i;
		}
		if ( $current <= 3 ) {
			$candidates[] = 2;
			$candidates[] = 3;
			$candidates[] = 4;
		}
		if ( $current >= $max - 2 ) {
			$candidates[] = $max - 1;
			$candidates[] = $max - 2;
			$candidates[] = $max - 3;
		}

		$candidates = array_values( array_unique( array_filter( $candidates, function ( $p ) use ( $max ) {
			return $p >= 1 && $p <= $max;
		} ) ) );
		sort( $candidates );

		$sequence = array();
		$prev     = 0;
		foreach ( $candidates as $p ) {
			if ( $prev > 0 ) {
				$gap = $p - $prev;
				if ( 2 === $gap ) {
					$sequence[] = $prev + 1;
				} elseif ( $gap > 2 ) {
					$sequence[] = '...';
				}
			}
			$sequence[] = $p;
			$prev       = $p;
		}

		return $sequence;
	}

	/**
	 * Renders numbered pagination links with ellipsis truncation for large page counts.
	 */
	private static function render_numbered( $current, $max, $grid_id ) {
		$sequence = self::build_page_sequence( $current, $max );
		ob_start();
		?>
		<nav class="gm-pagination gm-pagination-numbered" role="navigation" aria-label="<?php esc_attr_e( 'Grid Pagination', 'ajax-filter-posts' ); ?>">
			<?php if ( $current > 1 ) : ?>
				<button type="button" class="gm-page-btn gm-prev-btn" data-page="<?php echo esc_attr( $current - 1 ); ?>" aria-label="<?php esc_attr_e( 'Previous Page', 'ajax-filter-posts' ); ?>">&laquo; <?php esc_html_e( 'Prev', 'ajax-filter-posts' ); ?></button>
			<?php endif; ?>

			<?php foreach ( $sequence as $item ) : ?>
				<?php if ( '...' === $item ) : ?>
					<span class="gm-page-ellipsis" aria-hidden="true">&hellip;</span>
				<?php else :
					$is_current = ( (int) $item === (int) $current );
					?>
					<button type="button" class="gm-page-btn<?php echo $is_current ? ' gm-current' : ''; ?>" data-page="<?php echo esc_attr( $item ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Page %d', 'ajax-filter-posts' ), $item ) ); ?>" <?php echo $is_current ? 'aria-current="page"' : ''; ?>>
						<?php echo esc_html( $item ); ?>
					</button>
				<?php endif; ?>
			<?php endforeach; ?>

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
		if ( $current >= $max ) {
			return '';
		}
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

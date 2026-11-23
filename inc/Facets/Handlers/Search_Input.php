<?php
namespace GridMaster\Facets\Handlers;

use GridMaster\Facets\Facet_Handler_Interface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keyword Search Input Filter Handler.
 */
class Search_Input implements Facet_Handler_Interface {

	public static function get_label() {
		return __( 'Keyword Search Input', 'ajax-filter-posts' );
	}

	public static function render( $facet, $grid_id, $runtime_params = array(), $is_preview = false ) {
		$placeholder = ! empty( $facet['placeholder'] ) ? sanitize_text_field( $facet['placeholder'] ) : __( 'Search posts...', 'ajax-filter-posts' );
		$search_val  = ! empty( $runtime_params['search'] ) ? sanitize_text_field( $runtime_params['search'] ) : '';

		ob_start();
		?>
		<div class="gm-facet gm-facet-search">
			<div class="gm-search-box">
				<span class="dashicons dashicons-search gm-search-icon" aria-hidden="true"></span>
				<input type="search" class="gm-filter-search" placeholder="<?php echo esc_attr( $placeholder ); ?>" value="<?php echo esc_attr( $search_val ); ?>" aria-label="<?php echo esc_attr( $placeholder ); ?>" />
				<?php if ( $search_val ) : ?>
					<button type="button" class="gm-search-clear" aria-label="<?php esc_attr_e( 'Clear search', 'ajax-filter-posts' ); ?>">&times;</button>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}

<?php
namespace GridMaster\Facets\Handlers;

use GridMaster\Facets\Facet_Handler_Interface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reset / Clear Filters Button Handler.
 */
class Reset_Button implements Facet_Handler_Interface {

	public static function get_label() {
		return __( 'Reset Filters Button', 'ajax-filter-posts' );
	}

	public static function render( $facet, $grid_id, $runtime_params = array(), $is_preview = false ) {
		$label = ! empty( $facet['label'] ) ? sanitize_text_field( $facet['label'] ) : __( 'Reset Filters', 'ajax-filter-posts' );

		ob_start();
		?>
		<div class="gm-facet gm-facet-reset">
			<button type="button" class="gm-btn gm-btn-reset" aria-label="<?php esc_attr_e( 'Reset all filters', 'ajax-filter-posts' ); ?>">
				<span class="dashicons dashicons-image-rotate" aria-hidden="true"></span>
				<?php echo esc_html( $label ); ?>
			</button>
		</div>
		<?php
		return ob_get_clean();
	}
}

<?php
namespace GridMaster\Facets\Handlers;

use GridMaster\Facets\Facet_Handler_Interface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dropdown Select Filter Handler.
 */
class Taxonomy_Dropdown implements Facet_Handler_Interface {

	public static function get_label() {
		return __( 'Taxonomy Dropdown', 'ajax-filter-posts' );
	}

	public static function render( $facet, $grid_id, $runtime_params = array(), $is_preview = false ) {
		$taxonomy  = ! empty( $facet['taxonomy'] ) ? sanitize_key( $facet['taxonomy'] ) : 'category';
		$heading   = ! empty( $facet['heading'] ) ? sanitize_text_field( $facet['heading'] ) : '';
		$all_label = ! empty( $facet['all_label'] ) ? sanitize_text_field( $facet['all_label'] ) : __( 'All Categories', 'ajax-filter-posts' );

		$terms = get_terms( array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => true,
		) );

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return '';
		}

		$active_terms = ! empty( $runtime_params['facets'][ $taxonomy ] ) ? (array) $runtime_params['facets'][ $taxonomy ] : array();
		$active_id    = ! empty( $active_terms[0] ) ? absint( $active_terms[0] ) : 0;

		ob_start();
		?>
		<div class="gm-facet gm-facet-dropdown" data-taxonomy="<?php echo esc_attr( $taxonomy ); ?>">
			<?php if ( $heading ) : ?>
				<label class="gm-facet-title" for="gm-dropdown-<?php echo esc_attr( $grid_id . '-' . $taxonomy ); ?>"><?php echo esc_html( $heading ); ?></label>
			<?php endif; ?>
			<select class="gm-filter-select" id="gm-dropdown-<?php echo esc_attr( $grid_id . '-' . $taxonomy ); ?>" data-taxonomy="<?php echo esc_attr( $taxonomy ); ?>">
				<option value="0"><?php echo esc_html( $all_label ); ?></option>
				<?php foreach ( $terms as $term ) : ?>
					<option value="<?php echo esc_attr( $term->term_id ); ?>" <?php selected( $active_id, $term->term_id ); ?>>
						<?php echo esc_html( $term->name ); ?> (<?php echo esc_html( $term->count ); ?>)
					</option>
				<?php endforeach; ?>
			</select>
		</div>
		<?php
		return ob_get_clean();
	}
}

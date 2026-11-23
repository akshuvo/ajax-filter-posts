<?php
namespace GridMaster\Facets\Handlers;

use GridMaster\Facets\Facet_Handler_Interface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checkbox List Filter Handler.
 */
class Taxonomy_Checkbox implements Facet_Handler_Interface {

	public static function get_label() {
		return __( 'Taxonomy Checkboxes', 'ajax-filter-posts' );
	}

	public static function render( $facet, $grid_id, $runtime_params = array(), $is_preview = false ) {
		$taxonomy = ! empty( $facet['taxonomy'] ) ? sanitize_key( $facet['taxonomy'] ) : 'category';
		$heading  = ! empty( $facet['heading'] ) ? sanitize_text_field( $facet['heading'] ) : '';

		$terms = get_terms( array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => true,
		) );

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return '';
		}

		$active_terms = ! empty( $runtime_params['facets'][ $taxonomy ] ) ? (array) $runtime_params['facets'][ $taxonomy ] : array();

		ob_start();
		?>
		<div class="gm-facet gm-facet-checkbox" data-taxonomy="<?php echo esc_attr( $taxonomy ); ?>">
			<?php if ( $heading ) : ?>
				<h4 class="gm-facet-title"><?php echo esc_html( $heading ); ?></h4>
			<?php endif; ?>
			<div class="gm-checkbox-group">
				<?php foreach ( $terms as $term ) :
					$checked = in_array( $term->term_id, $active_terms, false );
					$input_id = 'gm-cb-' . $grid_id . '-' . $term->term_id;
					?>
					<label class="gm-checkbox-label" for="<?php echo esc_attr( $input_id ); ?>">
						<input type="checkbox" id="<?php echo esc_attr( $input_id ); ?>" class="gm-filter-checkbox" data-taxonomy="<?php echo esc_attr( $taxonomy ); ?>" value="<?php echo esc_attr( $term->term_id ); ?>" <?php checked( $checked ); ?> />
						<span class="gm-checkbox-text"><?php echo esc_html( $term->name ); ?></span>
						<span class="gm-term-count">(<?php echo esc_html( $term->count ); ?>)</span>
					</label>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}

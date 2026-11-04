<?php
namespace GridMaster\Facets\Handlers;

use GridMaster\Facets\Facet_Handler_Interface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Radio List Filter Handler.
 */
class Taxonomy_Radio implements Facet_Handler_Interface {

	public static function get_label() {
		return __( 'Taxonomy Radio Buttons', 'ajax-filter-posts' );
	}

	public static function render( $facet, $grid_id, $runtime_params = array(), $is_preview = false ) {
		$taxonomy  = ! empty( $facet['taxonomy'] ) ? sanitize_key( $facet['taxonomy'] ) : 'category';
		$heading   = ! empty( $facet['heading'] ) ? sanitize_text_field( $facet['heading'] ) : '';
		$all_label = ! empty( $facet['all_label'] ) ? sanitize_text_field( $facet['all_label'] ) : __( 'All', 'ajax-filter-posts' );

		$terms = get_terms( array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => true,
		) );

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return '';
		}

		$active_terms = ! empty( $runtime_params['facets'][ $taxonomy ] ) ? (array) $runtime_params['facets'][ $taxonomy ] : array();
		$active_id    = ! empty( $active_terms[0] ) ? absint( $active_terms[0] ) : 0;
		$radio_name   = 'gm-radio-' . $grid_id . '-' . $taxonomy;

		ob_start();
		?>
		<div class="gm-facet gm-facet-radio" data-taxonomy="<?php echo esc_attr( $taxonomy ); ?>">
			<?php if ( $heading ) : ?>
				<h4 class="gm-facet-title"><?php echo esc_html( $heading ); ?></h4>
			<?php endif; ?>
			<div class="gm-radio-group">
				<label class="gm-radio-label">
					<input type="radio" name="<?php echo esc_attr( $radio_name ); ?>" class="gm-filter-radio" data-taxonomy="<?php echo esc_attr( $taxonomy ); ?>" value="0" <?php checked( 0 === $active_id ); ?> />
					<span class="gm-radio-text"><?php echo esc_html( $all_label ); ?></span>
				</label>
				<?php foreach ( $terms as $term ) :
					$checked = ( $active_id === (int) $term->term_id );
					?>
					<label class="gm-radio-label">
						<input type="radio" name="<?php echo esc_attr( $radio_name ); ?>" class="gm-filter-radio" data-taxonomy="<?php echo esc_attr( $taxonomy ); ?>" value="<?php echo esc_attr( $term->term_id ); ?>" <?php checked( $checked ); ?> />
						<span class="gm-radio-text"><?php echo esc_html( $term->name ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
